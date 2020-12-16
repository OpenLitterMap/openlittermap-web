<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameTablesForEasierTranslation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up ()
    {
        Schema::rename('soft_drinks', 'softdrinks');
        Schema::rename('others', 'other');
        Schema::rename('trash_dogs', 'trashdog');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::rename('softdrinks', 'soft_drinks');
        Schema::rename('other', 'others');
        Schema::rename('trashdog', 'trash_dogs');
    }
}
