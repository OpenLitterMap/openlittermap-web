<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OLM v5: Strip location tables down to identity-only.
 *
 * All aggregates (totals, brands, litter counts, contributors, photos_per_month)
 * now live in the `metrics` table + Redis.
 *
 * After this migration, location tables only contain:
 *   countries: id, country, shortcode, created_by, created_at, updated_at
 *   states:    id, state, country_id, created_by, created_at, updated_at
 *   cities:    id, city, country_id, state_id, created_by, created_at, updated_at
 *   photos:    drops string location columns (country, country_code, county, city, display_name, location, road)
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- COUNTRIES ---
        Schema::table('countries', function (Blueprint $table) {
            // Drop FK on user_id_last_uploaded first
            $table->dropForeign('countries_user_id_last_uploaded_foreign');

            // Drop unique index on slug
            $table->dropUnique('countries_slug_unique');
        });

        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn([
                'countrynameb',
                'countrynamec',
                'user_id_last_uploaded',
                'manual_verify',
                'total_images',
                'total_litter',
                'total_smoking',
                'total_cigaretteButts',
                'total_food',
                'total_softdrinks',
                'total_plasticBottles',
                'total_alcohol',
                'total_coffee',
                'total_drugs',
                'total_needles',
                'total_sanitary',
                'total_other',
                'total_contributors',
                'total_pathways',
                'littercoin_paid',
                'littercoin_issued',
                'total_coastal',
                'total_brands',
                'total_adidas',
                'total_amazon',
                'total_apple',
                'total_budweiser',
                'total_coke',
                'total_colgate',
                'total_corona',
                'total_fritolay',
                'total_gillette',
                'total_heineken',
                'total_kellogs',
                'total_lego',
                'total_loreal',
                'total_nescafe',
                'total_nestle',
                'total_marlboro',
                'total_mcdonalds',
                'total_nike',
                'total_pepsi',
                'total_redbull',
                'total_samsung',
                'total_subway',
                'total_starbucks',
                'total_tayto',
                'total_applegreen',
                'total_avoca',
                'total_bewleys',
                'total_brambles',
                'total_butlers',
                'total_cafe_nero',
                'total_centra',
                'total_costa',
                'total_esquires',
                'total_frank_and_honest',
                'total_insomnia',
                'total_obriens',
                'total_lolly_and_cookes',
                'total_supermacs',
                'total_wilde_and_greene',
                'slug',
                'total_art',
                'total_dumping',
                'total_industrial',
                'photos_per_month',
                'total_dogshit',
            ]);
        });

        // --- STATES ---
        Schema::table('states', function (Blueprint $table) {
            $table->dropForeign('states_user_id_last_uploaded_foreign');
        });

        Schema::table('states', function (Blueprint $table) {
            $table->dropColumn([
                'statenameb',
                'manual_verify',
                'user_id_last_uploaded',
                'total_images',
                'total_litter',
                'total_smoking',
                'total_cigaretteButts',
                'total_food',
                'total_softdrinks',
                'total_plasticBottles',
                'total_alcohol',
                'total_coffee',
                'total_drugs',
                'total_needles',
                'total_sanitary',
                'total_other',
                'total_coastal',
                'total_contributors',
                'total_pathways',
                'littercoin_paid',
                'littercoin_issued',
                'total_brands',
                'total_adidas',
                'total_amazon',
                'total_apple',
                'total_budweiser',
                'total_coke',
                'total_colgate',
                'total_corona',
                'total_fritolay',
                'total_gillette',
                'total_heineken',
                'total_kellogs',
                'total_lego',
                'total_loreal',
                'total_nescafe',
                'total_nestle',
                'total_marlboro',
                'total_mcdonalds',
                'total_nike',
                'total_pepsi',
                'total_redbull',
                'total_samsung',
                'total_subway',
                'total_starbucks',
                'total_tayto',
                'total_applegreen',
                'total_avoca',
                'total_bewleys',
                'total_brambles',
                'total_butlers',
                'total_cafe_nero',
                'total_centra',
                'total_costa',
                'total_esquires',
                'total_frank_and_honest',
                'total_insomnia',
                'total_obriens',
                'total_lolly_and_cookes',
                'total_supermacs',
                'total_wilde_and_greene',
                'total_art',
                'total_dumping',
                'total_industrial',
                'photos_per_month',
                'total_dogshit',
            ]);
        });

        // --- CITIES ---
        Schema::table('cities', function (Blueprint $table) {
            $table->dropForeign('cities_user_id_last_uploaded_foreign');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn([
                'user_id_last_uploaded',
                'total_images',
                'total_litter',
                'total_smoking',
                'total_cigaretteButts',
                'total_food',
                'total_softdrinks',
                'total_plasticBottles',
                'total_alcohol',
                'total_coffee',
                'total_drugs',
                'total_needles',
                'total_sanitary',
                'total_other',
                'total_contributors',
                'total_coastal',
                'total_pathways',
                'manual_verify',
                'total_art',
                'littercoin_paid',
                'littercoin_issued',
                'total_brands',
                'total_adidas',
                'total_amazon',
                'total_apple',
                'total_budweiser',
                'total_coke',
                'total_colgate',
                'total_corona',
                'total_fritolay',
                'total_gillette',
                'total_heineken',
                'total_kellogs',
                'total_lego',
                'total_loreal',
                'total_nescafe',
                'total_nestle',
                'total_marlboro',
                'total_mcdonalds',
                'total_nike',
                'total_pepsi',
                'total_redbull',
                'total_samsung',
                'total_subway',
                'total_starbucks',
                'total_tayto',
                'total_applegreen',
                'total_avoca',
                'total_bewleys',
                'total_brambles',
                'total_butlers',
                'total_cafe_nero',
                'total_centra',
                'total_costa',
                'total_esquires',
                'total_frank_and_honest',
                'total_insomnia',
                'total_obriens',
                'total_lolly_and_cookes',
                'total_supermacs',
                'total_wilde_and_greene',
                'total_dumping',
                'total_industrial',
                'photos_per_month',
                'total_dogshit',
            ]);
        });

        // --- PHOTOS ---
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn([
                'country',
                'country_code',
                'county',
                'city',
                'display_name',
                'location',
                'road',
            ]);
        });
    }

    public function down(): void
    {
        // --- PHOTOS ---
        Schema::table('photos', function (Blueprint $table) {
            $table->string('country')->nullable();
            $table->string('country_code')->nullable();
            $table->string('county')->nullable();
            $table->string('city')->nullable();
            $table->string('display_name')->nullable();
            $table->string('location')->nullable();
            $table->string('road')->nullable();
        });

        // --- COUNTRIES ---
        Schema::table('countries', function (Blueprint $table) {
            $table->string('countrynameb')->nullable();
            $table->string('countrynamec')->nullable();
            $table->integer('user_id_last_uploaded')->unsigned()->nullable();
            $table->tinyInteger('manual_verify')->default(0);
            $table->integer('total_images')->unsigned()->default(1);
            $table->bigInteger('total_litter')->unsigned()->default(0);
            $table->integer('total_smoking')->unsigned()->nullable();
            $table->integer('total_cigaretteButts')->unsigned()->nullable();
            $table->integer('total_food')->unsigned()->nullable();
            $table->integer('total_softdrinks')->unsigned()->nullable();
            $table->integer('total_plasticBottles')->unsigned()->nullable();
            $table->integer('total_alcohol')->unsigned()->nullable();
            $table->integer('total_coffee')->unsigned()->nullable();
            $table->integer('total_drugs')->unsigned()->nullable();
            $table->integer('total_needles')->unsigned()->nullable();
            $table->integer('total_sanitary')->unsigned()->nullable();
            $table->integer('total_other')->unsigned()->nullable();
            $table->integer('total_contributors')->unsigned()->default(1);
            $table->integer('total_pathways')->unsigned()->nullable();
            $table->tinyInteger('littercoin_paid')->default(0);
            $table->tinyInteger('littercoin_issued')->unsigned()->default(0);
            $table->integer('total_coastal')->unsigned()->nullable();
            $table->integer('total_brands')->unsigned()->nullable();
            $table->integer('total_adidas')->unsigned()->nullable();
            $table->integer('total_amazon')->unsigned()->nullable();
            $table->integer('total_apple')->unsigned()->nullable();
            $table->integer('total_budweiser')->unsigned()->nullable();
            $table->integer('total_coke')->unsigned()->nullable();
            $table->integer('total_colgate')->unsigned()->nullable();
            $table->integer('total_corona')->unsigned()->nullable();
            $table->integer('total_fritolay')->unsigned()->nullable();
            $table->integer('total_gillette')->unsigned()->nullable();
            $table->integer('total_heineken')->unsigned()->nullable();
            $table->integer('total_kellogs')->unsigned()->nullable();
            $table->integer('total_lego')->unsigned()->nullable();
            $table->integer('total_loreal')->unsigned()->nullable();
            $table->integer('total_nescafe')->unsigned()->nullable();
            $table->integer('total_nestle')->unsigned()->nullable();
            $table->integer('total_marlboro')->unsigned()->nullable();
            $table->integer('total_mcdonalds')->unsigned()->nullable();
            $table->integer('total_nike')->unsigned()->nullable();
            $table->integer('total_pepsi')->unsigned()->nullable();
            $table->integer('total_redbull')->unsigned()->nullable();
            $table->integer('total_samsung')->unsigned()->nullable();
            $table->integer('total_subway')->unsigned()->nullable();
            $table->integer('total_starbucks')->unsigned()->nullable();
            $table->integer('total_tayto')->unsigned()->nullable();
            $table->integer('total_applegreen')->unsigned()->nullable();
            $table->integer('total_avoca')->unsigned()->nullable();
            $table->integer('total_bewleys')->unsigned()->nullable();
            $table->integer('total_brambles')->unsigned()->nullable();
            $table->integer('total_butlers')->unsigned()->nullable();
            $table->integer('total_cafe_nero')->unsigned()->nullable();
            $table->integer('total_centra')->unsigned()->nullable();
            $table->integer('total_costa')->unsigned()->nullable();
            $table->integer('total_esquires')->unsigned()->nullable();
            $table->integer('total_frank_and_honest')->unsigned()->nullable();
            $table->integer('total_insomnia')->unsigned()->nullable();
            $table->integer('total_obriens')->unsigned()->nullable();
            $table->integer('total_lolly_and_cookes')->unsigned()->nullable();
            $table->integer('total_supermacs')->unsigned()->nullable();
            $table->integer('total_wilde_and_greene')->unsigned()->nullable();
            $table->string('slug')->nullable()->unique('countries_slug_unique');
            $table->integer('total_art')->unsigned()->nullable();
            $table->integer('total_dumping')->unsigned()->nullable();
            $table->integer('total_industrial')->unsigned()->nullable();
            $table->text('photos_per_month')->nullable();
            $table->bigInteger('total_dogshit')->unsigned()->nullable();

            $table->foreign('user_id_last_uploaded', 'countries_user_id_last_uploaded_foreign')
                ->references('id')->on('users');
        });

        // --- STATES ---
        Schema::table('states', function (Blueprint $table) {
            $table->string('statenameb')->nullable();
            $table->integer('manual_verify')->default(1);
            $table->integer('user_id_last_uploaded')->unsigned()->nullable();
            $table->integer('total_images')->unsigned()->default(1);
            $table->integer('total_litter')->default(1);
            $table->integer('total_smoking')->unsigned()->nullable();
            $table->integer('total_cigaretteButts')->unsigned()->nullable();
            $table->integer('total_food')->unsigned()->nullable();
            $table->integer('total_softdrinks')->unsigned()->nullable();
            $table->integer('total_plasticBottles')->unsigned()->nullable();
            $table->integer('total_alcohol')->unsigned()->nullable();
            $table->integer('total_coffee')->unsigned()->nullable();
            $table->integer('total_drugs')->unsigned()->nullable();
            $table->integer('total_needles')->unsigned()->nullable();
            $table->integer('total_sanitary')->unsigned()->nullable();
            $table->integer('total_other')->unsigned()->nullable();
            $table->integer('total_coastal')->unsigned()->nullable();
            $table->integer('total_contributors')->unsigned()->default(1);
            $table->integer('total_pathways')->unsigned()->nullable();
            $table->tinyInteger('littercoin_paid')->default(0);
            $table->tinyInteger('littercoin_issued')->unsigned()->default(0);
            $table->integer('total_brands')->unsigned()->nullable();
            $table->integer('total_adidas')->unsigned()->nullable();
            $table->integer('total_amazon')->unsigned()->nullable();
            $table->integer('total_apple')->unsigned()->nullable();
            $table->integer('total_budweiser')->unsigned()->nullable();
            $table->integer('total_coke')->unsigned()->nullable();
            $table->integer('total_colgate')->unsigned()->nullable();
            $table->integer('total_corona')->unsigned()->nullable();
            $table->integer('total_fritolay')->unsigned()->nullable();
            $table->integer('total_gillette')->unsigned()->nullable();
            $table->integer('total_heineken')->unsigned()->nullable();
            $table->integer('total_kellogs')->unsigned()->nullable();
            $table->integer('total_lego')->unsigned()->nullable();
            $table->integer('total_loreal')->unsigned()->nullable();
            $table->integer('total_nescafe')->unsigned()->nullable();
            $table->integer('total_nestle')->unsigned()->nullable();
            $table->integer('total_marlboro')->unsigned()->nullable();
            $table->integer('total_mcdonalds')->unsigned()->nullable();
            $table->integer('total_nike')->unsigned()->nullable();
            $table->integer('total_pepsi')->unsigned()->nullable();
            $table->integer('total_redbull')->unsigned()->nullable();
            $table->integer('total_samsung')->unsigned()->nullable();
            $table->integer('total_subway')->unsigned()->nullable();
            $table->integer('total_starbucks')->unsigned()->nullable();
            $table->integer('total_tayto')->unsigned()->nullable();
            $table->integer('total_applegreen')->unsigned()->nullable();
            $table->integer('total_avoca')->unsigned()->nullable();
            $table->integer('total_bewleys')->unsigned()->nullable();
            $table->integer('total_brambles')->unsigned()->nullable();
            $table->integer('total_butlers')->unsigned()->nullable();
            $table->integer('total_cafe_nero')->unsigned()->nullable();
            $table->integer('total_centra')->unsigned()->nullable();
            $table->integer('total_costa')->unsigned()->nullable();
            $table->integer('total_esquires')->unsigned()->nullable();
            $table->integer('total_frank_and_honest')->unsigned()->nullable();
            $table->integer('total_insomnia')->unsigned()->nullable();
            $table->integer('total_obriens')->unsigned()->nullable();
            $table->integer('total_lolly_and_cookes')->unsigned()->nullable();
            $table->integer('total_supermacs')->unsigned()->nullable();
            $table->integer('total_wilde_and_greene')->unsigned()->nullable();
            $table->integer('total_art')->unsigned()->nullable();
            $table->integer('total_dumping')->unsigned()->nullable();
            $table->integer('total_industrial')->unsigned()->nullable();
            $table->text('photos_per_month')->nullable();
            $table->bigInteger('total_dogshit')->unsigned()->nullable();

            $table->foreign('user_id_last_uploaded', 'states_user_id_last_uploaded_foreign')
                ->references('id')->on('users');
        });

        // --- CITIES ---
        Schema::table('cities', function (Blueprint $table) {
            $table->integer('user_id_last_uploaded')->unsigned()->nullable();
            $table->integer('total_images')->unsigned()->default(1);
            $table->integer('total_litter')->default(1);
            $table->integer('total_smoking')->unsigned()->nullable();
            $table->integer('total_cigaretteButts')->unsigned()->nullable();
            $table->integer('total_food')->unsigned()->nullable();
            $table->integer('total_softdrinks')->unsigned()->nullable();
            $table->integer('total_plasticBottles')->unsigned()->nullable();
            $table->integer('total_alcohol')->unsigned()->nullable();
            $table->integer('total_coffee')->unsigned()->nullable();
            $table->integer('total_drugs')->unsigned()->nullable();
            $table->integer('total_needles')->unsigned()->nullable();
            $table->integer('total_sanitary')->unsigned()->nullable();
            $table->integer('total_other')->unsigned()->nullable();
            $table->integer('total_contributors')->unsigned()->default(1);
            $table->integer('total_coastal')->unsigned()->default(0);
            $table->integer('total_pathways')->unsigned()->nullable();
            $table->tinyInteger('manual_verify')->default(0);
            $table->integer('total_art')->unsigned()->nullable();
            $table->tinyInteger('littercoin_paid')->default(0);
            $table->tinyInteger('littercoin_issued')->unsigned()->default(0);
            $table->integer('total_brands')->unsigned()->nullable();
            $table->integer('total_adidas')->unsigned()->nullable();
            $table->integer('total_amazon')->unsigned()->nullable();
            $table->integer('total_apple')->unsigned()->nullable();
            $table->integer('total_budweiser')->unsigned()->nullable();
            $table->integer('total_coke')->unsigned()->nullable();
            $table->integer('total_colgate')->unsigned()->nullable();
            $table->integer('total_corona')->unsigned()->nullable();
            $table->integer('total_fritolay')->unsigned()->nullable();
            $table->integer('total_gillette')->unsigned()->nullable();
            $table->integer('total_heineken')->unsigned()->nullable();
            $table->integer('total_kellogs')->unsigned()->nullable();
            $table->integer('total_lego')->unsigned()->nullable();
            $table->integer('total_loreal')->unsigned()->nullable();
            $table->integer('total_nescafe')->unsigned()->nullable();
            $table->integer('total_nestle')->unsigned()->nullable();
            $table->integer('total_marlboro')->unsigned()->nullable();
            $table->integer('total_mcdonalds')->unsigned()->nullable();
            $table->integer('total_nike')->unsigned()->nullable();
            $table->integer('total_pepsi')->unsigned()->nullable();
            $table->integer('total_redbull')->unsigned()->nullable();
            $table->integer('total_samsung')->unsigned()->nullable();
            $table->integer('total_subway')->unsigned()->nullable();
            $table->integer('total_starbucks')->unsigned()->nullable();
            $table->integer('total_tayto')->unsigned()->nullable();
            $table->integer('total_applegreen')->unsigned()->nullable();
            $table->integer('total_avoca')->unsigned()->nullable();
            $table->integer('total_bewleys')->unsigned()->nullable();
            $table->integer('total_brambles')->unsigned()->nullable();
            $table->integer('total_butlers')->unsigned()->nullable();
            $table->integer('total_cafe_nero')->unsigned()->nullable();
            $table->integer('total_centra')->unsigned()->nullable();
            $table->integer('total_costa')->unsigned()->nullable();
            $table->integer('total_esquires')->unsigned()->nullable();
            $table->integer('total_frank_and_honest')->unsigned()->nullable();
            $table->integer('total_insomnia')->unsigned()->nullable();
            $table->integer('total_obriens')->unsigned()->nullable();
            $table->integer('total_lolly_and_cookes')->unsigned()->nullable();
            $table->integer('total_supermacs')->unsigned()->nullable();
            $table->integer('total_wilde_and_greene')->unsigned()->nullable();
            $table->integer('total_dumping')->unsigned()->nullable();
            $table->integer('total_industrial')->unsigned()->nullable();
            $table->text('photos_per_month')->nullable();
            $table->bigInteger('total_dogshit')->unsigned()->nullable();

            $table->foreign('user_id_last_uploaded', 'cities_user_id_last_uploaded_foreign')
                ->references('id')->on('users');
        });
    }
};
