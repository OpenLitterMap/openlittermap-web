<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('team_id');
            $table->unsignedSmallInteger('slot_number');
            $table->string('display_name', 100);
            $table->char('session_token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->unique(['team_id', 'slot_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
