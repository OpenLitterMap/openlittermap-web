<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dirty_teams', function (Blueprint $table) {
            $table->unsignedInteger('team_id');
            $table->timestamp('changed_at')->useCurrent();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->primary('team_id');
            $table->index(['changed_at', 'attempts']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dirty_teams');
    }
};
