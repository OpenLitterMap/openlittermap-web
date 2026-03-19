<?php

namespace App\Services;

use App\Models\Users\User;
use RuntimeException;

class UsernameGeneratorService
{
    private const MAX_RETRIES = 10;

    private const MAX_LENGTH = 50;

    private const ADJECTIVES = [
        // environmentally unwell
        'environmentally-unhinged', 'oceanically-concerned', 'plastically-offended',
        'aggressively-recycling', 'violently-eco-friendly', 'microplastic-aware',
        'suspiciously-sustainable', 'ferociously-reusable', 'climate-agitated',

        // personality disorders
        'mildly-feral', 'chronically-committed', 'dangerously-caffeinated',
        'disturbingly-cheerful', 'catastrophically-invested', 'borderline-evangelical',
        'uncomfortably-efficient', 'wildly-overqualified', 'stubborn-beyond-reason',

        // job energy
        'unlicensed', 'lightly-supervised', 'unnecessarily-brave',
        'professionally-nosy', 'recreationally-serious', 'accidentally-legendary',

        // threat aura
        'covert', 'volatile', 'reckless', 'stealth', 'militant',
        'turbo', 'tactical', 'savage', 'relentless', 'untamed',
    ];

    private const RANKS = [
        // bureaucratic
        'assistant', 'deputy', 'senior', 'principal', 'acting',
        'interim', 'regional', 'field', 'associate',

        // ominous
        'supreme', 'shadow', 'arch', 'elite', 'chief',
        'lead', 'rogue', 'secret', 'honorary', 'self-appointed',
    ];

    private const NOUNS = [
        // classic litter roles
        'bin-overlord', 'litter-archaeologist', 'trash-cartographer',
        'waste-wrangler', 'wrapper-diplomat', 'bottle-whisperer',
        'cap-harvester', 'beach-patroller', 'shoreline-auditor',

        // animal negotiation
        'seagull-negotiator', 'turtle-bodyguard', 'whale-lawyer',

        // detective work
        'microplastic-detective', 'butt-detective', 'ghost-net-investigator',
        'plastic-prosecutor', 'gum-hunter', 'lid-sleuth',

        // mystical
        'drain-oracle', 'litter-mystic', 'bin-mystic', 'trash-oracle',
        'compost-sage', 'refuse-prophet',

        // unhinged
        'drain-lurker', 'curb-tyrant', 'crumb-czar', 'filth-czar',
        'crud-knight', 'muck-lord', 'junk-priest', 'litter-yeti',
        'gum-reaper', 'foam-nemesis', 'can-wrangler', 'cup-stalker',
        'trash-bard', 'scrap-hawk', 'debris-boss',
    ];

    /**
     * Weighted patterns — 3-part names are funnier and more common.
     */
    private const PATTERNS = [
        '{adjective}-{rank}-{noun}',
        '{adjective}-{rank}-{noun}',
        '{adjective}-{rank}-{noun}',
        '{adjective}-{noun}',
        '{rank}-{noun}',
    ];

    public static function generate(): string
    {
        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            $username = self::buildUsername();

            if (! User::where('username', $username)->exists()) {
                return $username;
            }
        }

        throw new RuntimeException(
            'Failed to generate a unique username after '.self::MAX_RETRIES.' attempts.'
        );
    }

    private static function buildUsername(): string
    {
        $pattern = self::randomElement(self::PATTERNS);

        $base = strtr($pattern, [
            '{adjective}' => self::randomElement(self::ADJECTIVES),
            '{rank}' => self::randomElement(self::RANKS),
            '{noun}' => self::randomElement(self::NOUNS),
        ]);

        $number = random_int(10, 9999);
        $username = "{$base}-{$number}";

        // If over limit, drop to 2-part pattern
        if (strlen($username) > self::MAX_LENGTH) {
            $adjective = self::randomElement(self::ADJECTIVES);
            $noun = self::randomElement(self::NOUNS);
            $username = "{$adjective}-{$noun}-{$number}";
        }

        // Final safety: truncate base to fit
        if (strlen($username) > self::MAX_LENGTH) {
            $suffix = "-{$number}";
            $base = substr($base, 0, self::MAX_LENGTH - strlen($suffix));
            $base = rtrim($base, '-');
            $username = "{$base}{$suffix}";
        }

        return $username;
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $items
     * @return T
     */
    private static function randomElement(array $items): mixed
    {
        return $items[array_rand($items)];
    }
}
