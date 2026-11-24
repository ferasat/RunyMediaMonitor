<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\Source;
use App\Services\ContentDelivery;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_caption_has_both_jalali_dates_and_stays_under_limit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-24 13:00:00', 'UTC'));
        $src = Source::create(['name' => 'nasa', 'type' => 'instagram', 'url' => 'https://www.instagram.com/nasa/', 'is_active' => true]);
        $c = Content::create(['source_id' => $src->id, 'external_id' => 'X1', 'canonical_url' => 'https://www.instagram.com/p/X1/',
            'content_hash' => 'h', 'title' => null, 'body' => str_repeat('متن خیلی طولانی ', 300), 'published_at' => '2026-11-24 10:50:00']);
        $tail = "📦 حجم: 9.6 MB\n⬇️ لینک دانلود:\nhttps://media.as7th.com/d/abc";
        $cap = app(ContentDelivery::class)->caption($c->fresh('source'), true, $tail, '', 1024);
        $this->assertLessThanOrEqual(1024, mb_strlen($cap));
        $this->assertStringContainsString('📅 تاریخ انتشار: ۱۴۰۵/۰۹/۰۳ – ۱۴:۲۰', $cap);
        $this->assertStringContainsString('📤 تاریخ ارسال: ۱۴۰۵/۰۹/۰۳ – ۱۶:۳۰', $cap);
        $this->assertStringContainsString('https://media.as7th.com/d/abc', $cap);
        $this->assertStringContainsString('https://www.instagram.com/p/X1/', $cap);

        $c->update(['published_at' => null]);
        $cap = app(ContentDelivery::class)->caption($c->fresh('source'), true, $tail, '', 300);
        $this->assertLessThanOrEqual(300, mb_strlen($cap));
        $this->assertStringContainsString('تاریخ انتشار: نامشخص', $cap);
        $this->assertStringContainsString('https://media.as7th.com/d/abc', $cap);
    }
}
