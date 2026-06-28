<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Source of truth for undeliverable addresses. One row per normalized email.
 * Populated by the SNS webhook (source=ses) and the SES backfill import
 * (source=backfill). Complaint outranks bounce — never downgrade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('email');                                      // normalized: strtolower(trim())
            $table->enum('reason', ['bounced', 'complained', 'manual']);
            $table->enum('source', ['ses', 'backfill', 'manual']);
            $table->timestamp('suppressed_at')->nullable();               // actual event time if known
            $table->timestamps();

            $table->unique('email', 'uniq_email');
            $table->index('reason', 'idx_reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppressions');
    }
};
