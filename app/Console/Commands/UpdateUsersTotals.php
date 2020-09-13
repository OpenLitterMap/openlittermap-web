<?php

namespace App\Console\Commands;

use App\Models\User\User;
use App\Models\Photo;
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

class UpdateUsersTotals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:update-users-totals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the total verified litter a user has uploaded';

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
        $users = User::where([
            ['verified', 1],
            ['has_uploaded', 1]
        ])->get();

        foreach($users as $user) {
            $litterTotal = 0;
            $smokingTotal = 0;
            $cigarettesTotal = 0;
            $foodTotal = 0;
            $softdrinksTotal = 0;
            $alcoholTotal = 0;
            $coffeeTotal = 0;
            $needlesTotal = 0;
            $drugsTotal = 0;
            $sanitaryTotal = 0;
            $otherTotal = 0;

            $photos = Photo::where([
                ['user_id', $user->id],
                ['verified', '>', 0]
            ])->get();

            foreach($photos as $photo) {

                if ($photo["smoking_id"]) {
                    $smoking = Smoking::find($photo["smoking_id"]);
                    $smokingTotal += $smoking['butts'];
                    $smokingTotal += $smoking['lighters'];
                    $smokingTotal += $smoking['cigaretteBox'];
                    $smokingTotal += $smoking['tobaccoPouch'];
                    $smokingTotal += $smoking['skins'];
                    $smokingTotal += $smoking['smokingOther'];
                    $litterTotal += $smokingTotal;
                }
                if ($photo["food_id"]) {
                    $food = Food::find($photo['food_id']);
                    $foodTotal += $food['sweetWrappers'];
                    $foodTotal += $food['paperFoodPackaging'];
                    $foodTotal += $food['plasticFoodPackaging'];
                    $foodTotal += $food['plasticCutlery'];
                    $foodTotal += $food['foodOther'];
                    $litterTotal += $foodTotal;
                }
                if ($photo["softdrinks_id"]) {
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
                    $litterTotal += $softDrinksTotal;
                }
                if ($photo["alcohol_id"]) {
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
                    $litterTotal += $alcoholTotal;
                }
                if ($photo["coffee_id"]) {
                    $coffee = Coffee::find($photo['coffee_id']);
                    $coffeeTotal += $coffee['coffeeCups'];
                    $coffeeTotal += $coffee['coffeeLids'];
                    $coffeeTotal += $coffee['coffeeOther'];
                    $litterTotal += $coffeeTotal;
                }
                if ($photo["drugs_id"]) {
                    $drugs = Drugs::find($drugPhoto['drugs_id']);

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
                    $litterTotal += $drugsTotal;
                }
                if ($photo["sanitary_id"]) {
                    $sanitary = Sanitary::find($photo['sanitary_id']);
                    $sanitaryTotal += $sanitary['condoms'];
                    $sanitaryTotal += $sanitary['nappies'];
                    $sanitaryTotal += $sanitary['menstral'];
                    $sanitaryTotal += $sanitary['deodorant'];
                    $sanitaryTotal += $sanitary['sanitaryOther'];
                    $litterTotal += $sanitaryTotal;
                }
                if ($photo["other_id"]) {
                    $other = Other::find($otherPhoto['other_id']);
                    $otherTotal += $other['dogshit'];
                    $otherTotal += $other['plastic'];
                    $otherTotal += $other['dump'];
                    $otherTotal += $other['metal'];
                    $otherTotal += $other['other'];
                    $litterTotal += $otherTotal;
                }
            } // end photos loop
            $user->total_litter = $litterTotal;
            $user->total_smoking = $smokingTotal;
            $user->total_cigaretteButts = $cigarettesTotal;
            $user->total_food = $foodTotal;
            $user->total_softDrinks = $softDrinksTotal;
            $user->total_plasticBottles = $plasticBottleTotal;
            $user->total_smoking = $smokingTotal;
            $user->save();
        } // end users loop
    }
}

