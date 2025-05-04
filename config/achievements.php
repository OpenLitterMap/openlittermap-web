<?php
return [

    'milestones' => [
        1, 10, 100, 250, 500, 1000, 1500, 2000, 2500, 3000, 4000, 5000, 10000, 20000, 30000, 50000, 100000,
        200000, 300000, 400000, 500000, 600000, 700000, 800000, 900000, 1000000,
    ],

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

    // ── Dynamic object-count milestones ────────────────────────────
    'country‑pioneer' => [
        'name' => 'Country Pioneer',
        'xp'   => 250,
        'icon' => '🗺️',
        'when' => 'isFirstInCountry()',
    ],

    // ── Upload-count milestones ─────────────────────────────────
    'streak‑7' => [
        'name' => '7‑day streak',
        'xp'   => 70,
        'icon' => '🔥',
        'when' => 'streak() >= 7',
    ],

    // ── Time-based ─────────────────────────────────────────────
    'sunrise‑snapper' => [
        'name' => 'Sunrise Snapper',
        'xp'   => 50,
        'icon' => '🌅',
        'when' => 'timeOfDay() == "morning" && stats.photosTotal == 1',
    ],
];
