<?php

namespace Tests\Unit;

use App\Support\Jalali;
use Carbon\Carbon;
use Tests\TestCase;

class JalaliTest extends TestCase
{
    public function test_gregorian_to_jalali(): void
    {
        $this->assertSame([1405, 9, 3], Jalali::fromGregorian(2026, 11, 24));
        $this->assertSame([1405, 1, 1], Jalali::fromGregorian(2026, 3, 21));
        $this->assertSame([1404, 12, 29], Jalali::fromGregorian(2026, 3, 20));
        $this->assertSame([1404, 1, 1], Jalali::fromGregorian(2025, 3, 21));
        $this->assertSame([1403, 12, 30], Jalali::fromGregorian(2025, 3, 20)); // 1403 is a leap year
        $this->assertSame([1399, 10, 11], Jalali::fromGregorian(2020, 12, 31));
    }

    public function test_format_in_tehran_with_persian_digits(): void
    {
        $this->assertSame('۱۴۰۵/۰۹/۰۳ – ۱۴:۲۰', Jalali::format(Carbon::parse('2026-11-24 10:50:00', 'UTC'), true, 'Asia/Tehran'));
        // 20:40 UTC is already the next day in Tehran
        $this->assertSame('۱۴۰۵/۰۹/۰۴ – ۰۰:۱۰', Jalali::format(Carbon::parse('2026-11-24 20:40:00', 'UTC'), true, 'Asia/Tehran'));
        $this->assertSame('نامشخص', Jalali::format(null));
    }

    public function test_published_date_only_and_unknown(): void
    {
        config(['rmm.timezone' => 'Asia/Tehran']);
        $this->assertSame('۱۴۰۵/۰۹/۰۳', Jalali::published(Carbon::parse('2026-11-24 00:00:00', 'UTC')));
        $this->assertSame('۱۴۰۵/۰۹/۰۳ – ۱۴:۲۰', Jalali::published(Carbon::parse('2026-11-24 10:50:00', 'UTC')));
        $this->assertSame('نامشخص', Jalali::published(null));
    }
}
