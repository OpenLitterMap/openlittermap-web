<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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

class CompileResultsString extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'global:compile-verified-resultstrings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate string result of verified data for global map.';

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
     * Save string to text column
     *
     * Todo - save translation keys
     */
    public function handle()
    {
        $photos = Photo::where('verified', '>', 0)->get();

        foreach ($photos as $photo)
        {
            if (is_null($photo['result_string']))
            {
                if ($photo['smoking_id'])
                {
                    $smoking = Smoking::find($photo['smoking_id']);
                    $smoking_string = '';
                    if ($smoking['butts'])
                    {
                        $smoking_string .= 'Cigarette/Butts: ' . $smoking['butts'];
                    }
                    if($smoking['lighters']){
                        $smoking_string .= ' Lighters: ' . $smoking['lighters'];
                    }
                    if($smoking['cigaretteBox']){
                        $smoking_string .= ' Cigarette Box: ' . $smoking['cigaretteBox'];
                    }
                    if($smoking['tobaccoPouch']){
                        $smoking_string .= ' Tobacco Pouch: ' . $smoking['tobaccoPouch'];
                    }
                    if($smoking['plastic']){
                        $smoking_string .= ' Cellophane: ' . $smoking['plastic'];
                    }
                    if($smoking['filters']){
                        $smoking_string .= ' Filters: ' . $smoking['filters'];
                    }
                    $photo['result_string'] .= $smoking_string;
                }

                if($photo['food_id']){
                    $food = Food::find($photo['food_id']);
                    $food_string = '';
                    if($food['sweetWrappers']){
                        $food_string .= ' Sweet Wrappers: ' . $food['sweetWrappers'];
                    }
                    if($food['cardboardFoodPackaging']){
                        $food_string .= ' Cardboard Food Packaging: ' . $food['cardboardFoodPackaging'];
                    }
                    if($food['plasticFoodPackaging']){
                        $food_string .= ' Plastic Food Packaging: ' . $food['plasticFoodPackaging'];
                    }
                    if($food['paperFoodPackaging']){
                        $food_string .= ' Paper Food Packaging: ' . $food['paperFoodPackaging'];
                    }
                    if($food['plasticCutlery']){
                        $food_string .= ' Plastic Cutlery: ' . $food['plasticCutlery'];
                    }
                    if($food['crisp_small']){
                        $food_string .= ' Crisps/Chip packet (small): ' . $food['crisp_small'];
                    }
                    if($food['crisp_large']){
                        $food_string .= ' Crisps/Chip packet (large): ' . $food['crisp_large'];
                    }
                    if($food['styrofoam_plate']){
                        $food_string .= ' Styrofoam: ' . $food['styrofoam_plate'];
                    }
                    if($food['napkins']){
                        $food_string .= ' Napkins: ' . $food['napkins'];
                    }
                    if($food['sauce_packet']){
                        $food_string .= ' Sauce Packet: ' . $food['sauce_packet'];
                    }
                    if($food['glass_jar']){
                        $food_string .= ' Glass Jar: ' . $food['glass_jar'];
                    }
                    if($food['glass_jar_lid']){
                        $food_string .= ' Glass Jar Lid: ' . $food['glass_jar_lid'];
                    }
                    if($food['foodOther']){
                        $food_string .= ' Food (other): ' . $food['foodOther'];
                    }
                    $photo['result_string'] .= $food_string;
                }

                if($photo['coffee_id']){
                    $coffee_string = '';
                    $coffee = Coffee::find($photo['coffee_id']);
                    if($coffee['coffeeCups']){
                        $coffee_string .= ' Coffee Cups: ' . $coffee['coffeeCups'];
                    }
                    if($coffee['coffeeLids']){
                        $coffee_string .= ' Coffee Lids: ' . $coffee['coffeeLids'];
                    }
                    if($coffee['coffeeOther']){
                        $coffee_string .= ' Coffee (other): ' . $coffee['coffeeOther'];
                    }
                    $photo['result_string'] .= $coffee_string;
                }

                if($photo['softdrinks_id']){
                    $softdrinks_string = '';
                    $softdrinks = SoftDrinks::find($photo['softdrinks_id']);
                    if($softdrinks['waterBottle']){
                        $softdrinks_string .= ' Plastic Water Bottle: ' . $softdrinks['waterBottle'];
                    }
                    if($softdrinks['fizzyDrinkBottle']){
                        $softdrinks_string .= ' Plastic FizzyDrink Bottle: ' . $softdrinks['fizzyDrinkBottle'];
                    }
                    if($softdrinks['bottleLid']){
                        $softdrinks_string .= ' Plastic Lid: ' . $softdrinks['bottleLid'];
                    }
                    if($softdrinks['bottleLabel']){
                        $softdrinks_string .= ' Plastic Label: ' . $softdrinks['bottleLabel'];
                    }
                    if($softdrinks['tinCan']){
                        $softdrinks_string .= ' Can: ' . $softdrinks['tinCan'];
                    }
                    if($softdrinks['sportsDrink']){
                        $softdrinks_string .= ' Sports Drink: ' . $softdrinks['sportsDrink'];
                    }
                    if($softdrinks['straws']){
                        $softdrinks_string .= ' Straws: ' . $softdrinks['straws'];
                    }
                    if($softdrinks['plasticCups']){
                        $softdrinks_string .= ' Plastic Cups: ' . $softdrinks['plasticCups'];
                    }
                    if($softdrinks['plasticCupTops']){
                        $softdrinks_string .= ' Plastic Cup Tops: ' . $softdrinks['plasticCupTops'];
                    }
                    if($softdrinks['milk_bottle']){
                        $softdrinks_string .= ' Milk Bottle: ' . $softdrinks['milk_bottle'];
                    }
                    if($softdrinks['milk_carton']){
                        $softdrinks_string .= ' Milk Carton: ' . $softdrinks['milk_carton'];
                    }
                    if($softdrinks['pape_cups']){
                        $softdrinks_string .= ' Paper Cups: ' . $softdrinks['paper_cups'];
                    }
                    if($softdrinks['juice_cartons']){
                        $softdrinks_string .= ' Juice Cartons: ' . $softdrinks['juice_cartons'];
                    }
                    if($softdrinks['juice_packet']){
                        $softdrinks_string .= ' Juice Packet: ' . $softdrinks['juice_packet'];
                    }
                    if($softdrinks['ice_tea_bottles']){
                        $softdrinks_string .= ' Ice Tea: ' . $softdrinks['ice_tea_bottles'];
                    }
                    if($softdrinks['ice_tea_can']){
                        $softdrinks_string .= ' Ice Tea Can: ' . $softdrinks['ice_tea_can'];
                    }
                    if($softdrinks['energy_can']){
                        $softdrinks_string .= ' Energy Can: ' . $softdrinks['energy_can'];
                    }
                    $photo['result_string'] .= $softdrinks_string;
                }

                if($photo['alcohol_id']){
                    $alcohol_string = '';
                    $alcohol = Alcohol::find($photo['alcohol_id']);
                    if($alcohol['beerBottle']){
                        $alcohol_string .= ' Beer Bottle: ' . $alcohol['beerBottle'];
                    }
                    if($alcohol['wineBottle']){
                        $alcohol_string .= ' Wine Bottle: ' . $alcohol['wineBottle'];
                    }
                    if($alcohol['spiritBottle']){
                        $alcohol_string .= ' Spirit Bottle: ' . $alcohol['spiritBottle'];
                    }
                    if($alcohol['beerCan']){
                        $alcohol_string .= ' Beer Can: ' . $alcohol['beerCan'];
                    }
                    if($alcohol['brokenGlass']){
                        $alcohol_string .= ' Broken Glass: ' . $alcohol['brokenGlass'];
                    }
                    if($alcohol['paperCardAlcoholPackaging']){
                        $alcohol_string .= ' Paper/Card Alcohol Packaging: ' . $alcohol['paperCardAlcoholPackaging'];
                    }
                    if($alcohol['plasticAlcoholPackaging']){
                        $alcohol_string .= ' Plastic Alcohol Packaging: ' . $alcohol['plasticAlcoholPackaging'];
                    }
                    if($alcohol['bottleTops']){
                        $alcohol_string .= ' Beer Bottle Tops: ' . $alcohol['bottleTops'];
                    }
                    if($alcohol['wine']){
                        $alcohol_string .= ' Beer Bottle: ' . $alcohol['wine'];
                    }
                    if($alcohol['alcoholOther']){
                        $alcohol_string .= ' Alcohol (other): ' . $alcohol['alcoholOther'];
                    }
                    $photo['result_string'] .= $alcohol_string;
                }

                if($photo['sanitary_id']){
                    $sanitary_string = '';
                    $sanitary = Sanitary::find($photo['sanitary_id']);
                    if ($sanitary['condoms']) {
                        $sanitary_string .= ' Condoms: ' . $sanitary['condoms'];
                    }
                    if ($sanitary['nappies']) {
                        $sanitary_string .= ' Nappies: ' . $sanitary['nappies'];
                    }
                    if ($sanitary['menstral']) {
                        $sanitary_string .= ' Menstral: ' . $sanitary['condoms'];
                    }
                    if ($sanitary['deodorant']) {
                        $sanitary_string .= ' Deodorant: ' . $sanitary['deodorant'];
                    }
                    if ($sanitary['ear_swabs']) {
                        $sanitary_string .= ' Ear swabs: ' . $sanitary['ear_swabs'];
                    }
                    if ($sanitary['tooth_pick']){
                        $sanitary_string .= ' Tooth pick: ' . $sanitary['tooth_pick'];
                    }
                    if ($sanitary['sanitaryOther']){
                        $sanitary_string .= ' Sanitary (other): ' . $sanitary['sanitaryOther'];
                    }
                    if ($sanitary->hand_sanitiser) {
                        $sanitary_string .= ' Hand Sanitiser: ' . $sanitary->hand_sanitiser;
                    }
                    $photo['result_string'] .= $sanitary_string;
                }

                if($photo['coastal_id']){
                    $coastal_string = '';
                    $coastal = Coastal::find($photo['coastal_id']);
                    if($coastal['microplastics']){
                        $coastal_string .= ' Microplastics: ' . $coastal['microplastics'];
                    }
                    if($coastal['mediumplastics']){
                        $coastal_string .= ' Mediumplastics: ' . $coastal['mediumplastics'];
                    }
                    if($coastal['microplastics']){
                        $coastal_string .= ' Microplastics: ' . $coastal['microplastics'];
                    }
                    if($coastal['macroplastics']){
                        $coastal_string .= ' Macroplastics: ' . $coastal['macroplastics'];
                    }
                    if($coastal['rope_small']){
                        $coastal_string .= ' Rope (small): ' . $coastal['rope_small'];
                    }
                    if($coastal['rope_medium']){
                        $coastal_string .= ' Rope (medium): ' . $coastal['rope_medium'];
                    }
                    if($coastal['rope_large']){
                        $coastal_string .= ' Rope (large): ' . $coastal['rope_large'];
                    }
                    if($coastal['fishing_gear_nets']){
                        $coastal_string .= ' Fishing Nets: ' . $coastal['fishing_gear_nets'];
                    }
                    if($coastal['buoys']){
                        $coastal_string .= ' Buoys: ' . $coastal['buoys'];
                    }
                    if($coastal['degraded_plasticbottle']){
                        $coastal_string .= ' Degraded plastic bottle: ' . $coastal['degraded_plasticbottle'];
                    }
                    if($coastal['degraded_plasticbag']){
                        $coastal_string .= ' Degraded plastic bag: ' . $coastal['degraded_plasticbag'];
                    }
                    if($coastal['degraded_straws']){
                        $coastal_string .= ' Degraded straws: ' . $coastal['degraded_straws'];
                    }
                    if($coastal['degraded_lighters']){
                        $coastal_string .= ' Degraded lighters: ' . $coastal['degraded_lighters'];
                    }
                    if($coastal['balloons']){
                        $coastal_string .= ' Balloons: ' . $coastal['balloons'];
                    }
                    if($coastal['lego']){
                        $coastal_string .= ' Lego: ' . $coastal['lego'];
                    }
                    if($coastal['shotgun_cartridges']){
                        $coastal_string .= ' Shutgun Cartridges: ' . $coastal['shotgun_cartridges'];
                    }
                    $photo['result_string'] .= $coastal_string;
                }

                if($photo['other_id']){
                    $other_string = '';
                    $other = Other::find($photo['other_id']);
                    if($other['dump']){
                        $other_string .= ' Large/Random Dump: ' . $other['dump'];
                    }
                    if($other['plastic']){
                        $other_string .= ' Unidentified Plastic: ' . $other['plastic'];
                    }
                    if($other['metal']){
                        $other_string .= ' Metal Object: ' . $other['metal'];
                    }
                    if($other['plastic_bags']){
                        $other_string .= ' Plastic Bags: ' . $other['plastic_bags'];
                    }
                    if($other['election_posters']){
                        $other_string .= ' Election Posters: ' . $other['election_posters'];
                    }
                    if($other['forsale_posters']){
                        $other_string .= ' For Sale Posters: ' . $other['forsale_posters'];
                    }
                    if($other['books']){
                        $other_string .= ' Books: ' . $other['books'];
                    }
                    if($other['magazines']){
                        $other_string .= ' Magazines: ' . $other['magazines'];
                    }
                    if($other['paper']){
                        $other_string .= ' Paper: ' . $other['paper'];
                    }
                    if($other['stationary']){
                        $other_string .= ' Stationary: ' . $other['stationary'];
                    }
                    if($other['hair_tie']){
                        $other_string .= ' Hair Tie: ' . $other['hair_tie'];
                    }
                    if($other['ear_plugs']){
                        $other_string .= ' Ear Plugs: ' . $other['ear_plugs'];
                    }
                    $photo['result_string'] .= $other_string;
                }

                if($photo['brands_id']){
                    $brands_string = '';
                    $brand = Brand::find($photo['brands_id']);
                    if($brand['adidas']){
                        $brands_string .= ' Adidas: ' . $brand['adidas'];
                    }
                    if($brand['amazon']){
                        $brands_string .= ' Amazon: ' . $brand['amazon'];
                    }
                    if($brand['apple']){
                        $brands_string .= ' Apple: ' . $brand['apple'];
                    }
                    if($brand['budweiser']){
                        $brands_string .= ' Budweiser: ' . $brand['budweiser'];
                    }
                    if($brand['coke']){
                        $brands_string .= ' Coke: ' . $brand['coke'];
                    }
                    if($brand['colgate']){
                        $brands_string .= ' Colgate: ' . $brand['colgate'];
                    }
                    if($brand['corona']){
                        $brands_string .= ' Corona: ' . $brand['corona'];
                    }
                    if($brand['fritolay']){
                        $brands_string .= ' Fritolay: ' . $brand['fritolay'];
                    }
                    if($brand['gillette']){
                        $brands_string .= ' Gillette: ' . $brand['gillette'];
                    }
                    if($brand['heineken']){
                        $brands_string .= ' Heineken: ' . $brand['heineken'];
                    }
                    if($brand['kellogs']){
                        $brands_string .= ' Kellogs: ' . $brand['kellogs'];
                    }
                    if($brand['lego']){
                        $brands_string .= ' Lego: ' . $brand['lego'];
                    }
                    if($brand['loreal']){
                        $brands_string .= ' Loreal: ' . $brand['loreal'];
                    }
                    if($brand['nescafe']){
                        $brands_string .= ' Nescafe: ' . $brand['nescafe'];
                    }
                    if($brand['nestle']){
                        $brands_string .= ' Nestle: ' . $brand['nestle'];
                    }
                    if($brand['marlboro']){
                        $brands_string .= ' Marlboro: ' . $brand['marlboro'];
                    }
                    if($brand['mcdonalds']){
                        $brands_string .= ' McDonalds: ' . $brand['mcdonalds'];
                    }
                    if($brand['nike']){
                        $brands_string .= ' Nike: ' . $brand['nike'];
                    }
                    if($brand['pepsi']){
                        $brands_string .= ' Pepsi: ' . $brand['pepsi'];
                    }
                    if($brand['redbull']){
                        $brands_string .= ' Redbull: ' . $brand['redbull'];
                    }
                    if($brand['samsung']){
                        $brands_string .= ' Samsung: ' . $brand['samsung'];
                    }
                    if($brand['subway']){
                        $brands_string .= ' Subway: ' . $brand['subway'];
                    }
                    if($brand['starbucks']){
                        $brands_string .= ' Starbucks: ' . $brand['starbucks'];
                    }
                    if($brand['tayto']){
                        $brands_string .= ' Tayto: ' . $brand['tayto'];
                    }
                    if($brand['applegreen']){
                        $brands_string .= ' Applegreen: ' . $brand['applegreen'];
                    }
                    if($brand['avoca']){
                        $brands_string .= ' Avoca: ' . $brand['avoca'];
                    }
                    if($brand['bewleys']){
                        $brands_string .= ' Bewleys: ' . $brand['bewleys'];
                    }
                    if($brand['brambels']){
                        $brands_string .= ' Brambels: ' . $brand['brambels'];
                    }
                    if($brand['butlers']){
                        $brands_string .= ' Butlers: ' . $brand['butlers'];
                    }
                    if($brand['cafe_nero']){
                        $brands_string .= ' Cafe_nero: ' . $brand['cafe_nero'];
                    }
                    if($brand['centra']){
                        $brands_string .= ' Centra: ' . $brand['centra'];
                    }
                    if($brand['costa']){
                        $brands_string .= ' Costa: ' . $brand['costa'];
                    }
                    if($brand['esquires']){
                        $brands_string .= ' Esquires: ' . $brand['esquires'];
                    }
                    if($brand['frank_and_honest']){
                        $brands_string .= ' Frank & Honest: ' . $brand['frank_and_honest'];
                    }
                    if($brand['insomnia']){
                        $brands_string .= ' Insomnia: ' . $brand['insomnia'];
                    }
                    if($brand['lolly_and_cookes']){
                        $brands_string .= ' Lolly & Cookes: ' . $brand['lolly_and_cookes'];
                    }
                    if($brand['obriens']){
                        $brands_string .= ' O Briens: ' . $brand['obriens'];
                    }
                    if($brand['supermacs']){
                        $brands_string .= ' Supermacs: ' . $brand['supermacs'];
                    }
                    if($brand['wilde_and_greene']){
                        $brands_string .= ' Wilde & Greene: ' . $brand['wilde_and_greene'];
                    }
                    $photo['result_string'] .= $brands_string;
                }
            }
            $photo->save();
        }
    }
}
