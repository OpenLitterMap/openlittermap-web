<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign ledger — the centrepiece. One row per (campaign, normalized email).
 * Makes a bulk send runnable, interruptible, resumable, and auditable: the
 * unique key is the atomic claim lock, and re-runs only dispatch addresses
 * with no `accepted` row.
 *
 * `accepted` = SES accepted the message for sending; we do NOT track inbox
 * delivery. `skipped_duplicate` is intentionally NOT a status — duplicates
 * (same email in users + subscribers) are a report-only counter, since the
 * (campaign, email) unique permits a single row and the user's row already
 * records the dedup.
 *
 * NOTE (row-format): uniq(campaign VARCHAR(64), email VARCHAR(255)) is ~1276
 * bytes on utf8mb4 — well under the 3072-byte limit. Prod is MySQL 8.4.8
 * (innodb_large_prefix / DYNAMIC row format are defaults on 8.x), so there is
 * no row-format concern; local dev is MySQL 9.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_sends', function (Blueprint $table) {
            $table->id();
            $table->string('campaign', 64);                   // e.g. 'update28'
            $table->string('email');                          // normalized
            $table->enum('recipient_type', ['user', 'subscriber']);
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->enum('status', [
                'queued', 'accepted', 'failed',
                'skipped_invalid', 'skipped_suppressed',
            ]);
            $table->string('provider_message_id')->nullable();// SES Message ID if exposed; null OK
            $table->timestamp('accepted_at')->nullable();     // accepted by SES, NOT inbox delivery
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(['campaign', 'email'], 'uniq_campaign_email');
            $table->index(['campaign', 'status'], 'idx_campaign_status');
            $table->index('email', 'idx_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_sends');
    }
};
