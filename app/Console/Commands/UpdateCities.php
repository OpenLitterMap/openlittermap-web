<?php

namespace App\Console\Commands;

use App\Models\Location\City;
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

class UpdateCities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:update-cities';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh all values for cities verified data';

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
        // Get all countries that have 2+ images
        $cities = City::where('total_images', '>=', 2)->get();

        // loop
        foreach($cities as $city) {
            // get photos
            $photos = Photo::where([
                ['city_id', $city->id],
                ['verified', '>', 0],
            ])->get();

            // count contributors
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
                        $drugsTotal += $drugs['drugsOther'];
                    }
                }

                if($photo['sanitary_id']) {
                    $sanitary = Sanitary::find($photo['sanitary_id']);
                    $sanitaryTotal += $sanitary['condoms'];
                    $sanitaryTotal += $sanitary['nappies'];
                    $sanitaryTotal += $sanitary['menstral'];
                    $sanitaryTotal += $sanitary['deodorant'];
                    $sanitaryTotal += $sanitary['sanitaryOther'];
                }

                if($photo['other_id']) {
                    $other = Other::find($photo['other_id']);
                    $otherTotal += $other['dogshit'];
                    $otherTotal += $other['plastic'];
                    $otherTotal += $other['dump'];
                    $otherTotal += $other['metal'];
                    $otherTotal += $other['other'];
                } // end other

            } // end for each photos

            $city->total_cigaretteButts = $cigaretteTotal;
            $city->total_smoking = $smokingTotal;
            $city->total_food = $foodTotal;
            $city->total_softDrinks = $softDrinksTotal;
            $city->total_plasticBottles = $plasticBottleTotal;
            $city->total_coffee = $coffeeTotal;
            $city->total_alcohol = $alcoholTotal;
            $city->total_drugs = $drugsTotal;
            $city->total_needles = $drugsTotal;
            $city->total_sanitary = $sanitaryTotal;
            $city->total_other = $otherTotal;
            $sizeOfUsers = sizeof($users);
            $city->total_contributors = $sizeOfUsers;
            $city->save();

    } // end for each cities
}
