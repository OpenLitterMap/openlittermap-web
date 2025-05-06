<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['daily','weekly','monthly','yearly'] as $grain) {
            Schema::create("photo_metrics_{$grain}", function (Blueprint $t) use ($grain) {
                $t->enum('location_type',['global','country','state','city']);
                $t->unsignedBigInteger('location_id')->default(0);   // 0 = global

                match ($grain) {
                    'daily'   => $t->date('day'),
                    'weekly'  => [$t->year('year'), $t->unsignedTinyInteger('iso_week')],
                    'monthly' => [$t->year('year'), $t->tinyInteger('month')],
                    'yearly'  => $t->year('year'),
                };

                $t->unsignedInteger('uploads')->default(0);
                $t->unsignedInteger('tags_total')->default(0);
                $t->timestamps();

                $pk = match ($grain) {
                    'daily'   => ['location_type','location_id','day'],
                    'weekly'  => ['location_type','location_id','year','iso_week'],
                    'monthly' => ['location_type','location_id','year','month'],
                    'yearly'  => ['location_type','location_id','year'],
                };
                $t->primary($pk);
            });
        }
    }

    public function down(): void
    {
        foreach (['daily','weekly','monthly','yearly'] as $grain) {
            Schema::dropIfExists("photo_metrics_{$grain}");
        }
    }
};
