<?php

namespace Tests\Unit;

use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_due_when_never_checked(): void
    {
        $s = new Source(['last_checked_at' => null, 'check_interval_minutes' => 60]);
        $this->assertTrue($s->isDue());
    }
}