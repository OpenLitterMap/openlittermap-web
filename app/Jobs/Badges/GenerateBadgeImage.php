<?php

namespace App\Jobs\Badges;

use App\Models\Badges\Badge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI;

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

        $prompt = "Generate a vibrant, clean, and engaging gamification badge icon for OpenLitterMap representing a cleanup award in a {$this->badge->subtype} area. The badge should be in the shape of a shield with a ribbon containing the text '{$this->badge->subtype}'. Use a transparent background.";

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

            Log::info("Badge image generated successfully", ['badge_id' => $this->badge->id, 'path' => $filePath]);
        } else {
            Log::error("Badge image generation failed", ['response' => $response]);
        }
    }
}
