<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bring `subscriptions` and `subscription_items` up to the shape Laravel Cashier v15 expects.
 *
 * Why: Cashier v15's WebhookController writes to columns (`type`, `stripe_price`, `stripe_product`)
 * that don't exist on the legacy (Cashier v9-era) schema. Every Stripe `customer.subscription.*`
 * webhook fails with "Unknown column 'type' in 'field list'", so subscription state in our DB
 * silently drifts out of sync with Stripe (e.g. past_due never lands).
 *
 * The Cashier upgrade migrations were never run on this project. This migration brings the schema
 * forward in a single, idempotent step and leaves data intact.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── subscriptions ──────────────────────────────────────────────────
        // Rename `name` → `type` (Cashier v10)
        if (Schema::hasColumn('subscriptions', 'name') && ! Schema::hasColumn('subscriptions', 'type')) {
            Schema::table('subscriptions', function (Blueprint $t) {
                $t->renameColumn('name', 'type');
            });
        }

        // Rename `stripe_plan` → `stripe_price` (Cashier v13)
        if (Schema::hasColumn('subscriptions', 'stripe_plan') && ! Schema::hasColumn('subscriptions', 'stripe_price')) {
            Schema::table('subscriptions', function (Blueprint $t) {
                $t->renameColumn('stripe_plan', 'stripe_price');
            });
        }

        // Make stripe_price nullable (Cashier v13 — sub may have multiple items, no top-level price)
        Schema::table('subscriptions', function (Blueprint $t) {
            $t->string('stripe_price')->nullable()->change();
        });

        // Drop legacy `stripe_active` (Cashier v9 era — replaced by stripe_status)
        if (Schema::hasColumn('subscriptions', 'stripe_active')) {
            Schema::table('subscriptions', function (Blueprint $t) {
                $t->dropColumn('stripe_active');
            });
        }

        // Add unique on stripe_id (Cashier expects this; webhook lookups depend on uniqueness).
        // Existence-checked rather than try/catch so genuine errors (e.g. duplicate data)
        // surface loudly instead of being swallowed.
        $subIndexes = collect(DB::select('SHOW INDEX FROM subscriptions'))
            ->pluck('Key_name')->unique()->all();

        if (! in_array('subscriptions_stripe_id_unique', $subIndexes, true)) {
            Schema::table('subscriptions', function (Blueprint $t) {
                $t->unique('stripe_id');
            });
        }

        // ─── subscription_items ─────────────────────────────────────────────
        // Drop the legacy unique key that referenced `stripe_plan` (Cashier v15's index is non-unique
        // on (subscription_id, stripe_price); per-row uniqueness is enforced via stripe_id instead).
        $itemsIndexes = collect(DB::select('SHOW INDEX FROM subscription_items'))
            ->pluck('Key_name')->unique()->all();

        if (in_array('subscription_items_subscription_id_stripe_plan_unique', $itemsIndexes, true)) {
            Schema::table('subscription_items', function (Blueprint $t) {
                $t->dropUnique('subscription_items_subscription_id_stripe_plan_unique');
            });
        }

        if (in_array('subscription_items_stripe_id_index', $itemsIndexes, true)) {
            Schema::table('subscription_items', function (Blueprint $t) {
                $t->dropIndex('subscription_items_stripe_id_index');
            });
        }

        // Rename stripe_plan → stripe_price
        if (Schema::hasColumn('subscription_items', 'stripe_plan') && ! Schema::hasColumn('subscription_items', 'stripe_price')) {
            Schema::table('subscription_items', function (Blueprint $t) {
                $t->renameColumn('stripe_plan', 'stripe_price');
            });
        }

        // Add stripe_product (Cashier v13 — Stripe Product id, separate from Price id).
        // Nullable so backfilled rows from old Cashier installs don't violate NOT NULL;
        // Cashier's webhook handler always writes it on the next event, so it self-heals.
        if (! Schema::hasColumn('subscription_items', 'stripe_product')) {
            Schema::table('subscription_items', function (Blueprint $t) {
                $t->string('stripe_product')->nullable()->after('stripe_id');
            });
        }

        // Make quantity nullable (Cashier v13 — metered prices may have null quantity)
        Schema::table('subscription_items', function (Blueprint $t) {
            $t->integer('quantity')->nullable()->change();
        });

        // Re-add Cashier-shape indexes — existence-checked, not try/catch.
        $itemsIndexes = collect(DB::select('SHOW INDEX FROM subscription_items'))
            ->pluck('Key_name')->unique()->all();

        if (! in_array('subscription_items_stripe_id_unique', $itemsIndexes, true)) {
            Schema::table('subscription_items', function (Blueprint $t) {
                $t->unique('stripe_id');
            });
        }

        if (! in_array('subscription_items_subscription_id_stripe_price_index', $itemsIndexes, true)) {
            Schema::table('subscription_items', function (Blueprint $t) {
                $t->index(['subscription_id', 'stripe_price']);
            });
        }

        // ─── users (Cashier v13 payment-method column rename) ───────────────
        // Cashier v15's ManagesPaymentMethods writes $user->pm_type and $user->pm_last_four
        // (renamed from card_brand / card_last_four in Cashier v13). Without this rename,
        // every payment-method update or `customer.updated` webhook fatals the same way the
        // subscriptions schema bug did.
        if (Schema::hasColumn('users', 'card_brand') && ! Schema::hasColumn('users', 'pm_type')) {
            Schema::table('users', function (Blueprint $t) {
                $t->renameColumn('card_brand', 'pm_type');
            });
        }

        if (Schema::hasColumn('users', 'card_last_four') && ! Schema::hasColumn('users', 'pm_last_four')) {
            Schema::table('users', function (Blueprint $t) {
                $t->renameColumn('card_last_four', 'pm_last_four');
            });
        }
    }

    public function down(): void
    {
        // Reverse to legacy shape so a rollback is possible. Data preserved.
        if (Schema::hasColumn('users', 'pm_last_four')) {
            Schema::table('users', function (Blueprint $t) {
                $t->renameColumn('pm_last_four', 'card_last_four');
            });
        }

        if (Schema::hasColumn('users', 'pm_type')) {
            Schema::table('users', function (Blueprint $t) {
                $t->renameColumn('pm_type', 'card_brand');
            });
        }

        Schema::table('subscription_items', function (Blueprint $t) {
            try { $t->dropUnique('subscription_items_stripe_id_unique'); } catch (\Throwable $e) {}
            try { $t->dropIndex('subscription_items_subscription_id_stripe_price_index'); } catch (\Throwable $e) {}
        });

        if (Schema::hasColumn('subscription_items', 'stripe_product')) {
            Schema::table('subscription_items', function (Blueprint $t) {
                $t->dropColumn('stripe_product');
            });
        }

        if (Schema::hasColumn('subscription_items', 'stripe_price')) {
            Schema::table('subscription_items', function (Blueprint $t) {
                $t->renameColumn('stripe_price', 'stripe_plan');
            });
        }

        Schema::table('subscription_items', function (Blueprint $t) {
            $t->integer('quantity')->nullable(false)->change();
            $t->index('stripe_id');
        });

        Schema::table('subscriptions', function (Blueprint $t) {
            try { $t->dropUnique('subscriptions_stripe_id_unique'); } catch (\Throwable $e) {}
        });

        if (! Schema::hasColumn('subscriptions', 'stripe_active')) {
            Schema::table('subscriptions', function (Blueprint $t) {
                $t->unsignedInteger('stripe_active')->default(0);
            });
        }

        if (Schema::hasColumn('subscriptions', 'stripe_price')) {
            Schema::table('subscriptions', function (Blueprint $t) {
                $t->renameColumn('stripe_price', 'stripe_plan');
            });
        }

        if (Schema::hasColumn('subscriptions', 'type')) {
            Schema::table('subscriptions', function (Blueprint $t) {
                $t->renameColumn('type', 'name');
            });
        }
    }
};
