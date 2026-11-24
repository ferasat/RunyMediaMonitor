#!/bin/bash
# RunyMediaMonitor end-to-end test (run as root on auto2). Prints no secrets.
#   1) Bale bot reachability   2) YouTube small (<=49MB, file+link) and big (>49MB, link only)
#   3) Instagram login (if no cookies yet) + baseline with the 2 newest posts delivered
A=/opt/runymedia/bin/rmm-artisan
S=/var/lib/runymedia/secrets
PY=/opt/runymedia/venv/bin/python
W=/var/www/runymedia/worker/rmm_worker.py
SMALL=${SMALL:-62}; BIG=${BIG:-1}; IG_URL=${IG_URL:-https://www.instagram.com/nasa/}

echo "== 1. Bale"; $A rmm:bale-test || { echo "STOP: Amin must press Start in @Insta_youtube_bot"; exit 2; }
echo "== 2a. YouTube small #$SMALL"; $A rmm:send "$SMALL" --sync --force 2>&1 | grep -v psysh
echo "== 2b. YouTube big #$BIG";   $A rmm:send "$BIG" --sync --force 2>&1 | grep -v psysh
echo "== 3. Instagram"
if [ ! -s $S/instagram_cookies.txt ]; then
  runuser -u www-data -- env HOME=/var/lib/runymedia/home $PY $W ig-login --env-file $S/instagram.env \
    --session-file $S/instagram.session --cookies-file $S/instagram_cookies.txt || { echo "STOP: Instagram login blocked (see code above)"; exit 3; }
fi
runuser -u www-data -- env HOME=/var/lib/runymedia/home $PY $W ig-check --cookies-file $S/instagram_cookies.txt || { echo "STOP: Instagram cookies not accepted"; exit 3; }
$A rmm:add "$IG_URL" --no-check
ID=$($A tinker --execute='echo App\Models\Source::where("url", "'"$IG_URL"'")->value("id");' 2>/dev/null | grep -E '^[0-9]+$' | tail -1)
[ -n "$ID" ] || { echo "STOP: could not resolve Instagram source id"; exit 4; }
$A tinker --execute='App\Models\Source::whereKey('"$ID"')->update(["baselined_at"=>null]);' >/dev/null 2>&1
$A tinker --execute='print_r((new App\Jobs\SourceCheckJob(App\Models\Source::find('"$ID"'), 2))->handle(app(App\Infrastructure\Adapters\AdapterRegistry::class)));' 2>&1 | grep -v psysh
echo "(the 2 newest Instagram posts were queued; the queue worker delivers them within ~1 min)"
sleep 90
$A tinker --execute='foreach (App\Models\Content::with("files")->whereIn("status",["sent","failed","downloading","pending"])->latest("id")->limit(6)->get() as $c) { echo $c->id," ",$c->status," ",$c->canonical_url," files=",$c->files->map(fn($f)=>$f->kind.":".$f->sizeMb()."MB:".$f->delivery)->implode(","),"  ",mb_substr((string)$c->error_message,0,120),"\n"; }' 2>&1 | grep -v psysh
echo "== schedule"; $A schedule:list
systemctl list-timers --no-legend | grep -E "runymedia|lego-renew-media"
systemctl is-active runymedia-fpm runymedia-queue runymedia-scheduler
