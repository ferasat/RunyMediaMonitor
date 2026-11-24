<?php

namespace Tests\Feature;

use App\Jobs\DeliverContentJob;
use App\Models\Content;
use App\Services\BaleBotHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BaleBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['rmm.bale.token' => 'T', 'rmm.bale.token_file' => null, 'rmm.bale.chat_id' => '69089008', 'cache.default' => 'array']);
        Http::fake(['tapi.bale.ai/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
        Queue::fake();
    }

    protected function msg(string $text, string $chat = '69089008'): array
    {
        return ['update_id' => 1, 'message' => ['message_id' => 5, 'chat' => ['id' => (int) $chat], 'from' => ['id' => (int) $chat], 'text' => $text]];
    }

    public function test_new_link_flow(): void
    {
        $h = app(BaleBotHandler::class);
        $this->assertSame('asked', $h->handle($this->msg('/new_link')));
        Http::assertSent(fn ($r) => ($r['text'] ?? '') === BaleBotHandler::ASK);
        $this->assertSame('no-link', $h->handle($this->msg('سلام')));
        $this->assertSame('queued', $h->handle($this->msg('https://www.instagram.com/reel/Df3Rp0bKNdr/?igsh=x')));
        Queue::assertPushedOn('manual', DeliverContentJob::class);
        $this->assertSame('bale', Content::first()->metadata['via']);
        $this->assertSame('busy', $h->handle($this->msg('https://www.instagram.com/reel/Df3Rp0bKNdr/')));
        $this->assertSame('queued', $h->handle($this->msg('https://youtu.be/QM5vh0ZKvZo'))); // plain link, no command
        $this->assertSame('invalid', $h->handle($this->msg('/new_link https://example.com/x')));
    }

    public function test_other_chats_are_refused_once(): void
    {
        $h = app(BaleBotHandler::class);
        $this->assertSame('refused', $h->handle($this->msg('https://youtu.be/QM5vh0ZKvZo', '123')));
        $this->assertSame('refused', $h->handle($this->msg('/new_link', '123')));
        Http::assertSentCount(1);
        Queue::assertNothingPushed();
        $this->assertSame(0, Content::count());
    }
}
