<?php

namespace App\Domain\Monitoring\Enums;

enum SourceType: string
{
    case Instagram = 'instagram';
    case YouTube = 'youtube';
    case Website = 'website';
    case Telegram = 'telegram';
    case Bale = 'bale';
    case X = 'x';
    case Custom = 'custom';

    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}