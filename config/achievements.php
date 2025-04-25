<?php
return [

    // ── Easy starters ────────────────────────────────────────────────
    'first-upload' => [
        'name' => 'First Upload',
        'xp'   => 25,
        'icon' => '🍼',
        'when' => 'stats.photos_total == 1',
    ],

    // ── Object-based ─────────────────────────────────────────────────
    'plastic-slayer' => [
        'name' => 'Plastic Slayer',
        'xp'   => 100,
        'icon' => '🥤',
        'when' => 'hasObject("plastic_bottle", 10)',
    ],

    // ── Streak ───────────────────────────────────────────────────────
    'marathon-uploader' => [
        'name' => '30-Day Streak',
        'xp'   => 300,
        'icon' => '🔥',
        'when' => 'stats.current_streak >= 30',
    ],

    // ── Cumulative (Redis) ───────────────────────────────────────────
    'bottle-legend' => [
        'name' => 'Bottle Legend',
        'xp'   => 1_000,
        'icon' => '🏆',
        'when' => 'objectQty("plastic_bottle") >= 1000',
    ],
];
