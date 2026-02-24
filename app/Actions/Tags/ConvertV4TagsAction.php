<?php

namespace App\Actions\Tags;

use App\Actions\Photos\AddCustomTagsToPhotoAction;
use App\Actions\Photos\AddTagsToPhotoAction as OldAddTagsToPhotoAction;
use App\Enums\VerificationStatus;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Photo;
use App\Models\Teams\Team;
use App\Models\Users\User;
use App\Services\Tags\UpdateTagsService;
use Illuminate\Support\Facades\Log;

class ConvertV4TagsAction
{
    public function __construct(
        private OldAddTagsToPhotoAction $oldAddTagsAction,
        private AddCustomTagsToPhotoAction $oldAddCustomTagsAction,
        private UpdateTagsService $updateTagsService,
    ) {}

    /**
     * Convert v4 mobile tag payload to v5 PhotoTags.
     *
     * Reuses the migration pipeline:
     * 1. Old action writes v4 data to category columns
     * 2. UpdateTagsService reads it back, creates v5 PhotoTags + summary + XP
     * 3. Handle verification (trusted → TagsVerifiedByAdmin)
     *
     * @param int   $userId
     * @param int   $photoId
     * @param array $v4Tags     v4 format {categoryKey: {objectKey: quantity}}
     * @param bool  $pickedUp
     * @param array $customTags Optional custom tags
     */
    public function run(int $userId, int $photoId, array $v4Tags, bool $pickedUp, array $customTags = []): void
    {
        $photo = Photo::find($photoId);

        if (! $photo) {
            Log::warning('ConvertV4TagsAction: photo not found', ['photo_id' => $photoId]);

            return;
        }

        // Idempotency: skip if already converted to v5
        if ($photo->migrated_at !== null || $photo->photoTags()->exists()) {
            return;
        }

        // Set remaining before summary generation (affects XP picked_up bonus)
        $photo->remaining = ! $pickedUp;
        $photo->save();

        // Filter to known categories (old action requires matching Photo relationship)
        $knownCategories = array_flip($photo->categories());
        $filteredTags = [];

        foreach ($v4Tags as $categoryKey => $items) {
            if (isset($knownCategories[$categoryKey])) {
                $filteredTags[$categoryKey] = $items;
            } else {
                Log::warning("ConvertV4TagsAction: unknown category '{$categoryKey}', skipping");
            }
        }

        // Step 1: Write v4 data to category columns
        if (! empty($filteredTags)) {
            $this->oldAddTagsAction->run($photo, $filteredTags);
        }

        if (! empty($customTags)) {
            $this->oldAddCustomTagsAction->run($photo, $customTags);
        }

        // Step 2: Convert to v5 (PhotoTags + summary + XP) via migration pipeline
        $photo->refresh();
        $this->updateTagsService->updateTags($photo);

        // Step 3: Handle verification
        $photo->refresh();
        $this->updateVerification($userId, $photo);
    }

    /**
     * Set verification status and dispatch event if user is trusted.
     */
    protected function updateVerification(int $userId, Photo $photo): void
    {
        $user = User::find($userId);

        if ($user->verification_required) {
            $photo->verification = 0.1;

            if ($photo->team_id) {
                $team = Team::find($photo->team_id);

                if ($team && $team->isSchool()) {
                    $photo->verified = VerificationStatus::VERIFIED->value;
                }
            }
        } else {
            $photo->verification = 1;
            $photo->verified = VerificationStatus::ADMIN_APPROVED->value;

            event(new TagsVerifiedByAdmin(
                $photo->id,
                $photo->user_id,
                $photo->country_id,
                $photo->state_id,
                $photo->city_id,
                $photo->team_id
            ));
        }

        $photo->save();
    }
}
