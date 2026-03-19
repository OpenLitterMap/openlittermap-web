<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')
            ->where('key', 'beverages')
            ->update(['key' => 'soft_drinks']);
    }

    public function down(): void
    {
        DB::table('categories')
            ->where('key', 'soft_drinks')
            ->update(['key' => 'beverages']);
    }
};
