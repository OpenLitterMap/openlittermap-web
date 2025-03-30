<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('crowdsourced')->default(false)->after('key');
        });

        Schema::table('litter_objects', function (Blueprint $table) {
            $table->boolean('crowdsourced')->default(false)->after('key');
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->boolean('crowdsourced')->default(false)->after('key');
        });

        Schema::table('brandslist', function (Blueprint $table) {
            $table->boolean('crowdsourced')->default(false)->after('key');
        });

        Schema::table('custom_tags_new', function (Blueprint $table) {
            $table->boolean('crowdsourced')->default(false)->after('key');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('categories', 'crowdsourced')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('crowdsourced');
            });
        }

        if (Schema::hasColumn('litter_objects', 'crowdsourced')) {
            Schema::table('litter_objects', function (Blueprint $table) {
                $table->dropColumn('crowdsourced');
            });
        }

        if (Schema::hasColumn('materials', 'crowdsourced')) {
            Schema::table('materials', function (Blueprint $table) {
                $table->dropColumn('crowdsourced');
            });
        }

        if (Schema::hasColumn('brandslist', 'crowdsourced')) {
            Schema::table('brandslist', function (Blueprint $table) {
                $table->dropColumn('crowdsourced');
            });
        }

        if (Schema::hasColumn('custom_tags_new', 'crowdsourced')) {
            Schema::table('custom_tags_new', function (Blueprint $table) {
                $table->dropColumn('crowdsourced');
            });
        }
    }
};
