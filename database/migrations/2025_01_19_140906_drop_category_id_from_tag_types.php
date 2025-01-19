<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tag_types', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropUnique(['key', 'category_id']);
            $table->dropColumn('category_id');
            $table->primary(['key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tag_types', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id');
            $table->foreign('category_id')->references('id')->on('categories');
            $table->dropPrimary(['key']);
            $table->primary(['key', 'category_id']);
        });
    }
};
