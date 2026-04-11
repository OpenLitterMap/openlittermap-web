<?php

namespace Tests\Feature\Cashier;

use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;
use Tests\TestCase;

/**
 * Regression: production was on the Cashier v9-era schema (subscriptions.name, stripe_plan,
 * stripe_active) and Cashier v15's WebhookController writes `type`, `stripe_price`,
 * `stripe_product` — every customer.subscription.* webhook fatalled with
 * "Unknown column 'type' in 'field list'".
 *
 * These tests pin the post-migration schema by exercising the exact write paths Cashier uses.
 */
class SubscriptionsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_save_subscription_with_cashier_v15_columns()
    {
        $user = User::factory()->create();

        $sub = new Subscription();
        $sub->user_id = $user->id;
        $sub->type = 'default';
        $sub->stripe_id = 'sub_test_' . uniqid();
        $sub->stripe_status = 'past_due';
        $sub->stripe_price = 'price_startup';
        $sub->quantity = 1;
        $sub->save();

        $this->assertDatabaseHas('subscriptions', [
            'id' => $sub->id,
            'type' => 'default',
            'stripe_status' => 'past_due',
            'stripe_price' => 'price_startup',
        ]);
    }

    public function test_can_update_subscription_status_like_webhook_does()
    {
        // Mirror Cashier WebhookController::handleCustomerSubscriptionUpdated() line 183
        $user = User::factory()->create();
        $sub = Subscription::create([
            'user_id' => $user->id,
            'type' => 'default',
            'stripe_id' => 'sub_test_' . uniqid(),
            'stripe_status' => 'active',
            'stripe_price' => 'price_pro',
            'quantity' => 1,
        ]);

        // The exact assignment that was fatalling in production
        $sub->type = $sub->type ?? 'default';
        $sub->stripe_price = 'price_startup';
        $sub->quantity = 1;
        $sub->stripe_status = 'past_due';
        $sub->save();

        $this->assertSame('past_due', $sub->fresh()->stripe_status);
        $this->assertSame('price_startup', $sub->fresh()->stripe_price);
    }

    public function test_can_create_subscription_item_with_stripe_product()
    {
        $user = User::factory()->create();
        $sub = Subscription::create([
            'user_id' => $user->id,
            'type' => 'default',
            'stripe_id' => 'sub_test_' . uniqid(),
            'stripe_status' => 'active',
            'stripe_price' => 'price_pro',
            'quantity' => 1,
        ]);

        $item = $sub->items()->updateOrCreate(
            ['stripe_id' => 'si_test_' . uniqid()],
            [
                'stripe_product' => 'prod_test',
                'stripe_price' => 'price_pro',
                'quantity' => 2,
            ]
        );

        $this->assertInstanceOf(SubscriptionItem::class, $item);
        $this->assertDatabaseHas('subscription_items', [
            'id' => $item->id,
            'subscription_id' => $sub->id,
            'stripe_product' => 'prod_test',
            'stripe_price' => 'price_pro',
            'quantity' => 2,
        ]);
    }

    public function test_subscription_item_quantity_is_nullable()
    {
        // Cashier v13+ allows null quantity for metered prices
        $user = User::factory()->create();
        $sub = Subscription::create([
            'user_id' => $user->id,
            'type' => 'default',
            'stripe_id' => 'sub_test_' . uniqid(),
            'stripe_status' => 'active',
            'quantity' => 1,
        ]);

        $item = $sub->items()->create([
            'stripe_id' => 'si_test_' . uniqid(),
            'stripe_product' => 'prod_metered',
            'stripe_price' => 'price_metered',
            'quantity' => null,
        ]);

        $this->assertNull($item->fresh()->quantity);
    }
}
