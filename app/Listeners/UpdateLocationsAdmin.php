<?php

namespace App\Listeners;

use App\Models\Litter\Categories\Smoking as Smoking;
use App\Models\Litter\Categories\Alcohol as Alcohol;
use App\Models\Litter\Categories\Coffee as Coffee;
use App\Models\Litter\Categories\Food as Food;
use App\Models\Litter\Categories\SoftDrinks as SoftDrinks;
// use App\Models\Litter\Categories\Drugs;
use App\Models\Litter\Categories\Sanitary as Sanitary;
use App\Models\Litter\Categories\Other as Other;
use App\Models\Litter\Categories\Coastal as Coastal;
use App\Models\Litter\Categories\Pathway as Pathway;
use App\Models\Litter\Categories\Art as Art;
use App\Models\Litter\Categories\Brand as Brand;
use App\Models\Litter\Categories\TrashDog as TrashDog;
use App\Models\Litter\Categories\Dumping as Dumping;
use App\Models\Litter\Categories\Industrial as Industrial;

use Illuminate\Support\Facades\Log;

use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Location\City;
use App\Models\Photo;

use App\Events\PhotoVerifiedByAdmin;
use App\Events\DynamicUpdate;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateLocationsAdmin
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
     * @param  DynamicUpdate  $event
     * @return void
     */
    public function handle (PhotoVerifiedByAdmin $event)
    {
        // get the Photo and associated Country
        $photo = Photo::find($event->photoId);
        $country = Country::find($photo->country_id);
        State::find($photo->state_id);
        City::find($photo->city_id);

        // Get all photos for that country that have been verified
        $countryPhotos = Photo::where([
            ['country_id', $photo->country_id],
            ['verified', '>', 0]
        ])->get();

        // count, update, save
        $country->total_images = $countryPhotos->count();
        $country->save();

        // count, update and save total users
        // Todo - table with country.id and user.id
        $users = [];
        foreach ($countryPhotos as $countryPhoto)
        {
            $users[$countryPhoto->user_id] = $countryPhoto->user_id;
        }

        $sizeOfUsers = count($users);

        $country->total_contributors = $sizeOfUsers;
        $country->save();

        /**
         * Update total counts for each category
         * Check the photo for foreign keys, count and update them on the Country
         */
        if ($photo->smoking_id)
        {
            // get all verified photos for that country where smoking id not null
            $smokingPhotos = Photo::where([
                ['country_id', $photo->country_id],
                ['verified', '>', 0],
                ['smoking_id', '!=', null]
            ])->get();

            $smokingTotal = 0;
            $cigaretteTotal = 0;
            // for each of these photos
            foreach ($smokingPhotos as $smokingPhoto)
            {
                $smoking = Smoking::find($smokingPhoto['smoking_id']);

                // count totals
                $cigaretteTotal += $smoking['butts'];
                  $smokingTotal += $smoking['butts'];
                  $smokingTotal += $smoking['lighters'];
                  $smokingTotal += $smoking['cigaretteBox'];
                  $smokingTotal += $smoking['tobaccoPouch'];
                  $smokingTotal += $smoking['skins'];
                  $smokingTotal += $smoking['plastic'];
                  $smokingTotal += $smoking['filters'];
                  $smokingTotal += $smoking['filterbox'];
                  $smokingTotal += $smoking['smokingOther'];
            }

            $country->total_cigaretteButts = $cigaretteTotal;
            $country->total_smoking = $smokingTotal;
            $country->save();
        }

        if ($photo->food_id)
        {
            $foodPhotos = Photo::where([
                ['country_id', $photo->country_id],
                ['verified', '>', 0],
                ['food_id', '!=', null]
            ])->get();

            $foodTotal = 0;
            foreach($foodPhotos as $foodPhoto)
            {
                $food = Food::find($foodPhoto['food_id']);

                $foodTotal += $food['sweetWrappers'];
                $foodTotal += $food['paperFoodPackaging'];
                $foodTotal += $food['plasticFoodPackaging'];
                $foodTotal += $food['plasticCutlery'];
                $foodTotal += $food['crisp_small'];
                $foodTotal += $food['crisp_large'];
                $foodTotal += $food['styrofoam_plate'];
                $foodTotal += $food['napkins'];
                $foodTotal += $food['sauce_packet'];
                $foodTotal += $food['glass_jar'];
                $foodTotal += $food['glass_jar_lid'];
                $foodTotal += $food['foodOther'];
            }

            $country->total_food = $foodTotal;
            $country->save();
        }

        if ($photo->softdrinks_id)
        {
            $softdrinkPhotos = Photo::where([
                ['country_id', $photo->country_id],
                ['verified', '>', 0],
                ['softdrinks_id', '!=', null]
            ])->get();

            $softDrinksTotal = 0;
            $plasticBottleTotal = 0;
            foreach ($softdrinkPhotos as $softdrinkPhoto)
            {
                $softdrink = SoftDrinks::find($softdrinkPhoto['softdrinks_id']);

                $plasticBottleTotal += $softdrink['waterBottle'];
                   $softDrinksTotal += $softdrink['waterBottle'];
                $plasticBottleTotal += $softdrink['fizzyDrinkBottle'];
                   $softDrinksTotal += $softdrink['fizzyDrinkBottle'];
                   $softDrinksTotal += $softdrink['tinCan'];
                   $softDrinksTotal += $softdrink['bottleLid'];
                   $softDrinksTotal += $softdrink['bottleLabel'];
                   $softDrinksTotal += $softdrink['sportsDrink'];
                $plasticBottleTotal += $softdrink['sportsDrink'];
                   $softDrinksTotal += $softdrink['straws'];
                   $softDrinksTotal += $softdrink['plastic_cups'];
                   $softDrinksTotal += $softdrink['plastic_cup_tops'];
                   $softDrinksTotal += $softdrink['milk_bottle'];
                   $softDrinksTotal += $softdrink['milk_carton'];
                   $softDrinksTotal += $softdrink['paper_cups'];
                   $softDrinksTotal += $softdrink['juice_cartons'];
                   $softDrinksTotal += $softdrink['juice_bottles'];
                   $softDrinksTotal += $softdrink['juice_packet'];
                   $softDrinksTotal += $softdrink['ice_tea_bottles'];
                   $softDrinksTotal += $softdrink['ice_tea_can'];
                   $softDrinksTotal += $softdrink['energy_can'];
                   $softDrinksTotal += $softdrink['styro_cup'];
                   $softDrinksTotal += $softdrink['softDrinkOther'];
            }

            $country->total_softdrinks = $softDrinksTotal;
            $country->total_plasticBottles = $plasticBottleTotal;
            $country->save();
        }

        if ($photo->alcohol_id)
        {
            $alcoholPhotos = Photo::where([
                ['country_id', $photo->country_id],
                ['verified', '>', 0],
                ['alcohol_id', '!=', null]
            ])->get();

            $alcoholTotal = 0;

            foreach ($alcoholPhotos as $alcoholPhoto)
            {
                $alcohol = Alcohol::find($alcoholPhoto['alcohol_id']);

                $alcoholTotal += $alcohol['beerBottle'];
                $alcoholTotal += $alcohol['spiritBottle'];
                $alcoholTotal += $alcohol['wineBottle'];
                $alcoholTotal += $alcohol['beerCan'];
                $alcoholTotal += $alcohol['brokenGlass'];
                $alcoholTotal += $alcohol['paperCardAlcoholPackaging'];
                $alcoholTotal += $alcohol['plasticAlcoholPackaging'];
                $alcoholTotal += $alcohol['bottleTops'];
                $alcoholTotal += $alcohol['alcoholOther'];
                $alcoholTotal += $alcohol['six_pack_rings'];
                $alcoholTotal += $alcohol['plastic_cups'];
            }

            $country->total_alcohol = $alcoholTotal;
            $country->save();
        }

        if ($photo->coffee_id)
        {
            $coffeePhotos = Photo::where([
                ['country_id', $photo->country_id],
                ['verified', '>', 0],
                ['coffee_id', '!=', null]
            ])->get();

            $coffeeTotal = 0;
            foreach ($coffeePhotos as $coffeePhoto)
            {
                $coffee = Coffee::find($coffeePhoto['coffee_id']);

                $coffeeTotal += $coffee['coffeeCups'];
                $coffeeTotal += $coffee['coffeeLids'];
                $coffeeTotal += $coffee['coffeeOther'];
            }

            $country->total_coffee = $coffeeTotal;
            $country->save();
        }

        if ($photo->dumping_id)
        {
            $dumpingPhotos = Photo::where([
                ['country_id', $photo->country_id],
                ['verified', '>', 0],
                ['dumping_id', '!=', null]
            ])->get();

            $dumpingTotal = 0;
            foreach ($dumpingPhotos as $dumpingPhoto)
            {
                $dumping = Dumping::find($dumpingPhoto['dumping_id']);

                $dumpingTotal += $dumping['small'];
                $dumpingTotal += $dumping['medium'];
                $dumpingTotal += $dumping['large'];
            }

            $country->total_dumping = $dumpingTotal;
            $country->save();
        }

        if ($photo->industrial_id)
        {
            $industrialPhotos = Photo::where([
                ['country_id', $photo->country_id],
                ['verified', '>', 0],
                ['industrial_id', '!=', null]
            ])->get();

            $industrialTotal = 0;
            foreach ($industrialPhotos as $industrialPhoto)
            {
                $industrial = Industrial::find($industrialPhoto['industrial_id']);

                $industrialTotal += $industrial['oil'];
                $industrialTotal += $industrial['chemical'];
                $industrialTotal += $industrial['industrial_plastic'];
                $industrialTotal += $industrial['bricks'];
                $industrialTotal += $industrial['tape'];
                $industrialTotal += $industrial['industrial_other'];
            }

            $country->total_industrial = $industrialTotal;
            $country->save();
        }

        // if($photo->drugs_id'{
        //     $drugsPhotos = Photo::where([
        //         ['country_id', $photo->country_id],
        //         ['verified', '>', 0],
        //         ['drugs_id', '!=', null]
        //     ])->get();

        //     $drugsTotal = 0;
        //     $needlesTotal = 0;
        //     foreach($drugsPhotos as $drugPhoto){

        //         $drugs = \App\Models\Litter\Categories\Drugs::find($drugPhoto['drugs_id']);

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
        //     $country->total_drugs = $drugsTotal;
        //     $country->total_needles = $drugsTotal;
        //     $country->save();
        // }

        if ($photo->sanitary_id)
        {
            $sanitaryPhotos = Photo::where([
                ['country_id', $photo->country_id],
                ['verified', '>', 0],
                ['sanitary_id', '!=', null]
            ])->get();

            $sanitaryTotal = 0;
            foreach ($sanitaryPhotos as $sanitaryPhoto)
            {
                $sanitary = Sanitary::find($sanitaryPhoto['sanitary_id']);

                $sanitaryTotal += $sanitary['gloves'];
                $sanitaryTotal += $sanitary['facemask'];
                $sanitaryTotal += $sanitary['condoms'];
                $sanitaryTotal += $sanitary['nappies'];
                $sanitaryTotal += $sanitary['menstral'];
                $sanitaryTotal += $sanitary['deodorant'];
                $sanitaryTotal += $sanitary['ear_swabs'];
                $sanitaryTotal += $sanitary['tooth_pick'];
                $sanitaryTotal += $sanitary['tooth_brush'];
                $sanitaryTotal += $sanitary['sanitaryOther'];
            }

            $country->total_sanitary = $sanitaryTotal;
            $country->save();
        }

        if ($photo->other_id)
        {
            $otherPhotos = Photo::where([
                ['country_id', $photo->country_id],
                ['verified', '>', 0],
                ['other_id', '!=', null]
            ])->get();

            $otherTotal = 0;
            foreach ($otherPhotos as $otherPhoto)
            {
                $other = Other::find($otherPhoto['other_id']);

                $otherTotal += $other['dogshit'];
                $otherTotal += $other['plastic'];
                $otherTotal += $other['dump'];
                $otherTotal += $other['metal'];
                $otherTotal += $other['plastic_bags'];
                $otherTotal += $other['election_posters'];
                $otherTotal += $other['forsale_posters'];
                $otherTotal += $other['books'];
                $otherTotal += $other['magazines'];
                $otherTotal += $other['paper'];
                $otherTotal += $other['stationary'];
                $otherTotal += $other['washing_up'];
                $otherTotal += $other['hair_tie'];
                $otherTotal += $other['ear_plugs'];
                $otherTotal += $other['batteries'];
                $otherTotal += $other['elec_small'];
                $otherTotal += $other['elec_large'];
                $otherTotal += $other['other'];
            }

            $country->total_other = $otherTotal;
            $country->save();
        }

        // if($photo->pathway_id']{
        //     $pathwayPhotos = Photo::where([
        //         ['country_id', $photo->country_id],
        //         ['verified', '>', 0],
        //         ['pathway_id', '!=', null]
        //     ])->get();

        //     $pathwayTotal = 0;
        //     foreach($pathwayPhotos as $pathwayPhoto) {
        //         $pathway = \App\Models\Litter\Categories\Pathway::find($pathwayPhoto['pathway_id']);

        //         $pathwayTotal += $pathway['gutter'];
        //         $pathwayTotal += $pathway['gutter_long'];
        //         $pathwayTotal += $pathway['kerb_hole_small'];
        //         $pathwayTotal += $pathway['kerb_hole_large'];
        //         $pathwayTotal += $pathway['pathwayOther'];
        //     }
        //     $country->total_pathways = $pathwayTotal;
        //     $country->save();
        // }

        if ($photo->art_id)
        {
            $artPhotos = Photo::where([
                ['country_id', $photo->country_id],
                ['verified', '>', 0],
                ['art_id', '!=', null]
            ])->get();

            $artTotal = 0;
            foreach ($artPhotos as $artPhoto)
            {
                $pathway = Art::find($artPhoto['art_id']);
                $artTotal += $pathway['item'];
            }

            $country->total_art = $artTotal;
            $country->save();
        }

        if ($photo->coastal_id)
        {
            $coastalPhotos = Photo::where([
                ['country_id', $photo->country_id],
                ['verified', '>', 0],
                ['coastal_id', '!=', null]
            ])->get();

            $coastalTotal = 0;
            foreach($coastalPhotos as $coastalPhoto) {
                $coastal = Coastal::find($coastalPhoto['coastal_id']);
                $coastalTotal += $coastal['microplastics'];
                $coastalTotal += $coastal['mediumplastics'];
                $coastalTotal += $coastal['macroplastics'];
                $coastalTotal += $coastal['rope_small'];
                $coastalTotal += $coastal['rope_medium'];
                $coastalTotal += $coastal['rope_large'];
                $coastalTotal += $coastal['fishing_gear_nets'];
                $coastalTotal += $coastal['buoys'];
                $coastalTotal += $coastal['degraded_plasticbottle'];
                $coastalTotal += $coastal['degraded_plasticbag'];
                $coastalTotal += $coastal['degraded_straws'];
                $coastalTotal += $coastal['degraded_lighters'];
                $coastalTotal += $coastal['baloons'];
                $coastalTotal += $coastal['lego'];
                $coastalTotal += $coastal['shotgun_cartridges'];
                $coastalTotal += $coastal['styro_small'];
                $coastalTotal += $coastal['styro_medium'];
                $coastalTotal += $coastal['styro_large'];
                $coastalTotal += $coastal['coastal_other'];
            }

            $country->total_coastal = $coastalTotal;
            $country->save();
        }

        if ($photo->brands_id)
        {
            $brandsPhotos = Photo::where([
                ['country_id', $photo->country_id],
                ['verified', '>', 0],
                ['brands_id', '!=', null]
            ])->get();

            $brandsTotal = 0;
            $adidas = 0;
            $amazon = 0;
            $apple = 0;
            $applegreen = 0;
            $avoca = 0;
            $bewleys = 0;
            $brambles = 0;
            $budweiser = 0;
            $butlers = 0;
            $cafe_nero = 0;
            $camel = 0;
            $centra = 0;
            $coke = 0;
            $colgate = 0;
            $corona = 0;
            $costa = 0;
            $esquires = 0;
            $frank_and_honest = 0;
            $fritolay = 0;
            $gillette = 0;
            $heineken = 0;
            $insomnia = 0;
            $kellogs = 0;
            $lego = 0;
            $lolly_and_cookes = 0;
            $loreal = 0;
            $nescafe = 0;
            $nestle = 0;
            $marlboro = 0;
            $mcdonalds = 0;
            $nike = 0;
            $obriens = 0;
            $pepsi = 0;
            $redbull = 0;
            $samsung = 0;
            $subway = 0;
            $supermacs = 0;
            $starbucks = 0;
            $tayto = 0;
            $wilde_and_greene = 0;

            foreach($brandsPhotos as $brandPhoto) {
                $brand = Brand::find($brandPhoto['brands_id']);
                if($brand->adidas) {
                    $adidas += $brand->adidas;
                    $brandsTotal += $brand->adidas;
                }

                if($brand->amazon) {
                    $amazon += $brand->amazon;
                    $brandsTotal += $brand->amazon;
                }

                if($brand->apple) {
                    $apple += $brand->apple;
                    $brandsTotal += $brand->apple;
                }

                if($brand->applegreen) {
                    $applegreen += $brand->applegreen;
                    $brandsTotal += $brand->applegreen;
                }

                if($brand->avoca) {
                    $avoca += $brand->avoca;
                    $brandsTotal += $brand->avoca;
                }

                if($brand->bewleys) {
                    $bewleys += $brand->bewleys;
                    $brandsTotal += $brand->bewleys;
                }

                if($brand->brambles) {
                    $brambles += $brand->brambles;
                    $brandsTotal += $brand->brambles;
                }

                if($brand->budweiser) {
                    $budweiser += $brand->budweiser;
                    $brandsTotal += $brand->budweiser;
                }

                if($brand->butlers) {
                    $butlers += $brand->butlers;
                    $brandsTotal += $brand->butlers;
                }

                if($brand->cafe_nero) {
                    $cafe_nero += $brand->cafe_nero;
                    $brandsTotal += $brand->cafe_nero;
                }

                if($brand->camel) {
                    $camel += $brand->camel;
                    $brandsTotal += $brand->camel;
                }

                if($brand->centra) {
                    $centra += $brand->centra;
                    $brandsTotal += $brand->centra;
                }

                if($brand->coke) {
                    $coke += $brand->coke;
                    $brandsTotal += $brand->coke;
                }

                if($brand->colgate) {
                    $colgate += $brand->colgate;
                    $brandsTotal += $brand->colgate;
                }

                if($brand->corona) {
                    $corona += $brand->corona;
                    $brandsTotal += $brand->corona;
                }

                if($brand->costa) {
                    $costa += $brand->costa;
                    $brandsTotal += $brand->costa;
                }

                if($brand->esquires) {
                    $esquires += $brand->esquires;
                    $brandsTotal += $brand->esquires;
                }

                if($brand->frank_and_honest) {
                    $frank_and_honest += $brand->frank_and_honest;
                    $brandsTotal += $brand->frank_and_honest;
                }

                if($brand->fritolay) {
                    $fritolay += $brand->fritolay;
                    $brandsTotal += $brand->fritolay;
                }

                if($brand->gillette) {
                    $gillette += $brand->gillette;
                    $brandsTotal += $brand->gillette;
                }

                if($brand->heineken) {
                    $heineken += $brand->heineken;
                    $brandsTotal += $brand->heineken;
                }

                if($brand->insomnia) {
                    $insomnia += $brand->insomnia;
                    $brandsTotal += $brand->insomnia;
                }

                if($brand->kellogs){
                    $kellogs += $brand->kellogs;
                    $brandsTotal += $brand->kellogs;
                }

                if($brand->lego) {
                    $lego += $brand->lego;
                    $brandsTotal += $brand->lego;
                }

                if($brand->lolly_and_cookes) {
                    $lolly_and_cookes += $brand->lolly_and_cookes;
                    $brandsTotal += $brand->lolly_and_cookes;
                }

                if($brand->loreal) {
                    $loreal += $brand->loreal;
                    $brandsTotal += $brand->loreal;
                }

                if($brand->nescafe) {
                    $nescafe += $brand->nescafe;
                    $brandsTotal += $brand->nescafe;
                }

                if($brand->nestle) {
                    $nestle += $brand->nestle;
                    $brandsTotal += $brand->nestle;
                }

                if($brand->marlboro) {
                    $marlboro += $brand->marlboro;
                    $brandsTotal += $brand->marlboro;
                }

                if($brand->mcdonalds) {
                    $mcdonalds += $brand->mcdonalds;
                    $brandsTotal += $brand->mcdonalds;
                }

                if($brand->nike) {
                    $nike+= $brand->nike;
                    $brandsTotal += $brand->nike;
                }

                if($brand->obriens) {
                    $obriens+= $brand->obriens;
                    $brandsTotal += $brand->obriens;
                }

                if($brand->pepsi) {
                    $pepsi+= $brand->pepsi;
                    $brandsTotal += $brand->pepsi;
                }

                if($brand->redbull) {
                    $redbull += $brand->redbull;
                    $brandsTotal += $brand->redbull;
                }

                if($brand->samsung) {
                    $samsung += $brand->samsung;
                    $brandsTotal += $brand->samsung;
                }

                if($brand->subway) {
                    $subway += $brand->subway;
                    $brandsTotal += $brand->subway;
                }

                if($brand->starbucks) {
                    $starbucks += $brand->starbucks;
                    $brandsTotal += $brand->starbucks;
                }

                if($brand->supermacs) {
                    $supermacs += $brand->supermacs;
                    $brandsTotal += $brand->supermacs;
                }

                if($brand->tayto) {
                    $tayto += $brand->tayto;
                    $brandsTotal += $brand->tayto;
                }

                if($brand->wilde_and_greene) {
                    $wilde_and_greene += $brand->wilde_and_greene;
                    $brandsTotal += $brand->wilde_and_greene;
                }
            }

            $country->total_brands = $brandsTotal;
            $country->manual_verify = 1;

            $country->total_adidas = $adidas;
            $country->total_amazon = $amazon;
            $country->total_apple = $apple;
            $country->total_applegreen = $applegreen;
            $country->total_avoca = $avoca;
            $country->total_budweiser = $budweiser;
            $country->total_bewleys = $bewleys;
            $country->total_brambles = $brambles;
            $country->total_butlers = $butlers;
            $country->total_cafe_nero = $cafe_nero;
            $country->total_centra = $centra;
            $country->total_coke = $coke;
            $country->total_colgate = $colgate;
            $country->total_corona = $corona;
            $country->total_costa = $costa;
            $country->total_esquires = $esquires;
            $country->total_frank_and_honest = $frank_and_honest;
            $country->total_fritolay = $fritolay;
            $country->total_gillette = $gillette;
            $country->total_heineken = $heineken;
            $country->total_insomnia = $insomnia;
            $country->total_kellogs = $kellogs;
            $country->total_lego = $lego;
            $country->total_lolly_and_cookes = $lolly_and_cookes;
            $country->total_loreal = $loreal;
            $country->total_nescafe = $nescafe;
            $country->total_nestle = $nestle;
            $country->total_marlboro = $marlboro;
            $country->total_mcdonalds = $mcdonalds;
            $country->total_nike = $nike;
            $country->total_obriens = $obriens;
            $country->total_pepsi = $pepsi;
            $country->total_redbull = $redbull;
            $country->total_samsung = $samsung;
            $country->total_subway = $subway;
            $country->total_supermacs = $supermacs;
            $country->total_starbucks = $starbucks;
            $country->total_tayto = $tayto;
            $country->total_wilde_and_greene = $wilde_and_greene;
            $country->save();
        }
    }
}
