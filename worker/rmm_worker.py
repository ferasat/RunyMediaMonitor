#!/usr/bin/env python3
"""RunyMediaMonitor media worker.

Small CLI used by the Laravel app (via Symfony Process). Every command prints a
single JSON object on stdout: {"ok": true, ...} or {"ok": false, "error": "...",
"code": "..."}. Logs go to stderr. Secrets are never printed.

Commands:
  discover --platform youtube|instagram --url URL --limit N
  download --platform youtube|instagram --url URL --out DIR [--max-res 720]
  ig-login --env-file FILE --session-file FILE --cookies-file FILE
  ig-check --cookies-file FILE

Download pattern based on free_download_instagram_youtube/processing
(yt-dlp + cookies + ffmpeg, size/format policy).
"""
from __future__ import annotations

import argparse
import http.cookiejar
import json
import os
import re
import subprocess
import sys
import time
from pathlib import Path

VIDEO_EXT = {".mp4", ".mkv", ".webm", ".mov", ".m4v"}
IMAGE_EXT = {".jpg", ".jpeg", ".png", ".webp", ".heic"}


def out(obj: dict) -> None:
    sys.stdout.write(json.dumps(obj, ensure_ascii=False) + "\n")
    sys.stdout.flush()


def log(msg: str) -> None:
    sys.stderr.write(f"[rmm_worker] {msg}\n")
    sys.stderr.flush()


class WorkerError(Exception):
    def __init__(self, code: str, message: str):
        super().__init__(message)
        self.code = code
        self.message = message


def classify(message: str) -> str:
    m = message.lower()
    if "sign in to confirm" in m or "not a bot" in m:
        return "YOUTUBE_BOT_CHECK"
    if "checkpoint" in m or "challenge" in m:
        return "INSTAGRAM_CHECKPOINT"
    if "login" in m or "cookies" in m or "401" in m or "403" in m:
        return "AUTH_REQUIRED"
    if "429" in m or "rate" in m or "please wait" in m:
        return "RATE_LIMITED"
    if "private" in m:
        return "PRIVATE"
    if "unavailable" in m or "not found" in m or "404" in m:
        return "NOT_FOUND"
    return "FAILED"


# ----------------------------------------------------------------- YouTube

def yt_channel_base(url: str) -> str:
    url = url.strip().split("?")[0].rstrip("/")
    if not url.startswith("http"):
        url = "https://www.youtube.com/" + (url if url.startswith("@") else "@" + url)
    url = re.sub(r"/(videos|shorts|streams|featured|about|playlists|community)$", "", url)
    return url


def yt_opts(extra: dict | None = None) -> dict:
    opts = {
        "quiet": True,
        "no_warnings": True,
        "socket_timeout": 30,
        "retries": 3,
        "fragment_retries": 3,
        "noprogress": True,
    }
    cookies = os.environ.get("RMM_YT_COOKIES")
    if cookies and Path(cookies).is_file():
        opts["cookiefile"] = cookies
    js = os.environ.get("RMM_JS_RUNTIME")  # e.g. "deno" or "node"
    if js:
        opts["js_runtimes"] = {js: {}}
    ffmpeg = os.environ.get("RMM_FFMPEG_DIR")
    if ffmpeg:
        opts["ffmpeg_location"] = ffmpeg
    if extra:
        opts.update(extra)
    return opts


def yt_discover(url: str, limit: int) -> dict:
    import yt_dlp

    base = yt_channel_base(url)
    items: list[dict] = []
    seen: set[str] = set()
    channel = {}
    for tab, is_short in (("videos", False), ("shorts", True)):
        try:
            with yt_dlp.YoutubeDL(yt_opts({"extract_flat": "in_playlist", "playlistend": limit})) as ydl:
                info = ydl.extract_info(f"{base}/{tab}", download=False)
        except Exception as exc:  # tab may not exist (channel without shorts)
            msg = str(exc)
            if "does not have a" in msg.lower() or "this channel does not" in msg.lower():
                continue
            if tab == "shorts":
                log(f"shorts tab skipped: {msg[:200]}")
                continue
            raise WorkerError(classify(msg), msg[:500])
        channel = channel or {"channel_id": info.get("channel_id"), "channel": info.get("channel") or info.get("uploader")}
        for pos, e in enumerate(info.get("entries") or []):
            vid = e.get("id")
            if not vid or vid in seen:
                continue
            seen.add(vid)
            if e.get("live_status") in ("is_upcoming", "is_live"):
                continue
            items.append({
                "external_id": vid,
                "url": f"https://www.youtube.com/shorts/{vid}" if is_short else f"https://www.youtube.com/watch?v={vid}",
                "title": e.get("title"),
                "description": e.get("description"),
                "published_at": _ts(e.get("timestamp") or e.get("release_timestamp")),
                "media_type": "video",
                "is_short": is_short,
                "duration": e.get("duration"),
                "position": pos,
                "tab": tab,
            })
    return {"ok": True, "channel": channel, "items": items}


def yt_download(url: str, out_dir: Path, max_res: int) -> dict:
    import yt_dlp

    out_dir.mkdir(parents=True, exist_ok=True)
    opts = yt_opts({
        "outtmpl": str(out_dir / "%(id)s.%(ext)s"),
        "noplaylist": True,
        # prefer the largest resolution <= max_res (smallest side), i.e. 720 -> 480 -> 360 -> 240,
        # otherwise whatever exists; prefer mp4/h264/aac so Bale plays it inline.
        "format": "bv*+ba/b",
        "format_sort": [f"res:{max_res}", "vcodec:h264", "acodec:aac", "ext:mp4:m4a"],
        "merge_output_format": "mp4",
        "writethumbnail": False,
        "overwrites": True,
    })
    try:
        with yt_dlp.YoutubeDL(opts) as ydl:
            info = ydl.extract_info(url, download=True)
    except Exception as exc:
        msg = str(exc)
        raise WorkerError(classify(msg), msg[:500])
    files = _collect(out_dir)
    if not files:
        raise WorkerError("FAILED", "download produced no file")
    for f in files:
        f.update({"width": info.get("width"), "height": info.get("height"), "duration": info.get("duration")})
    return {
        "ok": True,
        "title": info.get("title"),
        "description": (info.get("description") or "")[:2000],
        "published_at": _ts(info.get("timestamp")) or _date(info.get("upload_date")),
        "resolution": info.get("resolution"),
        "format_note": info.get("format_note"),
        "files": files,
    }


# --------------------------------------------------------------- Instagram

def ig_username(url: str) -> str:
    u = url.strip()
    m = re.search(r"instagram\.com/([A-Za-z0-9_.]+)", u)
    name = m.group(1) if m else u.lstrip("@")
    if name in ("p", "reel", "reels", "stories", "explore"):
        raise WorkerError("BAD_URL", "not a profile URL")
    return name


def _gallery_dl(args: list[str], timeout: int = 600) -> subprocess.CompletedProcess:
    exe = Path(sys.executable).with_name("gallery-dl")
    cmd = [str(exe) if exe.exists() else "gallery-dl", *args]
    return subprocess.run(cmd, capture_output=True, text=True, timeout=timeout)


def _ig_common(cookies: str | None) -> list[str]:
    args = ["--config-ignore", "-o", "extractor.instagram.api=rest", "--sleep-request", "2-5"]
    if cookies and Path(cookies).is_file():
        args += ["--cookies", cookies]
    return args


def _require_cookies(cookies: str | None) -> None:
    if not cookies or not Path(cookies).is_file() or Path(cookies).stat().st_size == 0:
        raise WorkerError("AUTH_REQUIRED", "Instagram cookies file missing (RMM_IG_COOKIES)")


def ig_discover(url: str, limit: int, cookies: str | None) -> dict:
    _require_cookies(cookies)
    user = ig_username(url)
    items: dict[str, dict] = {}
    for kind in ("posts", "reels"):
        p = _gallery_dl([*_ig_common(cookies), "-j", "--post-range", f"1-{limit}",
                         f"https://www.instagram.com/{user}/{kind}/"])
        if p.returncode not in (0, 4, 8) and not p.stdout.strip():
            msg = (p.stderr or "")[-600:]
            raise WorkerError(classify(msg), msg.strip() or f"gallery-dl exit {p.returncode}")
        try:
            data = json.loads(p.stdout or "[]")
        except json.JSONDecodeError:
            msg = (p.stderr or p.stdout or "")[-600:]
            raise WorkerError(classify(msg), "unparseable gallery-dl output: " + msg)
        if isinstance(data, list) and data and isinstance(data[0], list) and data[0][0] == -1:
            # gallery-dl error message entry
            err = data[0][1] if len(data[0]) > 1 else {}
            msg = json.dumps(err)[:500]
            raise WorkerError(classify(msg), msg)
        for msg in data if isinstance(data, list) else []:
            if not isinstance(msg, list) or len(msg) < 2:
                continue
            kw = msg[-1] if isinstance(msg[-1], dict) else {}
            sc = kw.get("post_shortcode") or kw.get("shortcode")
            if not sc:
                continue
            it = items.setdefault(sc, {
                "external_id": sc,
                "url": f"https://www.instagram.com/p/{sc}/",
                "title": None,
                "description": (kw.get("description") or "")[:2000],
                "published_at": _iso(kw.get("post_date") or kw.get("date")),
                "media_type": "image",
                "count": 0,
                "kind": kind,
            })
            if msg[0] == 3:
                # a post can appear in both the posts and reels tabs: count distinct media numbers only
                nums = it.setdefault("_nums", set())
                nums.add(kw.get("num") or len(nums) + 1)
                it["count"] = max(len(nums), int(kw.get("count") or 0))
                is_video = bool(kw.get("video_url")) or kw.get("extension") == "mp4"
                if it["count"] > 1:
                    it["media_type"] = "carousel"
                elif is_video:
                    it["media_type"] = "video"
        if kind == "posts":
            time.sleep(3)
    for it in items.values():
        it.pop("_nums", None)
    lst = sorted(items.values(), key=lambda x: x.get("published_at") or "", reverse=True)
    return {"ok": True, "channel": {"username": user}, "items": lst}


def ig_download(url: str, out_dir: Path, cookies: str | None) -> dict:
    _require_cookies(cookies)
    out_dir.mkdir(parents=True, exist_ok=True)
    p = _gallery_dl([*_ig_common(cookies), "--sleep", "1-3", "-D", str(out_dir), "-f", "{num:>02}.{extension}",
                     "--write-metadata", url], timeout=1200)
    files = _collect(out_dir)
    if not files:
        msg = (p.stderr or "")[-600:]
        raise WorkerError(classify(msg), msg.strip() or "no media downloaded")
    desc = None
    for meta in sorted(out_dir.glob("*.json")):
        try:
            desc = json.loads(meta.read_text(encoding="utf-8")).get("description")
        except Exception:
            pass
        meta.unlink(missing_ok=True)
        if desc:
            break
    files = _collect(out_dir)
    return {"ok": True, "description": (desc or "")[:2000], "files": files}


def ig_login(env_file: str, session_file: str, cookies_file: str, force: bool = False) -> dict:
    """Manual, last-resort password login. Never used by the app. Refuses when cookies already exist
    (password logins from the server trigger Instagram checkpoints)."""
    import instaloader

    if Path(cookies_file).is_file() and Path(cookies_file).stat().st_size > 0 and not force:
        raise WorkerError("COOKIES_PRESENT", "cookies file exists; password login disabled (use --force to override)")

    env = {}
    for line in Path(env_file).read_text(encoding="utf-8").splitlines():
        if "=" in line and not line.strip().startswith("#"):
            k, v = line.split("=", 1)
            env[k.strip()] = v.strip().strip('"').strip("'")
    user, pw = env.get("IG_USER"), env.get("IG_PASS")
    if not user or not pw:
        raise WorkerError("CONFIG", "IG_USER/IG_PASS missing in env file")
    L = instaloader.Instaloader(quiet=True, download_pictures=False, download_videos=False,
                                save_metadata=False, max_connection_attempts=1)
    try:
        L.login(user, pw)
    except instaloader.TwoFactorAuthRequiredException:
        raise WorkerError("INSTAGRAM_2FA", "two-factor code required; run ig-login with --code")
    except instaloader.BadCredentialsException:
        raise WorkerError("BAD_CREDENTIALS", "Instagram rejected username/password")
    except instaloader.ConnectionException as exc:
        msg = str(exc).replace(pw, "***")
        raise WorkerError(classify(msg), msg[:500])
    finally:
        pw = None  # noqa: F841
    os.umask(0o077)
    L.save_session_to_file(session_file)
    _write_netscape(L.context._session.cookies, cookies_file)
    os.chmod(session_file, 0o600)
    os.chmod(cookies_file, 0o600)
    return {"ok": True, "user": user, "cookies": len(L.context._session.cookies)}


def ig_check(cookies: str) -> dict:
    p = _gallery_dl([*_ig_common(cookies), "-j", "--post-range", "1",
                     "https://www.instagram.com/instagram/posts/"], timeout=120)
    ok = '"post_shortcode"' in (p.stdout or "")
    return {"ok": ok, "error": None if ok else (p.stderr or "")[-300:], "code": None if ok else classify(p.stderr or "")}


def _write_netscape(jar, path: str) -> None:
    lines = ["# Netscape HTTP Cookie File"]
    for c in jar:
        domain = c.domain if c.domain.startswith(".") else "." + c.domain.lstrip(".")
        lines.append("\t".join([
            domain, "TRUE", c.path or "/", "TRUE" if c.secure else "FALSE",
            str(int(c.expires or time.time() + 365 * 86400)), c.name, c.value or "",
        ]))
    Path(path).write_text("\n".join(lines) + "\n", encoding="utf-8")


# ----------------------------------------------------------------- helpers

def _collect(d: Path) -> list[dict]:
    files = []
    for f in sorted(d.iterdir()):
        if not f.is_file() or f.name.endswith((".part", ".json", ".ytdl")):
            continue
        ext = f.suffix.lower()
        kind = "video" if ext in VIDEO_EXT else "photo" if ext in IMAGE_EXT else "document"
        files.append({"path": str(f), "name": f.name, "size": f.stat().st_size, "kind": kind})
    return files


def _ts(v):
    if not v:
        return None
    try:
        return time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime(int(v)))
    except Exception:
        return None


def _date(v):
    if v and re.fullmatch(r"\d{8}", str(v)):
        return f"{v[:4]}-{v[4:6]}-{v[6:]}T00:00:00Z"
    return None


def _iso(v):
    if not v:
        return None
    s = str(v).replace(" ", "T")
    return s if s.endswith("Z") or "+" in s else s + "Z"


def main() -> int:
    ap = argparse.ArgumentParser()
    sub = ap.add_subparsers(dest="cmd", required=True)
    d = sub.add_parser("discover")
    d.add_argument("--platform", required=True, choices=["youtube", "instagram"])
    d.add_argument("--url", required=True)
    d.add_argument("--limit", type=int, default=30)
    dl = sub.add_parser("download")
    dl.add_argument("--platform", required=True, choices=["youtube", "instagram"])
    dl.add_argument("--url", required=True)
    dl.add_argument("--out", required=True)
    dl.add_argument("--max-res", type=int, default=720)
    lg = sub.add_parser("ig-login")
    lg.add_argument("--env-file", required=True)
    lg.add_argument("--session-file", required=True)
    lg.add_argument("--cookies-file", required=True)
    lg.add_argument("--force", action="store_true")
    ck = sub.add_parser("ig-check")
    ck.add_argument("--cookies-file", required=True)
    a = ap.parse_args()
    cookies = os.environ.get("RMM_IG_COOKIES")
    try:
        if a.cmd == "discover":
            res = yt_discover(a.url, a.limit) if a.platform == "youtube" else ig_discover(a.url, a.limit, cookies)
        elif a.cmd == "download":
            res = (yt_download(a.url, Path(a.out), a.max_res) if a.platform == "youtube"
                   else ig_download(a.url, Path(a.out), cookies))
        elif a.cmd == "ig-login":
            res = ig_login(a.env_file, a.session_file, a.cookies_file, a.force)
        else:
            res = ig_check(a.cookies_file)
        out(res)
        return 0 if res.get("ok") else 1
    except WorkerError as e:
        out({"ok": False, "code": e.code, "error": e.message})
        return 1
    except Exception as e:  # pragma: no cover
        msg = str(e)
        out({"ok": False, "code": classify(msg), "error": msg[:500]})
        return 1


if __name__ == "__main__":
    sys.exit(main())
