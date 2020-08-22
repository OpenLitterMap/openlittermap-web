<?php

namespace App\Console\Commands;

use App\Country;
use App\Photo;

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
use Illuminate\Console\Command;

class UpdateCountries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:update-countries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh all values for countries verified data';

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
        $countries = Country::where('total_images', '>', 2)->get();
        
        // loop
        foreach($countries as $country) { 
            // get photos           
            $photos = Photo::where([
                ['country_id', $country->id],
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

                // Check the photo for foreign keys, count and update them on the Country 
                if($photo['smoking_id']) {
                    
                    // find each instance in the smoking table 
                    $smoking = Smoking::find($photo['smoking_id']);
                    // count totals
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

                    $sanitaryTotal += $sanitary['gloves'];
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

            $country->total_cigaretteButts = $cigaretteTotal;
            $country->total_smoking = $smokingTotal;
            $country->total_food = $foodTotal;
            $country->total_softDrinks = $softDrinksTotal;
            $country->total_plasticBottles = $plasticBottleTotal;
            $country->total_alcohol = $alcoholTotal;
            $country->total_coffee = $coffeeTotal;
            $country->total_drugs = $drugsTotal;
            $country->total_needles = $drugsTotal;
            $country->total_sanitary = $sanitaryTotal;
            $country->total_other = $otherTotal;
            $sizeOfUsers = sizeof($users);
            $country->total_contributors = $sizeOfUsers;
            $country->save();

    } // end for each countries 
}
