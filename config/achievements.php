<?php

return [
    'milestones' => [
        1,
        42,
        69,
        256,
        360,
        404,
        420,
        451,
        512,
        666,
        777,
        1337,
        2048,
        3333,
        3600,
        9001,
        13337,
        42069,
        69420,
        133337,
        420420,
        666666,
        696969,
        4206969,
    ],

    /**
     * XP scale - threshold => XP value
     * The XP value applies to all milestones up to and including that threshold
     */
    'xp_scale' => [
        1 => 5,
        10 => 10,
        42 => 20,
        69 => 30,
        420 => 50,
        1337 => 100,
        42069 => 150,
        69420 => 200,
    ],

    /**
     * Cache TTL for achievement data (in seconds)
     */
    'cache_ttl' => 86400, // 24 hours

    /**
     * Batch size for processing achievements
     */
    'batch_size' => 100,

    /**
     * Whether to dispatch events when achievements are unlocked
     */
    'dispatch_events' => true,

    /**
     * Level thresholds based on total XP
     */
    'levels' => [
        0 => 1,      // 0 XP = Level 1
        100 => 2,    // 100 XP = Level 2
        500 => 3,    // 500 XP = Level 3
        1000 => 4,   // 1000 XP = Level 4
        2500 => 5,   // 2500 XP = Level 5
        5000 => 6,   // 5000 XP = Level 6
        10000 => 7,  // 10000 XP = Level 7
        25000 => 8,  // 25000 XP = Level 8
        50000 => 9,  // 50000 XP = Level 9
        100000 => 10, // 100000 XP = Level 10
    ],

];

// old ones
//return [
//    // ── Easy starters ────────────────────────────────────────────────
//    'first-upload' => [
//        'name' => 'First Upload',
//        'xp'   => 1,
//        'icon' => '🍼',
//        'when' => 'stats.photosTotal == 1',
//    ],
//
//    // ── Object-based ─────────────────────────────────────────────────
//    'plastic-slayer' => [
//        'name' => 'Plastic Slayer',
//        'xp'   => 69,
//        'icon' => '🥤',
//        'when' => 'hasObject("plastic_bottle", 69)',
//    ],
//
//    // ── Streak ───────────────────────────────────────────────────────
//    'marathon-uploader' => [
//        'name' => '7-Day Streak',
//        'xp'   => 7,
//        'icon' => '🔥',
//        'when' => 'stats.currentStreak >= 7',
//    ],
//
//    // ── Cumulative (Redis) ───────────────────────────────────────────
//    'pickup-artist' => [
//        'name' => 'Pickup Artist',
//        'xp'   => 420,
//        'icon' => '🏆',
//        'when' => 'objectQty("plastic_bottle") >= 420',
//    ],
//
//    // ── Dynamic object-count milestones ────────────────────────────
//    'country‑pioneer' => [
//        'name' => 'Country Pioneer',
//        'xp'   => 69,
//        'icon' => '🗺️',
//        'when' => 'isFirstInCountry()',
//    ],
//
//    // ── Upload-count milestones ─────────────────────────────────
//    'streak‑69' => [
//        'name' => '69‑day streak',
//        'xp'   => 690,
//        'icon' => '🔥',
//        'when' => 'streak() >= 69',
//    ],
//
//    // ── Time-based ─────────────────────────────────────────────
//    'sunrise‑snapper' => [
//        'name' => 'Sunrise Snapper',
//        'xp'   => 50,
//        'icon' => '🌅',
//        'when' => 'timeOfDay() == "morning" && stats.photosTotal == 1',
//    ],
//];
