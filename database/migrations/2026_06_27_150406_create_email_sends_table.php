<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign ledger. One row per (campaign, normalized email); the unique key is
 * the atomic claim lock that makes a bulk send resumable.
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
