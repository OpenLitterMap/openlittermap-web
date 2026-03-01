<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->unsignedBigInteger('participant_id')->nullable()->after('team_id');
            $table->foreign('participant_id')->references('id')->on('participants')->onDelete('set null');
            $table->index(['team_id', 'participant_id'], 'photos_team_participant_idx');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropForeign(['participant_id']);
            $table->dropIndex('photos_team_participant_idx');
            $table->dropColumn('participant_id');
        });
    }
};
