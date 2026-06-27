<?php

namespace Tests\Feature\Email;

use App\Models\Users\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EmailVerificationMigrationTest extends TestCase
{
    public function test_verify_button_sets_email_verified_at_and_dual_writes_verified(): void
    {
        $user = User::factory()->create(['verified' => false, 'email_verified_at' => null]);
        $user->forceFill(['token' => 'verify-token-xyz'])->save();

        $this->get('/confirm/email/verify-token-xyz')->assertRedirect('/?verified=1');

        $user->refresh();
        $this->assertTrue($user->verified);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_backfill_sets_verified_at_to_created_at_for_verified_users(): void
    {
        $created = Carbon::now()->subDays(10)->startOfSecond();
        $user = User::factory()->create([
            'verified' => true, 'email_verified_at' => null, 'created_at' => $created,
        ]);

        $this->artisan('email:backfill-verified-at', ['--apply' => true])->assertSuccessful();

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame($created->toDateTimeString(), $user->email_verified_at->toDateTimeString());
    }

    public function test_backfill_leaves_unverified_users_null(): void
    {
        $user = User::factory()->create(['verified' => false, 'email_verified_at' => null]);

        $this->artisan('email:backfill-verified-at', ['--apply' => true])->assertSuccessful();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_backfill_dry_run_changes_nothing(): void
    {
        $user = User::factory()->create(['verified' => true, 'email_verified_at' => null]);

        $this->artisan('email:backfill-verified-at')->assertSuccessful();

        $this->assertNull($user->fresh()->email_verified_at);
    }
}
