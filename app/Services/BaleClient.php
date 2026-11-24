<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class BaleClient
{
    public function token(): string
    {
        $file = config('rmm.bale.token_file');
        $token = $file && is_readable($file) ? trim((string) file_get_contents($file)) : (string) config('rmm.bale.token');
        if ($token === '') {
            throw new RuntimeException('Bale bot token is not configured');
        }
        return $token;
    }

    public function chatId(): string
    {
        return (string) config('rmm.bale.chat_id');
    }

    protected function url(string $method): string
    {
        return rtrim(config('rmm.bale.api'), '/').'/bot'.$this->token().'/'.$method;
    }

    public function call(string $method, array $params = []): array
    {
        $res = Http::timeout(60)->asForm()->post($this->url($method), $params);
        return $this->check($method, $res);
    }

    public function sendMessage(string $text, ?string $chatId = null): array
    {
        return $this->call('sendMessage', ['chat_id' => $chatId ?? $this->chatId(), 'text' => mb_substr($text, 0, 4000)]);
    }

    /** $method: sendVideo|sendPhoto|sendDocument; $field: video|photo|document */
    public function sendFile(string $method, string $field, string $path, string $caption, ?string $chatId = null, array $extra = []): array
    {
        $res = Http::timeout(600)
            ->attach($field, fopen($path, 'r'), basename($path))
            ->post($this->url($method), array_merge([
                'chat_id' => $chatId ?? $this->chatId(),
                'caption' => mb_substr($caption, 0, 1000),
            ], $extra));
        return $this->check($method, $res);
    }

    protected function check(string $method, $res): array
    {
        $json = $res->json() ?? [];
        if (! $res->successful() || ! ($json['ok'] ?? false)) {
            $desc = $json['description'] ?? mb_substr($res->body(), 0, 300);
            throw new RuntimeException("Bale {$method} failed (HTTP {$res->status()}): {$desc}");
        }
        return $json['result'] ?? [];
    }
}
