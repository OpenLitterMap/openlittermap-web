<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log + idempotency guard for SES notifications delivered via SNS.
 * Only verified Bounce/Complaint recipient events are stored. Raw payloads
 * live here ONLY. The (sns_id, email) unique key makes duplicate SNS
 * deliveries harmless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_events', function (Blueprint $table) {
            $table->id();
            $table->string('sns_id');                 // SNS MessageId
            $table->string('email');                  // normalized
            $table->string('type', 32);               // Bounce | Complaint
            $table->string('subtype', 64)->nullable();// Permanent | Transient | Undetermined | complaint type
            $table->json('payload')->nullable();      // raw event, kept here ONLY
            $table->timestamp('created_at')->nullable();

            $table->unique(['sns_id', 'email'], 'uniq_event');
            $table->index(['email', 'type'], 'idx_email_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_events');
    }
};
