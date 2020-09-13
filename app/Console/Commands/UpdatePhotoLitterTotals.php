<?php

namespace App\Console\Commands;

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

class UpdatePhotoLitterTotals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:update-litter-totals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Count total litter per verified photo';

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
        $photos = Photo::where('verified', '>', 0)->get();

        foreach($photos as $photo) {
            $totalLitter = 0;

            if($photo['smoking_id']) {
                $smoking = Smoking::find($photo['smoking_id']);
                $totalLitter += $smoking['butts'];
                $totalLitter += $smoking['lighters'];
                $totalLitter += $smoking['cigaretteBox'];
                $totalLitter += $smoking['tobaccoPouch'];
                $totalLitter += $smoking['skins'];
                $totalLitter += $smoking['smokingOther'];
            }

            if($photo['food_id']) {
                $food = Food::find($photo['food_id']);
                $totalLitter += $food['sweetWrappers'];
                $totalLitter += $food['paperFoodPackaging'];
                $totalLitter += $food['plasticFoodPackaging'];
                $totalLitter += $food['plasticCutlery'];
                $totalLitter += $food['foodOther'];
            }

            if($photo['coffee_id']) {
                $coffee = Coffee::find($photo['coffee_id']);
                $totalLitter += $coffee['coffeeCups'];
                $totalLitter += $coffee['coffeeLids'];
                $totalLitter += $coffee['coffeeOther'];
            }

            if($photo['softdrinks_id']) {
                $softdrink = SoftDrinks::find($photo['softdrinks_id']);
                $totalLitter += $softdrink['waterBottle'];
                $totalLitter += $softdrink['fizzyDrinkBottle'];
                $totalLitter += $softdrink['tinCan'];
                $totalLitter += $softdrink['bottleLid'];
                $totalLitter += $softdrink['bottleLabel'];
                $totalLitter += $softdrink['sportsDrink'];
                $totalLitter += $softdrink['softDrinkOther'];
            }

            if($photo['alcohol_id']){
                $alcohol = Alcohol::find($photo['alcohol_id']);
                $totalLitter += $alcohol['beerBottle'];
                $totalLitter += $alcohol['spiritBottle'];
                $totalLitter += $alcohol['wineBottle'];
                $totalLitter += $alcohol['beerCan'];
                $totalLitter += $alcohol['brokenGlass'];
                $totalLitter += $alcohol['paperCardAlcoholPackaging'];
                $totalLitter += $alcohol['plasticAlcoholPackaging'];
                $totalLitter += $alcohol['bottleTops'];
                $totalLitter += $alcohol['alcoholOther'];
            }

            if($photo['drugs_id']) {
                $drugs = Drugs::find($photo['drugs_id']);
                $totalLitter += $drugs['needles'];
                $totalLitter += $drugs['wipes'];
                $totalLitter += $drugs['tops'];
                $totalLitter += $drugs['packaging'];
                $totalLitter += $drugs['waterBottle'];
                $totalLitter += $drugs['spoons'];
                $totalLitter += $drugs['needlebin'];
                $totalLitter += $drugs['barrels'];
                $totalLitter += $drugs['usedtinfoil'];
                $totalLitter += $drugs['fullpackage'];
                $totalLitter += $drugs['drugsOther'];
            }

            if($photo['sanitary_id']) {
                $sanitary = Sanitary::find($photo['sanitary_id']);
                $totalLitter += $sanitary['condoms'];
                $totalLitter += $sanitary['nappies'];
                $totalLitter += $sanitary['menstral'];
                $totalLitter += $sanitary['deodorant'];
                $totalLitter += $sanitary['sanitaryOther'];
            }

            if($photo['other_id']) {
                $other = Other::find($photo['other_id']);
                $totalLitter += $other['dogshit'];
                $totalLitter += $other['plastic'];
                $totalLitter += $other['dump'];
                $totalLitter += $other['metal'];
                $totalLitter += $other['other'];
            }
            $photo->total_litter = $totalLitter;
            $photo->save();
        }
    }
}
