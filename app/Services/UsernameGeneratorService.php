<?php

namespace App\Services;

use App\Models\Users\User;
use RuntimeException;

class UsernameGeneratorService
{
    private const MAX_RETRIES = 5;

    private const ADJECTIVES = [
        'violently-enthusiastic', 'aggressively-hydrated', 'emotionally-overprepared',
        'deeply-unsettled', 'mildly-feral', 'chronically-committed',
        'irrationally-motivated', 'profoundly-bored', 'environmentally-triggered',
        'dangerously-caffeinated', 'excessively-alert', 'spiritually-activated',
        'socially-unnecessary', 'aggressively-helpful', 'suspiciously-vigilant',
        'inexplicably-determined', 'disturbingly-cheerful', 'unreasonably-passionate',
        'legally-distinct', 'professionally-nosy', 'catastrophically-invested',
        'excessively-wholesome', 'morally-exhausted', 'stubborn-beyond-reason',
        'permanently-busy', 'recreationally-serious', 'accidentally-legendary',
        'uncomfortably-efficient', 'wildly-overqualified', 'borderline-evangelical',
        'environmentally-unhinged', 'existentially-concerned', 'unlicensed',
        'lightly-supervised', 'unnecessarily-brave',
    ];

    private const NOUNS = [
        'bin-overlord', 'crumb-interrogator', 'bottle-whisperer', 'cap-harvester',
        'gum-evangelist', 'wrapper-warden', 'drain-lurker', 'crisp-packet-historian',
        'pavement-enforcer', 'roundabout-oracle', 'curb-tyrant', 'seagull-negotiator',
        'lid-archaeologist', 'debris-wrangler', 'litter-interventionist',
        'microplastic-detective', 'waste-summoner', 'compost-influencer',
        'drain-purifier', 'refuse-enthusiast', 'bottle-apologist', 'gum-hunter',
        'street-diplomat', 'plastic-prosecutor', 'crumb-collector-general',
        'wrapper-therapist', 'bin-strategist', 'cap-overseer', 'drain-warden',
        'civic-disruptor', 'litter-mystic', 'pavement-auditor', 'beach-activator',
        'rubbish-savant', 'field-chaos-coordinator',
    ];

    public static function generate(): string
    {
        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            $username = self::buildUsername();

            if (! User::where('username', $username)->exists()) {
                return $username;
            }
        }

        throw new RuntimeException('Failed to generate a unique username after '.self::MAX_RETRIES.' attempts.');
    }

    private static function buildUsername(): string
    {
        $adjective = self::ADJECTIVES[array_rand(self::ADJECTIVES)];
        $noun = self::NOUNS[array_rand(self::NOUNS)];
        $number = random_int(10, 9999);

        return "{$adjective}-{$noun}-{$number}";
    }
}
