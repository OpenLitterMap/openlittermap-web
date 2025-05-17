<?php
return [
    // ── Easy starters ────────────────────────────────────────────────
    'first-upload' => [
        'name' => 'First Upload',
        'xp'   => 1,
        'icon' => '🍼',
        'when' => 'stats.photosTotal == 1',
    ],

    // ── Object-based ─────────────────────────────────────────────────
    'plastic-slayer' => [
        'name' => 'Plastic Slayer',
        'xp'   => 69,
        'icon' => '🥤',
        'when' => 'hasObject("plastic_bottle", 69)',
    ],

    // ── Streak ───────────────────────────────────────────────────────
    'marathon-uploader' => [
        'name' => '7-Day Streak',
        'xp'   => 7,
        'icon' => '🔥',
        'when' => 'stats.currentStreak >= 7',
    ],

    // ── Cumulative (Redis) ───────────────────────────────────────────
    'pickup-artist' => [
        'name' => 'Pickup Artist',
        'xp'   => 420,
        'icon' => '🏆',
        'when' => 'objectQty("plastic_bottle") >= 420',
    ],

    // ── Dynamic object-count milestones ────────────────────────────
    'country‑pioneer' => [
        'name' => 'Country Pioneer',
        'xp'   => 69,
        'icon' => '🗺️',
        'when' => 'isFirstInCountry()',
    ],

    // ── Upload-count milestones ─────────────────────────────────
    'streak‑69' => [
        'name' => '69‑day streak',
        'xp'   => 690,
        'icon' => '🔥',
        'when' => 'streak() >= 69',
    ],

    // ── Time-based ─────────────────────────────────────────────
    'sunrise‑snapper' => [
        'name' => 'Sunrise Snapper',
        'xp'   => 50,
        'icon' => '🌅',
        'when' => 'timeOfDay() == "morning" && stats.photosTotal == 1',
    ],
];
