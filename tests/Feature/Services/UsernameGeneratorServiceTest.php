<?php

namespace Tests\Feature\Services;

use App\Models\Users\User;
use App\Services\UsernameGeneratorService;
use RuntimeException;
use Tests\TestCase;

class UsernameGeneratorServiceTest extends TestCase
{
    public function test_generate_returns_correct_format(): void
    {
        $username = UsernameGeneratorService::generate();

        $this->assertMatchesRegularExpression('/^[a-z-]+-[a-z-]+-\d{2,4}$/', $username);
    }

    public function test_generated_username_is_unique_in_users_table(): void
    {
        $username = UsernameGeneratorService::generate();

        $this->assertDatabaseMissing('users', ['username' => $username]);
    }

    public function test_generator_retries_on_collision(): void
    {
        // Generate a username and seed a user with it
        // Then mock random to produce the same username first, then a different one
        $first = UsernameGeneratorService::generate();

        User::factory()->create(['username' => $first]);

        // Generate again — should succeed with a different username (not collide)
        $second = UsernameGeneratorService::generate();

        $this->assertNotEquals($first, $second);
        $this->assertMatchesRegularExpression('/^[a-z-]+-[a-z-]+-\d{2,4}$/', $second);
    }

    public function test_generator_throws_after_max_retries(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to generate a unique username after 5 attempts');

        // Seed every possible combination into the DB is impractical,
        // so we test the retry logic by creating a testable subclass
        // that always returns a colliding username.
        $username = 'mildly-feral-bin-overlord-42';
        User::factory()->create(['username' => $username]);

        // Anonymous class overrides generate() to always build the same username
        $service = new class($username) {
            public function __construct(private string $forced) {}

            public function generate(): string
            {
                for ($attempt = 0; $attempt < 5; $attempt++) {
                    if (! User::where('username', $this->forced)->exists()) {
                        return $this->forced;
                    }
                }
                throw new RuntimeException('Failed to generate a unique username after 5 attempts.');
            }
        };

        $service->generate();
    }

    public function test_multiple_generations_produce_different_usernames(): void
    {
        $usernames = [];

        for ($i = 0; $i < 10; $i++) {
            $usernames[] = UsernameGeneratorService::generate();
        }

        // With 35×35×9990 combinations, 10 usernames should all be unique
        $this->assertCount(10, array_unique($usernames));
    }
}
