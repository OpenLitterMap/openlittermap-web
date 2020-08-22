<?php

namespace App\Listeners;

use App\Categories\Smoking;
use App\Categories\Alcohol;
use App\Categories\Coffee;
use App\Categories\Food;
use App\Categories\SoftDrinks;
use App\Categories\Drugs;
use App\Categories\Sanitary;
use App\Categories\Other;
use App\Categories\Coastal;
use App\Categories\Pathway;
use App\Categories\Art;
use App\Categories\Brand;
use App\Categories\TrashDog;

use App\City;
use App\State;
use App\Country;
use App\Photo;
use App\Events\PhotoVerifiedByUser;
use App\Events\DynamicUpdate;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateStatesTotals
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
    public function handle(PhotoVerifiedByUser $event)
    {
        $photoId = $event->photoId;
        $photo = Photo::find($photoId);
        $state = State::find($photo->city_id);

        // Get all verified photos for that state 
        $statePhotos = Photo::where([
            ['state_id', $photo->state_id],
            ['verified', '>', 0]
        ])->get();

        // count, update, save
        $state->total_images = $statePhotos->count();
        $state->save();

        // count, update and save total users
        $users = [];
        foreach($statePhotos as $index => $statePhoto) {
            $users[$statePhoto->user_id] = $statePhoto->user_id;
        }
        $sizeOfUsers = sizeof($users);
        $state->total_contributors = $sizeOfUsers;
        $state->save();

        if($photo['smoking_id']) {
            $smokingPhotos = Photo::where([
                ['state_id', $photo->state_id],
                ['verified', '>', 0],
                ['smoking_id', '!=', null]
            ])->get();

            $smokingTotal = 0;
            $cigaretteTotal = 0;
            foreach($smokingPhotos as $smokingPhoto) {

                $smoking = App\Categories\Smoking::find($smokingPhoto['smoking_id']);

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
            $state->total_cigaretteButts = $cigaretteTotal;
            $state->total_smoking = $smokingTotal;
            $state->save();
        }

        if($photo['food_id']) {
            $foodPhotos = Photo::where([
                ['state_id', $photo->state_id],
                ['verified', '>', 0]
            ])->get();

            $foodTotal = 0;
            foreach($foodPhotos as $foodPhoto) {

                $food = App\Categories\Food::find($foodPhoto['food_id']);

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
            $state->total_food = $foodTotal;
            $state->save();
        }

        if($photo['softdrinks_id']) {
            $softdrinkPhotos = Photo::where([
                ['state_id', $photo->state_id],
                ['verified', '>', 0],
                ['softdrinks_id', '!=', null]
            ])->get();

            $softDrinksTotal = 0;
            $plasticBottleTotal = 0;
            foreach($softdrinkPhotos as $softdrinkPhoto) {

                $softdrink = App\Categories\SoftDrinks::find($softdrinkPhoto['softdrinks_id']);

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
                $softDrinksTotal += $softdrink['softDrinkOther'];
            }
            $state->total_softDrinks = $softDrinksTotal;
            $state->total_plasticBottles = $plasticBottleTotal;
            $state->save();
        }

        if($photo['alcohol_id']){
            $alcoholPhotos = Photo::where([
                ['state_id', $photo->state_id],
                ['verified', '>', 0],
                ['alcohol_id', '!=', null]
            ])->get();

            $alcoholTotal = 0;

            foreach($alcoholPhotos as $alcoholPhoto){

                $alcohol = App\Categories\Alcohol::find($alcoholPhoto['alcohol_id']);

                $alcoholTotal += $alcohol['beerBottle'];
                $alcoholTotal += $alcohol['spiritBottle'];
                $alcoholTotal += $alcohol['wineBottle'];
                $alcoholTotal += $alcohol['beerCan'];
                $alcoholTotal += $alcohol['brokenGlass'];
                $alcoholTotal += $alcohol['paperCardAlcoholPackaging'];
                $alcoholTotal += $alcohol['plasticAlcoholPackaging'];
                $alcoholTotal += $alcohol['bottleTops'];
                $alcoholTotal += $alcohol['alcoholOther'];
            }
            $state->total_alcohol = $alcoholTotal;
            $state->save();
        }

        if($photo['coffee_id']){
            $coffeePhotos = Photo::where([
                ['state_id', $photo->state_id],
                ['verified', '>', 0],
                ['coffee_id', '!=', null]
            ])->get();

            $coffeeTotal = 0;
            foreach($coffeePhotos as $coffeePhoto){

                $coffee = App\Categories\Coffee::find($coffeePhoto['coffee_id']);

                $coffeeTotal += $coffee['coffeeCups'];
                $coffeeTotal += $coffee['coffeeLids'];
                $coffeeTotal += $coffee['coffeeOther'];
            }
            $state->total_coffee = $coffeeTotal;
            $state->save();
        }

        if($photo['drugs_id']){
            $drugsPhotos = Photo::where([
                ['state_id', $photo->state_id],
                ['verified', '>', 0],
                ['drugs_id', '!=', null]
            ])->get();

            $drugsTotal = 0;
            $needlesTotal = 0;
            foreach($drugsPhotos as $drugPhoto){

                $drugs = App\Categories\Drugs::find($drugPhoto['drugs_id']);

              $needlesTotal += $drugs['needles'];
                $drugsTotal += $drugs['needles'];
                $drugsTotal += $drugs['wipes'];
                $drugsTotal += $drugs['tops'];
                $drugsTotal += $drugs['packaging'];
                $drugsTotal += $drugs['waterBottle'];
                $drugsTotal += $drugs['spoons'];
                $drugsTotal += $drugs['needlebin'];
                $drugsTotal += $drugs['barrels'];
                $drugsTotal += $drugs['usedtinfoil'];
                $drugsTotal += $drugs['fullpackage'];
                $drugsTotal += $drugs['baggie'];
                $drugsTotal += $drugs['crack_pipes'];
                $drugsTotal += $drugs['drugsOther'];
            }
            $state->total_drugs = $drugsTotal;
            $state->total_needles = $drugsTotal;
            $state->save();
        }

        if($photo['sanitary_id']){
            $sanitaryPhotos = Photo::where([
                ['state_id', $photo->state_id],
                ['verified', '>', 0],
                ['sanitary_id', '!=', null]
            ])->get();

            $sanitaryTotal = 0;
            foreach($sanitaryPhotos as $sanitaryPhoto){

                $sanitary = App\Categories\Sanitary::find($sanitaryPhoto['sanitary_id']);

                $sanitaryTotal += $sanitary['condoms'];
                $sanitaryTotal += $sanitary['nappies'];
                $sanitaryTotal += $sanitary['menstral'];
                $sanitaryTotal += $sanitary['deodorant'];
                $sanitaryTotal += $sanitary['deodorant'];
                $sanitaryTotal += $sanitary['ear_swabs'];
                $sanitaryTotal += $sanitary['tooth_pick'];
                $sanitaryTotal += $sanitary['tooth_brush'];
                $sanitaryTotal += $sanitary['sanitaryOther'];
            }
            $state->total_sanitary = $sanitaryTotal;
            $state->save();
        }

        if($photo['other_id']){
            $otherPhotos = Photo::where([
                ['state_id', $photo->state_id],
                ['verified', '>', 0],
                ['other_id', '!=', null]
            ])->get();

            $otherTotal = 0;
            foreach($otherPhotos as $otherPhoto){

                $other = App\Categories\Other::find($otherPhoto['other_id']);

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
                $otherTotal += $other['other'];
            }
            $state->total_other = $otherTotal;
            $state->save();
        }

        if($photo['coastal_id']){
            $coastalPhotos = Photo::where([
                ['state_id', $photo->state_id],
                ['verified', '>', 0],
                ['coastal_id', '!=', null]
            ])->get();

            $coastalTotal = 0;
            foreach($coastalPhotos as $coastalPhoto){
                $coastal = App\Categories\Coastal::find($coastalPhoto['coastal_id']);

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
                $coastalTotal += $coastal['coastal_other'];
            }
            $state->total_coastal = $coastalTotal;
            $state->save();
        }

        if($photo['pathway_id']) {
            $pathwayPhotos = Photo::where([
                ['state_id', $photo->state_id],
                ['verified', '>', 0],
                ['pathway_id', '!=', null]
            ])->get();

            $pathwayTotal = 0;
            foreach($pathwayPhotos as $pathwayPhoto) {
                $pathway = App\Categories\Pathway::find($pathwayPhoto['pathway_id']);

                $pathwayTotal += $pathway['gutter'];
                $pathwayTotal += $pathway['gutter_long'];
                $pathwayTotal += $pathway['kerb_hole_small'];
                $pathwayTotal += $pathway['kerb_hole_large'];
                $pathwayTotal += $pathway['pathwayOther'];
            }
            $state->total_pathways = $pathwayTotal;
            $state->save();
        }

        if($photo['art_id']) {
            $artPhotos = Photo::where([
                ['state_id', $photo->state_id],
                ['verified', '>', 0],
                ['art_id', '!=', null]
            ])->get();

            $artTotal = 0;
            foreach($artPhotos as $artPhoto) {
                $pathway = App\Categories\Art::find($artPhoto['art_id']);

                $artTotal += $pathway['item'];
            }
            $state->total_art = $artTotal;
            $state->save();
        }
    }
}
