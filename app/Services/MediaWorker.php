<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/** Thin wrapper around worker/rmm_worker.py (yt-dlp / gallery-dl / instaloader). */
class MediaWorker
{
    public function discover(string $platform, string $url, int $limit): array
    {
        return $this->run(['discover', '--platform', $platform, '--url', $url, '--limit', (string) $limit]);
    }

    public function download(string $platform, string $url, string $outDir): array
    {
        return $this->run(['download', '--platform', $platform, '--url', $url, '--out', $outDir,
            '--max-res', (string) config('rmm.max_resolution')]);
    }

    public function run(array $args): array
    {
        $cfg = config('rmm.worker');
        $result = Process::timeout($cfg['timeout'])
            ->env($cfg['env'])
            ->run([$cfg['python'], $cfg['script'], ...$args]);
        $lines = array_values(array_filter(explode("\n", trim($result->output()))));
        $json = $lines ? json_decode(end($lines), true) : null;
        if (! is_array($json)) {
            throw new WorkerException('WORKER_CRASH', 'worker returned no JSON: '.mb_substr($result->errorOutput(), -400));
        }
        if (! ($json['ok'] ?? false)) {
            throw new WorkerException($json['code'] ?? 'FAILED', $json['error'] ?? 'unknown worker error');
        }
        return $json;
    }
}
