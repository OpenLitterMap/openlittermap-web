<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add school/safeguarding columns to teams
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('safeguarding')->default(false)->after('is_trusted');
            $table->string('school_roll_number', 50)->nullable()->after('safeguarding');
            $table->string('contact_email')->nullable()->after('school_roll_number');
            $table->string('academic_year', 20)->nullable()->after('contact_email');
            $table->string('class_group', 100)->nullable()->after('academic_year');
            $table->string('county', 100)->nullable()->after('class_group');
        });

        // 2. Seed the school team type (team_types currently has only 1 row)
        DB::table('team_types')->insert([
            'team' => 'school',
            'price' => 0,
            'description' => 'School team for LitterWeek and environmental education programs',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'safeguarding',
                'school_roll_number',
                'contact_email',
                'academic_year',
                'class_group',
                'county',
            ]);
        });

        DB::table('team_types')->where('team', 'school')->delete();
    }
};
