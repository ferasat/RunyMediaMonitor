<?php

namespace App\Support;

/** Parse a single YouTube video/shorts or Instagram post/reel URL (pasted by the admin). */
class MediaLink
{
    public function __construct(
        public string $platform,   // youtube | instagram
        public string $id,
        public string $url,        // canonical URL handed to the worker
        public bool $isShort = false,
        public string $kind = 'video',
    ) {}

    public static function parse(string $input): ?self
    {
        $input = trim($input);
        $parts = parse_url($input);
        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }
        $host = strtolower(preg_replace('/^(www\.|m\.|music\.)/', '', $parts['host'] ?? ''));
        $path = $parts['path'] ?? '/';
        parse_str($parts['query'] ?? '', $q);

        if ($host === 'youtu.be' && preg_match('~^/([\w-]{11})~', $path, $m)) {
            return new self('youtube', $m[1], "https://www.youtube.com/watch?v={$m[1]}");
        }
        if ($host === 'youtube.com') {
            if (preg_match('~^/shorts/([\w-]{11})~', $path, $m)) {
                return new self('youtube', $m[1], "https://www.youtube.com/shorts/{$m[1]}", true);
            }
            if (preg_match('~^/(live|embed)/([\w-]{11})~', $path, $m)) {
                return new self('youtube', $m[2], "https://www.youtube.com/watch?v={$m[2]}");
            }
            if ($path === '/watch' && isset($q['v']) && is_string($q['v']) && preg_match('~^[\w-]{11}$~', $q['v'])) {
                return new self('youtube', $q['v'], "https://www.youtube.com/watch?v={$q['v']}");
            }
            return null;
        }
        if ($host === 'instagram.com' && preg_match('~^/(?:[\w.]+/)?(p|reel|reels|tv)/([\w-]{5,})~', $path, $m)) {
            $kind = $m[1] === 'p' ? 'post' : 'reel';
            $seg = $m[1] === 'p' ? 'p' : 'reel';
            return new self('instagram', $m[2], "https://www.instagram.com/{$seg}/{$m[2]}/", false, $kind);
        }
        return null;
    }
}
