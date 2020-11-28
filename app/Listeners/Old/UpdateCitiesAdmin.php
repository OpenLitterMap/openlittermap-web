<?php

namespace App\Listeners;

use App\Models\Litter\Categories\Art as Art;
use App\Models\Litter\Categories\Alcohol as Alcohol;
use App\Models\Litter\Categories\Brand as Brand;
use App\Models\Litter\Categories\Coastal as Coastal;
use App\Models\Litter\Categories\Coffee as Coffee;
use App\Models\Litter\Categories\Dumping as Dumping;
// use App\Models\Litter\Categories\Drugs;
use App\Models\Litter\Categories\Food as Food;
use App\Models\Litter\Categories\Industrial as Industrial;
use App\Models\Litter\Categories\Other as Other;
// use App\Models\Litter\Categories\Pathway as Pathway;
use App\Models\Litter\Categories\SoftDrinks as SoftDrinks;
use App\Models\Litter\Categories\Sanitary as Sanitary;
use App\Models\Litter\Categories\Smoking as Smoking;
use App\Models\Litter\Categories\TrashDog as TrashDog;

use App\Models\Location\City;
use App\Models\Photo;

use App\Events\PhotoVerifiedByAdmin;
use App\Events\DynamicUpdate;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateCitiesAdmin
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * Needs Refactor
     * Instead of looping here,
     * Get the total counts when we Add / Update tags for each category and update the locations from there
     *
     * @param  DynamicUpdate  $event
     * @return void
     */
    public function handle (PhotoVerifiedByAdmin $event)
    {
        $photoId = $event->photoId;
        $photo = Photo::find($photoId);
        $city = City::find($photo->city_id);

        // Get all verified photos for that City
        $cityPhotos = Photo::where([
            ['city_id', $photo->city_id],
            ['verified', '>', 0]
        ])->get();

        // count, update, save
        $city->total_images = $cityPhotos->count();
        $city->save();

        // count, update and save total users
        $users = [];
        foreach ($cityPhotos as $index => $cityPhoto)
        {
            $users[$cityPhoto->user_id] = $cityPhoto->user_id;
        }
        $sizeOfUsers = sizeof($users);
        $city->total_contributors = $sizeOfUsers;
        $city->save();

        if ($photo->smoking_id)
        {
            $smokingPhotos = Photo::where([
                ['city_id', $photo->city_id],
                ['verified', '>', 0],
                ['smoking_id', '!=', null]
            ])->get();

            $smokingTotal = 0;
            $cigaretteTotal = 0;
            foreach ($smokingPhotos as $smokingPhoto)
            {
                $smoking = Smoking::find($smokingPhoto->smoking_id);

                $cigaretteTotal += $smoking->butts;
                  $smokingTotal += $smoking->butts;
                  $smokingTotal += $smoking->lighters;
                  $smokingTotal += $smoking->cigaretteBox;
                  $smokingTotal += $smoking->tobaccoPouch;
                  $smokingTotal += $smoking->skins;
                  $smokingTotal += $smoking->smoking_plastic;
                  $smokingTotal += $smoking->filters;
                  $smokingTotal += $smoking->filterbox;
                  $smokingTotal += $smoking->smokingOther;
                  $smokingTotal += $smoking->vape_oil;
                  $smokingTotal += $smoking->vape_pen;
            }
            $city->total_cigaretteButts = $cigaretteTotal;
            $city->total_smoking = $smokingTotal;
            $city->save();
        }

        if ($photo->food_id)
        {
            $foodPhotos = Photo::where([
                ['city_id', $photo->city_id],
                ['verified', '>', 0],
                ['food_id', '!=', null]
            ])->get();

            $foodTotal = 0;
            foreach ($foodPhotos as $foodPhoto)
            {
                $food = Food::find($foodPhoto->food_id);

                $foodTotal += $food->sweetWrappers;
                $foodTotal += $food->paperFoodPackaging;
                $foodTotal += $food->plasticFoodPackaging;
                $foodTotal += $food->plasticCutlery;
                $foodTotal += $food->crisp_small;
                $foodTotal += $food->crisp_large;
                $foodTotal += $food->styrofoam_plate;
                $foodTotal += $food->napkins;
                $foodTotal += $food->sauce_packet;
                $foodTotal += $food->glass_jar;
                $foodTotal += $food->glass_jar_lid;
                $foodTotal += $food->pizza_box;
                $foodTotal += $food->aluminium_foil;
                $foodTotal += $food->foodOther;
            }
            $city->total_food = $foodTotal;
            $city->save();
        }

        if ($photo->softdrinks_id)
        {
            $softdrinkPhotos = Photo::where([
                ['city_id', $photo->city_id],
                ['verified', '>', 0],
                ['softdrinks_id', '!=', null]
            ])->get();

            $softDrinksTotal = 0;
            $plasticBottleTotal = 0;
            foreach ($softdrinkPhotos as $softdrinkPhoto)
            {
                $softdrink = SoftDrinks::find($softdrinkPhoto->softdrinks_id);

               $plasticBottleTotal  += $softdrink->waterBottle;
                $softDrinksTotal    += $softdrink->waterBottle;
                $plasticBottleTotal += $softdrink->fizzyDrinkBottle;
                $softDrinksTotal    += $softdrink->fizzyDrinkBottle;
                $softDrinksTotal    += $softdrink->tinCan;
                $softDrinksTotal    += $softdrink->bottleLid;
                $softDrinksTotal    += $softdrink->bottleLabel;
                $softDrinksTotal    += $softdrink->sportsDrink;
                $plasticBottleTotal += $softdrink->sportsDrink;
                $softDrinksTotal    += $softdrink->straws;
                $softDrinksTotal    += $softdrink->plastic_cups;
                $softDrinksTotal    += $softdrink->plastic_cup_tops;
                $softDrinksTotal    += $softdrink->milk_bottle;
                $softDrinksTotal    += $softdrink->milk_carton;
                $softDrinksTotal    += $softdrink->plastic_cups;
                $softDrinksTotal    += $softdrink->plastic_cup_tops;
                $softDrinksTotal    += $softdrink->milk_bottle;
                $softDrinksTotal    += $softdrink->milk_carton;
                $softDrinksTotal    += $softdrink->paper_cups;
                $softDrinksTotal    += $softdrink->juice_cartons;
                $softDrinksTotal    += $softdrink->juice_bottles;
                $softDrinksTotal    += $softdrink->juice_packet;
                $softDrinksTotal    += $softdrink->ice_tea_bottles;
                $softDrinksTotal    += $softdrink->ice_tea_can;
                $softDrinksTotal    += $softdrink->energy_can;
                $softDrinksTotal    += $softdrink->styro_cup;
                $softDrinksTotal    += $softdrink->softDrinkOther;
            }
            $city->total_softdrinks = $softDrinksTotal;
            $city->total_plasticBottles = $plasticBottleTotal;
            $city->save();
        }

        if ($photo->alcohol_id)
        {
            $alcoholPhotos = Photo::where([
                ['city_id', $photo->city_id],
                ['verified', '>', 0],
                ['alcohol_id', '!=', null]
            ])->get();

            $alcoholTotal = 0;

            foreach ($alcoholPhotos as $alcoholPhoto)
            {
                $alcohol = Alcohol::find($alcoholPhoto->alcohol_id);

                $alcoholTotal += $alcohol->beerBottle;
                $alcoholTotal += $alcohol->spiritBottle;
                $alcoholTotal += $alcohol->wineBottle;
                $alcoholTotal += $alcohol->beerCan;
                $alcoholTotal += $alcohol->brokenGlass;
                $alcoholTotal += $alcohol->paperCardAlcoholPackaging;
                $alcoholTotal += $alcohol->plasticAlcoholPackaging;
                $alcoholTotal += $alcohol->bottleTops;
                $alcoholTotal += $alcohol->alcoholOther;
                $alcoholTotal += $alcohol->six_pack_rings;
                $alcoholTotal += $alcohol->alcohol_plastic_cups;
            }
            $city->total_alcohol = $alcoholTotal;
            $city->save();
        }

        if ($photo->coffee_id)
        {
            $coffeePhotos = Photo::where([
                ['city_id', $photo->city_id],
                ['verified', '>', 0],
                ['coffee_id', '!=', null]
            ])->get();

            $coffeeTotal = 0;
            foreach($coffeePhotos as $coffeePhoto)
            {
                $coffee = Coffee::find($coffeePhoto->coffee_id);

                $coffeeTotal += $coffee->coffeeCups;
                $coffeeTotal += $coffee->coffeeLids;
                $coffeeTotal += $coffee->coffeeOther;
            }
            $city->total_coffee = $coffeeTotal;
            $city->save();
        }

        if ($photo->dumping_id)
        {
            $dumpingPhotos = Photo::where([
                ['city_id', $photo->city_id],
                ['verified', '>', 0],
                ['dumping_id', '!=', null]
            ])->get();

            $dumpingTotal = 0;
            foreach ($dumpingPhotos as $dumpingPhoto)
            {
                $dumping = Dumping::find($dumpingPhoto->dumping_id);

                $dumpingTotal += $dumping->small;
                $dumpingTotal += $dumping->medium;
                $dumpingTotal += $dumping->large;
            }
            $city->total_dumping = $dumpingTotal;
            $city->save();
        }

        if ($photo->industrial_id)
        {
            $industrialPhotos = Photo::where([
                ['city_id', $photo->city_id],
                ['verified', '>', 0],
                ['industrial_id', '!=', null]
            ])->get();

            $industrialTotal = 0;
            foreach ($industrialPhotos as $industrialPhoto)
            {
                $industrial = Industrial::find($industrialPhoto->industrial_id);

                $industrialTotal += $industrial->oil;
                $industrialTotal += $industrial->chemical;
                $industrialTotal += $industrial->industrial_plastic;
                $industrialTotal += $industrial->bricks;
                $industrialTotal += $industrial->tape;
                $industrialTotal += $industrial->industrial_other;

            }
            $city->total_industrial = $industrialTotal;
            $city->save();
        }

        // if ($photo->drugs_id'{
        //     $drugsPhotos = Photo::where([
        //         ['city_id', $photo->city_id],
        //         ['verified', '>', 0],
        //         ['drugs_id', '!=', null]
        //     ])->get();

        //     $drugsTotal = 0;
        //     $needlesTotal = 0;
        //     foreach($drugsPhotos as $drugPhoto){

        //         $drugs = Drugs::find($drugPhoto['drugs_id']);

        //       $needlesTotal += $drugs['needles'];
        //         $drugsTotal += $drugs['needles'];
        //         $drugsTotal += $drugs['wipes'];
        //         $drugsTotal += $drugs['tops'];
        //         $drugsTotal += $drugs['packaging'];
        //         $drugsTotal += $drugs['waterBottle'];
        //         $drugsTotal += $drugs['spoons'];
        //         $drugsTotal += $drugs['needlebin'];
        //         $drugsTotal += $drugs['barrels'];
        //         $drugsTotal += $drugs['usedtinfoil'];
        //         $drugsTotal += $drugs['fullpackage'];
        //         $drugsTotal += $drugs['baggie'];
        //         $drugsTotal += $drugs['crack_pipes'];
        //         $drugsTotal += $drugs['drugsOther'];
        //     }
        //     $city->total_drugs = $drugsTotal;
        //     $city->total_needles = $drugsTotal;
        //     $city->save();
        // }

        if ($photo->sanitary_id)
        {
            $sanitaryPhotos = Photo::where([
                ['city_id', $photo->city_id],
                ['verified', '>', 0],
                ['sanitary_id', '!=', null]
            ])->get();

            $sanitaryTotal = 0;
            foreach ($sanitaryPhotos as $sanitaryPhoto)
            {
                $sanitary = Sanitary::find($sanitaryPhoto->sanitary_id);

                $sanitaryTotal += $sanitary->gloves;
                $sanitaryTotal += $sanitary->facemask;
                $sanitaryTotal += $sanitary->condoms;
                $sanitaryTotal += $sanitary->nappies;
                $sanitaryTotal += $sanitary->menstral;
                $sanitaryTotal += $sanitary->deodorant;
                $sanitaryTotal += $sanitary->ear_swabs;
                $sanitaryTotal += $sanitary->tooth_pick;
                $sanitaryTotal += $sanitary->tooth_brush;
                $sanitaryTotal += $sanitary->sanitaryOther;
            }
            $city->total_sanitary = $sanitaryTotal;
            $city->save();
        }

        if ($photo->other_id)
        {
            $otherPhotos = Photo::where([
                ['city_id', $photo->city_id],
                ['verified', '>', 0],
                ['other_id', '!=', null]
            ])->get();

            $otherTotal = 0;
            foreach ($otherPhotos as $otherPhoto)
            {
                $other = Other::find($otherPhoto->other_id);

                $otherTotal += $other->dogshit;
                $otherTotal += $other->plastic;
                $otherTotal += $other->dump;
                $otherTotal += $other->metal;
                $otherTotal += $other->plastic_bags;
                $otherTotal += $other->election_posters;
                $otherTotal += $other->forsale_posters;
                $otherTotal += $other->books;
                $otherTotal += $other->magazines;
                $otherTotal += $other->paper;
                $otherTotal += $other->stationary;
                $otherTotal += $other->washing_up;
                $otherTotal += $other->hair_tie;
                $otherTotal += $other->ear_plugs;
                $otherTotal += $other->batteries;
                $otherTotal += $other->elec_small;
                $otherTotal += $other->elec_large;
                $otherTotal += $other->other;
                $otherTotal += $other->random_litter;
                $otherTotal += $other->bags_litter;
                $otherTotal += $other->cable_tie;
                $otherTotal += $other->tyre;
                $otherTotal += $other->overflowing_bins;
            }
            $city->total_other = $otherTotal;
            $city->save();
        }

        // if ($photo->pathway_id']{
        //     $pathwayPhotos = Photo::where([
        //         ['city_id', $photo->city_id],
        //         ['verified', '>', 0],
        //         ['pathway_id', '!=', null]
        //     ])->get();

        //     $pathwayTotal = 0;
        //     foreach($pathwayPhotos as $pathwayPhoto) {
        //         $pathway = Pathway::find($pathwayPhoto['pathway_id']);

        //         $pathwayTotal += $pathway['gutter'];
        //         $pathwayTotal += $pathway['gutter_long'];
        //         $pathwayTotal += $pathway['kerb_hole_small'];
        //         $pathwayTotal += $pathway['kerb_hole_large'];
        //         $pathwayTotal += $pathway['pathwayOther'];
        //     }
        //     $city->total_pathways = $pathwayTotal;
        //     $city->save();
        // }

        if ($photo->coastal_id)
        {
            $coastalPhotos = Photo::where([
                ['city_id', $photo->city_id],
                ['verified', '>', 0],
                ['coastal_id', '!=', null]
            ])->get();

            $coastalTotal = 0;
            foreach ($coastalPhotos as $coastalPhoto)
            {
                $coastal = Coastal::find($coastalPhoto->coastal_id);

                $coastalTotal += $coastal->microplastics;
                $coastalTotal += $coastal->mediumplastics;
                $coastalTotal += $coastal->macroplastics;
                $coastalTotal += $coastal->rope_small;
                $coastalTotal += $coastal->rope_medium;
                $coastalTotal += $coastal->rope_large;
                $coastalTotal += $coastal->fishing_gear_nets;
                $coastalTotal += $coastal->buoys;
                $coastalTotal += $coastal->degraded_plasticbottle;
                $coastalTotal += $coastal->degraded_plasticbag;
                $coastalTotal += $coastal->degraded_straws;
                $coastalTotal += $coastal->degraded_lighters;
                $coastalTotal += $coastal->baloons;
                $coastalTotal += $coastal->lego;
                $coastalTotal += $coastal->shotgun_cartridges;
                $coastalTotal += $coastal->styro_small;
                $coastalTotal += $coastal->styro_medium;
                $coastalTotal += $coastal->styro_large;
                $coastalTotal += $coastal->coastal_other;
            }
            $city->total_coastal = $coastalTotal;
            $city->save();
        }

        if ($photo->art_id)
        {
            $artPhotos = Photo::where([
                ['city_id', $photo->city_id],
                ['verified', '>', 0],
                ['art_id', '!=', null]
            ])->get();

            $artTotal = 0;
            foreach ($artPhotos as $artPhoto)
            {
                $art = Art::find($artPhoto->art_id);
                $artTotal += $art->item;
            }
            $city->total_art = $artTotal;
            $city->save();
        }

        if ($photo->brands_id)
        {
            $brandsPhotos = Photo::where([
                ['city_id', $photo->city_id],
                ['verified', '>', 0],
                ['brands_id', '!=', null]
            ])->get();

            $brandsTotal = 0;
            $adidas = 0;
            $amazon = 0;
            $apple = 0;
            $budweiser = 0;
            $camel = 0;
            $coke = 0;
            $colgate = 0;
            $corona = 0;
            $fritolay = 0;
            $gillette = 0;
            $heineken = 0;
            $kellogs = 0;
            $lego = 0;
            $loreal = 0;
            $nescafe = 0;
            $nestle = 0;
            $marlboro = 0;
            $mcdonalds = 0;
            $nike = 0;
            $pepsi = 0;
            $redbull = 0;
            $samsung = 0;
            $subway = 0;
            $starbucks = 0;
            $tayto = 0;

            foreach ($brandsPhotos as $brandPhoto)
            {
                $brand = Brand::find($brandPhoto->brands_id);
                if ($brand->adidas) {
                    $adidas += $brand->adidas;
                    $brandsTotal += $brand->adidas;
                }
                if ($brand->amazon) {
                    $amazon += $brand->amazon;
                    $brandsTotal += $brand->amazon;
                }
                if ($brand->apple) {
                    $apple += $brand->apple;
                    $brandsTotal += $brand->apple;
                }
                if ($brand->budweiser) {
                    $budweiser += $brand->budweiser;
                    $brandsTotal += $brand->budweiser;
                }
                if ($brand->camel) {
                    $camel += $brand->camel;
                    $brandsTotal += $brand->camel;
                }
                if ($brand->coke) {
                    $coke += $brand->coke;
                    $brandsTotal += $brand->coke;
                }
                if ($brand->colgate) {
                    $colgate += $brand->colgate;
                    $brandsTotal += $brand->colgate;
                }
                if ($brand->corona) {
                    $corona += $brand->corona;
                    $brandsTotal += $brand->corona;
                }
                if ($brand->fritolay) {
                    $fritolay += $brand->fritolay;
                    $brandsTotal += $brand->fritolay;
                }
                if ($brand->gillette) {
                    $gillette += $brand->gillette;
                    $brandsTotal += $brand->gillette;
                }
                if ($brand->heineken) {
                    $heineken += $brand->heineken;
                    $brandsTotal += $brand->heineken;
                }
                if ($brand->kellogs){
                    $kellogs += $brand->kellogs;
                    $brandsTotal += $brand->kellogs;
                }
                if ($brand->lego) {
                    $lego += $brand->lego;
                    $brandsTotal += $brand->lego;
                }
                if ($brand->loreal) {
                    $loreal += $brand->loreal;
                    $brandsTotal += $brand->loreal;
                }
                if ($brand->nescafe) {
                    $nescafe += $brand->nescafe;
                    $brandsTotal += $brand->nescafe;
                }
                if ($brand->nestle) {
                    $nestle += $brand->nestle;
                    $brandsTotal += $brand->nestle;
                }
                if ($brand->marlboro) {
                    $marlboro += $brand->marlboro;
                    $brandsTotal += $brand->marlboro;
                }
                if ($brand->mcdonalds) {
                    $mcdonalds += $brand->mcdonalds;
                    $brandsTotal += $brand->mcdonalds;
                }
                if ($brand->nike) {
                    $nike += $brand->nike;
                    $brandsTotal += $brand->nike;
                }
                if ($brand->pepsi) {
                    $pepsi += $brand->pepsi;
                    $brandsTotal += $brand->pepsi;
                }
                if ($brand->redbull) {
                    $redbull += $brand->redbull;
                    $brandsTotal += $brand->redbull;
                }
                if ($brand->samsung) {
                    $samsung += $brand->samsung;
                    $brandsTotal += $brand->samsung;
                }
                if ($brand->subway) {
                    $subway += $brand->subway;
                    $brandsTotal += $brand->subway;
                }
                if ($brand->starbucks) {
                    $starbucks += $brand->starbucks;
                    $brandsTotal += $brand->starbucks;
                }
                if ($brand->tayto) {
                    $tayto += $brand->tayto;
                    $brandsTotal += $brand->tayto;
                }
            }
            $city->total_brands = $brandsTotal;
            $city->save();

            $city->total_adidas = $adidas;
            $city->total_amazon = $amazon;
            $city->total_apple = $apple;
            $city->total_budweiser = $budweiser;
            $city->total_coke = $coke;
            $city->total_colgate = $colgate;
            $city->total_corona = $corona;
            $city->total_fritolay = $fritolay;
            $city->total_gillette = $gillette;
            $city->total_heineken = $heineken;
            $city->total_kellogs = $kellogs;
            $city->total_lego = $lego;
            $city->total_loreal = $loreal;
            $city->total_nescafe = $nescafe;
            $city->total_nestle = $nestle;
            $city->total_marlboro = $marlboro;
            $city->total_mcdonalds = $mcdonalds;
            $city->total_nike = $nike;
            $city->total_pepsi = $pepsi;
            $city->total_redbull = $redbull;
            $city->total_samsung = $samsung;
            $city->total_subway = $subway;
            $city->total_starbucks = $starbucks;
            $city->total_tayto = $tayto;
            $city->save();
        }
    }
}
