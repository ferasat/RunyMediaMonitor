<?php

namespace Tests\Feature;

use App\Jobs\DeliverContentJob;
use App\Models\Content;
use App\Models\User;
use App\Services\ContentDelivery;
use App\Services\WorkerException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ManualDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_link_is_queued_on_manual_queue_and_listed(): void
    {
        Queue::fake();
        $this->get('/manual')->assertRedirect('/login');
        $this->actingAs(User::factory()->create());
        $this->post('/manual', ['url' => 'https://evil.com/x'])->assertSessionHasErrors('url');
        $this->post('/manual', ['url' => 'https://youtu.be/QM5vh0ZKvZo'])->assertRedirect('/manual');
        $this->post('/manual', ['url' => 'https://www.youtube.com/watch?v=QM5vh0ZKvZo'])->assertRedirect('/manual');
        $this->assertSame(1, Content::count()); // same video re-queued, not duplicated
        Queue::assertPushedOn('manual', DeliverContentJob::class);
        $this->get('/manual')->assertOk()->assertSee('youtube.com/watch?v=QM5vh0ZKvZo')->assertSee('در صف');
        // manual items/sources are hidden from the monitoring dashboard
        $this->get('/')->assertOk()->assertDontSee('manual://');
    }

    public function test_disk_guard_blocks_download(): void
    {
        config(['rmm.min_free_gb' => 1e9, 'rmm.bale.token' => 'T', 'rmm.bale.token_file' => null, 'rmm.bale.chat_id' => '1']);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['ok' => true, 'result' => []])]);
        $this->expectException(WorkerException::class);
        app(ContentDelivery::class)->guardDisk(sys_get_temp_dir().'/rmm-guard-'.uniqid());
    }
}
