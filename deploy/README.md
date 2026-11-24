# RunyMediaMonitor — deployment on auto2 (media.as7th.com)

Layout (nothing shared with other sites):

| What | Where |
|---|---|
| Code (git clone of this repo) | `/var/www/runymedia` (`.env` chmod 640, not in git) |
| SQLite DB | `/var/lib/runymedia/db/database.sqlite` |
| Downloaded media (7-day retention) | `/var/lib/runymedia/media` |
| Secrets (chmod 600, www-data) | `/var/lib/runymedia/secrets/` — `bale_token`, `instagram.env`, `instagram_cookies.txt`, `instagram.session`, `admin_password` |
| Python worker venv (yt-dlp, gallery-dl, instaloader) | `/opt/runymedia/venv` (Python 3.12 via uv) |
| ffmpeg/ffprobe (static), deno (yt-dlp JS runtime), uv | `/opt/runymedia/bin` |
| sqlite PHP extension (extracted from php8.5-sqlite3 .deb) | `/opt/runymedia/php/ext`, loaded via `PHP_INI_SCAN_DIR=:/opt/runymedia/php/conf.d` |

Services (systemd): `runymedia-fpm` (dedicated php-fpm8.5 master, socket `/run/runymedia/fpm.sock`),
`runymedia-queue` (queue:work), `runymedia-scheduler` (schedule:work), `runymedia-ytdlp-update.timer` (weekly),
`lego-renew-media-as7th.timer` (TLS, DNS-01 via Liara, same as the other as7th.com sites).

Schedule: `RMM_CHECK_TIMES` (default `08:00,09:01`) in `RMM_TIMEZONE` (Asia/Tehran); cleanup hourly.

## Common commands (as root on auto2)

```bash
A=/opt/runymedia/bin/rmm-artisan
$A rmm:add "https://www.youtube.com/@TED"            # baseline only (old posts marked seen)
$A rmm:add "https://www.instagram.com/nasa/" --send-latest=1
$A rmm:check            # queue a check of all active sources now
$A rmm:check 1 --sync   # check source #1 in the foreground
$A rmm:send 12 --sync   # (re)deliver content #12
$A rmm:cleanup
$A rmm:bale-test
$A schedule:list
# refresh Instagram session (after checkpoint / expiry):
sudo -u www-data /opt/runymedia/venv/bin/python /var/www/runymedia/worker/rmm_worker.py ig-login \
  --env-file /var/lib/runymedia/secrets/instagram.env \
  --session-file /var/lib/runymedia/secrets/instagram.session \
  --cookies-file /var/lib/runymedia/secrets/instagram_cookies.txt
```

## Update

```bash
cd /var/www/runymedia && sudo -u www-data git pull --ff-only
sudo -u www-data COMPOSER_HOME=/var/lib/runymedia/home composer install --no-dev -o
$A migrate --force && $A config:cache && $A route:cache && $A view:cache
systemctl restart runymedia-queue runymedia-scheduler && systemctl reload runymedia-fpm
```
