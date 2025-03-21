<?php

namespace App\Jobs\Badges;

use App\Models\Badges\Badge;
use App\Events\Images\BadgeCreated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateBadgeImage implements ShouldQueue
{
    use Queueable;

    protected Badge $badge;

    public function __construct(Badge $badge)
    {
        $this->badge = $badge;
    }

    public function handle(): void
    {
        $client = \OpenAI::client(config('services.openai.key'));

        // The background should be transparent but DALLE-3 doesn't support transparent images yet.
        $prompt = "Create a vibrant minimalistic gamification badge representing a cleanup award in a {$this->badge->subtype} geographic area.
        Place relative geographic features in the foreground and background.
        The badge should feature a modern shield design with a flowing ribbon that prominently displays the text '{$this->badge->subtype}' in clear, error-free lettering.";

        $response = $client->images()->create([
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1024',
            'quality' => 'standard',
        ]);

        if (isset($response->data[0]->url)) {
            $imageUrl = $response->data[0]->url;

            // Fetch the generated image
            $imageContent = file_get_contents($imageUrl);

            // Save the image to storage
            $filePath = "badges/{$this->badge->type}-{$this->badge->subtype}.png";
            Storage::disk('public')->put($filePath, $imageContent);

            // Update the Badge model
            $this->badge->update(['filename' => $filePath]);

            event(new BadgeCreated($this->badge));
        } else {
            Log::error("Badge image generation failed", ['response' => $response]);
        }
    }
}
