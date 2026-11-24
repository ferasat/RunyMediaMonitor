<?php

namespace Tests\Feature;

use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SourceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requires_login(): void
    {
        $this->getJson('/api/sources')->assertUnauthorized();
        $this->get('/')->assertRedirect('/login');
    }

    public function test_create_and_list_sources(): void
    {
        $this->actingAs(User::factory()->create());
        $res = $this->postJson('/api/sources', ['type' => 'website', 'name' => 'Example', 'url' => 'https://example.com/feed.xml']);
        $res->assertStatus(201);
        $this->getJson('/api/sources')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_show_update_delete(): void
    {
        $this->actingAs(User::factory()->create());
        $s = Source::create(['type' => 'website', 'name' => 'X', 'url' => 'https://x.com/feed']);
        $this->getJson("/api/sources/{$s->id}")->assertOk();
        $this->patchJson("/api/sources/{$s->id}", ['name' => 'Y'])->assertOk()->assertJsonFragment(['name' => 'Y']);
        $this->deleteJson("/api/sources/{$s->id}")->assertNoContent();
    }
}
