<?php

namespace App\Console\Commands;

use App\Models\Photo;
use App\Models\Location\State;

use App\Models\Litter\Categories\Smoking;
use App\Models\Litter\Categories\Alcohol;
use App\Models\Litter\Categories\Coffee;
use App\Models\Litter\Categories\Food;
use App\Models\Litter\Categories\SoftDrinks;
use App\Models\Litter\Categories\Drugs;
use App\Models\Litter\Categories\Sanitary;
use App\Models\Litter\Categories\Other;
use App\Models\Litter\Categories\Coastal;
use App\Models\Litter\Categories\Pathway;
use App\Models\Litter\Categories\Art;
use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Categories\TrashDog;

use Illuminate\Console\Command;

class UpdateStateValues extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:update-states';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update total values for states';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $states = State::all();

        foreach($states as $state) {

            $photos = Photo::where([
                ['state_id', $state->id],
                ['verified', '>', 0]
            ])->get();

            $photoCount = $photos->count();

            $totalLitter = 0;
            $users = [];

            $smokingTotal = 0;
            $cigaretteTotal = 0;

            $foodTotal = 0;

            $softDrinksTotal = 0;
            $plasticBottleTotal = 0;

            $alcoholTotal = 0;

            $coffeeTotal = 0;

            $drugsTotal = 0;
            $needlesTotal = 0;

            $sanitaryTotal = 0;
            $otherTotal = 0;
            $coastalTotal = 0;
            $pathwaysTotal = 0;

            foreach($photos as $photo) {

                $users[$photo->user_id] = $photo->user_id;

                if($photo['smoking_id']) {
                    $smoking = Smoking::find($photo['smoking_id']);
                    $cigaretteTotal += $smoking['butts'];
                      $smokingTotal += $smoking['butts'];
                      $smokingTotal += $smoking['lighters'];
                      $smokingTotal += $smoking['cigaretteBox'];
                      $smokingTotal += $smoking['tobaccoPouch'];
                      $smokingTotal += $smoking['skins'];
                      $smokingTotal += $smoking['smokingOther'];
                }

                if($photo['food_id']) {
                    $food = Food::find($photo['food_id']);
                    $foodTotal += $food['sweetWrappers'];
                    $foodTotal += $food['paperFoodPackaging'];
                    $foodTotal += $food['plasticFoodPackaging'];
                    $foodTotal += $food['plasticCutlery'];
                    $foodTotal += $food['foodOther'];
                }

                if($photo['softdrinks_id']) {
                    $softdrink = SoftDrinks::find($photo['softdrinks_id']);
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
                       $softDrinksTotal += $softdrink['softDrinkOther'];
                }

                if($photo['alcohol_id']){
                    $alcohol = Alcohol::find($photo['alcohol_id']);
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

                if($photo['coffee_id']) {
                    $coffee = Coffee::find($photo['coffee_id']);
                    $coffeeTotal += $coffee['coffeeCups'];
                    $coffeeTotal += $coffee['coffeeLids'];
                    $coffeeTotal += $coffee['coffeeOther'];
                }

                if($photo['drugs_id']) {
                    $drugs = Drugs::find($photo['drugs_id']);
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

                if($photo['sanitary_id']) {
                    $sanitary = Sanitary::find($photo['sanitary_id']);
                    $sanitaryTotal += $sanitary['condoms'];
                    $sanitaryTotal += $sanitary['nappies'];
                    $sanitaryTotal += $sanitary['menstral'];
                    $sanitaryTotal += $sanitary['deodorant'];
                    $sanitaryTotal += $sanitary['ear_swabs'];
                    $sanitaryTotal += $sanitary['tooth_pick'];
                    $sanitaryTotal += $sanitary['tooth_brush'];
                    $sanitaryTotal += $sanitary['sanitaryOther'];
                }

                if($photo['other_id']) {
                    $other = Other::find($photo['other_id']);
                    $otherTotal += $other['dogshit'];
                    $otherTotal += $other['dump'];
                    $otherTotal += $other['plastic'];
                    $otherTotal += $other['metal'];
                    $otherTotal += $other['plastic_bags'];
                    $otherTotal += $other['election_posters'];
                    $otherTotal += $other['forsale_posters'];
                    $otherTotal += $other['books'];
                    $otherTotal += $other['magazine'];
                    $otherTotal += $other['paper'];
                    $otherTotal += $other['stationary'];
                    $otherTotal += $other['other'];
                } // end other

                if($photo['coastal_id']) {
                  $coastal = Coastal::find($photo['coastal_id']);
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

                if($photo['pathways_id']) {
                  $pathway = Pathway::find($photo['pathways_id']);
                  $pathwaysTotal += $pathway['gutter'];
                  $pathwaysTotal += $pathway['gutter_long'];
                  $pathwaysTotal += $pathway['kerb_hole_small'];
                  $pathwaysTotal += $pathway['kerb_hole_large'];
                  $pathwaysTotal += $pathway['pathwayOther'];
                }

            } // end for each photos

            $state->total_images = $photoCount;
            $state->total_cigaretteButts = $cigaretteTotal;
            $state->total_smoking = $smokingTotal;
            $state->total_food = $foodTotal;
            $state->total_softdrinks = $softDrinksTotal;
            $state->total_plasticBottles = $plasticBottleTotal;
            $state->total_coffee = $coffeeTotal;
            $state->total_alcohol = $alcoholTotal;
            $state->total_drugs = $drugsTotal;
            $state->total_needles = $drugsTotal;
            $state->total_sanitary = $sanitaryTotal;
            $state->total_other = $otherTotal;
            $sizeOfUsers = sizeof($users);
            $state->total_contributors = $sizeOfUsers;
            $state->save();
        }
    }
}
