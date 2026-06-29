<?php

namespace App\Helpers;

/**
 * Dispatches OLMbot posts to every enabled social network. Commands and listeners
 * call Social; each network helper (Twitter, Bluesky) self-gates via its own
 * isEnabled(), so a disabled network is a no-op. Add a network here in two lines —
 * deliberately no registry/plugin layer.
 */
class Social
{
    public static function text(string $text): void
    {
        Twitter::sendTweet($text);
        Bluesky::post($text);
    }

    /**
     * Post a thread to every enabled network. Sums sent/total over enabled
     * networks only, so the callers' `sent < total` partial-failure check stays
     * correct and a no-network environment returns sent=0/total=0.
     *
     * @param  string[]  $messages
     * @return array{first_id: string|null, sent: int, total: int}
     */
    public static function thread(array $messages): array
    {
        $aggregate = ['first_id' => null, 'sent' => 0, 'total' => 0];

        foreach ([Twitter::class, Bluesky::class] as $network) {
            if (! $network::isEnabled()) {
                continue;
            }

            $result = $network === Twitter::class
                ? Twitter::sendThread($messages)
                : Bluesky::thread($messages);

            $aggregate['first_id'] ??= $result['first_id'];
            $aggregate['sent'] += $result['sent'];
            $aggregate['total'] += $result['total'];
        }

        return $aggregate;
    }

    public static function withImage(string $text, string $imagePath): void
    {
        Twitter::sendTweetWithImage($text, $imagePath);
        Bluesky::postWithImage($text, $imagePath);
    }
}
