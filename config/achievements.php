<?php
return [
    // ── Easy starters ────────────────────────────────────────────────
    'first-upload' => [
        'name' => 'First Upload',
        'xp'   => 25,
        'icon' => '🍼',
        'when' => 'stats.photosTotal == 1',
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
        'when' => 'stats.currentStreak >= 30',
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
