<?php

namespace App\Enums\Achievements;

enum Milestone:int
{
    case FIRST                     =   1;      // Every walk begins with a single step
    case MEANING_OF_LIFE           =  42;      // The ultimate answer to life, the universe, and everything
    case NICE                      =  69;      // Nice number, bro
    case BYTE_POWER                = 256;      // 2^8, the classic byte size
    case FULL_CIRCLE               = 360;      // Every degree is part of a circle
    case NOT_FOUND                 = 404;      // Milestone not found
    case BLAZE_IT                  = 420;      // Blaze it bro its 4:20pm somewhere
    case DMCA                      = 451;      // Unavailable for legal reasons
    case KIBIBYTE                  = 512;      // 2 × 256
    case MR_BEAST                  = 666;      // Mark of himself
    case JACKPOT                   = 777;      // Vegas vibes
    case LEET                      = 1337;     // 1337 h4x0r
    case POWER_TWO_K               = 2048;     // 2^11, a common power of two
    case QUADS_OF_THREE            = 3333;     // 3 × 1111
    case HOUR_OF_SECONDS           = 3600;     // 1 hour in seconds
    case OVER_9000                 = 9001;     // It’s OVER 9000 !!!
    case MEGA_LEET                 = 13337;    // next-level h4x
    case BLAZE_NICE                = 42069;    // 420 + 69 = perfection
    case NICE_BLAZE                = 69420;    // the flippening
    case ULTRA_LEET                = 133337;   // hxr1
    case DOUBLE_BLAZE              = 420420;   // 🔥🔥
    case HELL_ON_EARTH             = 666666;   // *spooky*
    case DOUBLE_NICE               = 696969;   // (☞ﾟヮﾟ)☞
    case GALACTIC_BLAZE            = 4206969;  // cosmic meme

    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}
