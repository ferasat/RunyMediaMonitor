<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Gregorian -> Solar Hijri (Jalali/Shamsi) conversion, no extensions needed. */
class Jalali
{
    /** @return array{0:int,1:int,2:int} [jy, jm, jd] */
    public static function fromGregorian(int $gy, int $gm, int $gd): array
    {
        $gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $gdm[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return [$jy, $jm, $jd];
    }

    public static function digits(string $s): string
    {
        return strtr($s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }

    /** e.g. ۱۴۰۵/۰۹/۰۳ – ۱۴:۲۰ (in Asia/Tehran unless another tz is given). */
    public static function format(?CarbonInterface $dt, bool $withTime = true, ?string $tz = null): string
    {
        if (! $dt) {
            return 'نامشخص';
        }
        $d = $dt->copy()->setTimezone($tz ?? config('rmm.timezone', 'Asia/Tehran'));
        [$y, $m, $day] = self::fromGregorian((int) $d->format('Y'), (int) $d->format('n'), (int) $d->format('j'));
        $s = sprintf('%04d/%02d/%02d', $y, $m, $day).($withTime ? ' – '.$d->format('H:i') : '');
        return self::digits($s);
    }

    /** Publish date; a value at exactly 00:00:00 UTC came from a date-only source (e.g. YouTube upload_date) -> show the date only. */
    public static function published(?CarbonInterface $dt): string
    {
        if (! $dt) {
            return 'نامشخص';
        }
        $utc = $dt->copy()->setTimezone('UTC');
        return $utc->format('H:i:s') === '00:00:00' ? self::format($utc, false, 'UTC') : self::format($dt);
    }
}
