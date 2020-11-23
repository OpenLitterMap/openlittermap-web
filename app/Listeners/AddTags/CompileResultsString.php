<?php

namespace App\Listeners\AddTags;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Models\Photo;
use App\Models\Litter\Categories\Smoking as Smoking;
use App\Models\Litter\Categories\Alcohol as Alcohol;
use App\Models\Litter\Categories\Coffee as Coffee;
use App\Models\Litter\Categories\Food as Food;
use App\Models\Litter\Categories\SoftDrinks as SoftDrinks;
use App\Models\Litter\Categories\Sanitary as Sanitary;
use App\Models\Litter\Categories\Other as Other;
use App\Models\Litter\Categories\Coastal as Coastal;
use App\Models\Litter\Categories\Art as Art;
use App\Models\Litter\Categories\Brand as Brand;
use App\Models\Litter\Categories\TrashDog as TrashDog;
use App\Models\Litter\Categories\Dumping as Dumping;
use App\Models\Litter\Categories\Industrial as Industrial;

class CompileResultsString
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
     * Instead of having to query the database to get the data for each photo
     * We save the metadata on the photos table to speed up page load
     * and avoid additional requests
     *
     * Todo - Add translation keys instead of English text
     *
     * @param  object  $event
     * @return void
     */
    public function handle ($event)
    {
        $photo = Photo::find($event->photo_id);
        $result_string = '';

        if ($photo->smoking_id)
        {
            $smoking = Smoking::find($photo->smoking_id);
            $smoking_string = '';

            if ($smoking->butts) {
                $smoking_string .= 'Cigarette/Butts: ' . $smoking->butts;
            }
            if ($smoking->lighters) {
                $smoking_string .= ' Lighters: ' . $smoking->lighters;
            }
            if ($smoking->cigaretteBox) {
                $smoking_string .= ' Cigarette Box: ' . $smoking->cigaretteBox;
            }
            if ($smoking->tobaccoPouch) {
                $smoking_string .= ' Tobacco Pouch: ' . $smoking->tobaccoPouch;
            }
            if ($smoking->smoking_plastic) {
                $smoking_string .= ' Cellophane: ' . $smoking->smoking_plastic;
            }
            if ($smoking->filters) {
                $smoking_string .= ' Filters: ' . $smoking->filters;
            }
            if ($smoking->smokingOther) {
                $smoking_string .= ' Smoking (Other): ' . $smoking->smokingOther;
            }

            $result_string .= $smoking_string;
        }

        if ($photo->food_id)
        {
            $food = Food::find($photo->food_id);
            $food_string = '';

            if ($food->sweetWrappers) {
                $food_string .= ' Sweet Wrappers: ' . $food->sweetWrappers;
            }
            if ($food->cardboardFoodPackaging) {
                $food_string .= ' Cardboard Food Packaging: ' . $food->cardboardFoodPackaging;
            }
            if ($food->plasticFoodPackaging) {
                $food_string .= ' Plastic Food Packaging: ' . $food->plasticFoodPackaging;
            }
            if ($food->paperFoodPackaging) {
                $food_string .= ' Paper Food Packaging: ' . $food->paperFoodPackaging;
            }
            if ($food->plasticCutlery) {
                $food_string .= ' Plastic Cutlery: ' . $food->plasticCutlery;
            }
            if ($food->crisp_small) {
                $food_string .= ' Crisps/Chip packet (small): ' . $food->crisp_small;
            }
            if ($food->crisp_large) {
                $food_string .= ' Crisps/Chip packet (large): ' . $food->crisp_large;
            }
            if ($food->styrofoam_plate) {
                $food_string .= ' Styrofoam: ' . $food->styrofoam_plate;
            }
            if ($food->napkins) {
                $food_string .= ' Napkins: ' . $food->napkins;
            }
            if ($food->sauce_packet) {
                $food_string .= ' Sauce Packet: ' . $food->sauce_packet;
            }
            if ($food->glass_jar) {
                $food_string .= ' Glass Jar: ' . $food->glass_jar;
            }
            if ($food->glass_jar_lid) {
                $food_string .= ' Glass Jar Lid: ' . $food->glass_jar_lid;
            }
            if ($food->foodOther) {
                $food_string .= ' Food (other): ' . $food->foodOther;
            }
            if ($food->pizza_box) {
                $food_string .= ' Pizza Box: ' . $food->pizza_box;
            }
            if ($food->aluminium_foil) {
                $food_string .= ' Aluminium Foil: ' . $food->aluminium_foil;
            }

            $result_string .= $food_string;
        }

        if ($photo->coffee_id)
        {
            $coffee_string = '';
            $coffee = Coffee::find($photo->coffee_id);

            if ($coffee->coffeeCups) {
                $coffee_string .= ' Coffee Cups: ' . $coffee->coffeeCups;
            }
            if ($coffee->coffeeLids) {
                $coffee_string .= ' Coffee Lids: ' . $coffee->coffeeLids;
            }
            if ($coffee->coffeeOther) {
                $coffee_string .= ' Coffee (other): ' . $coffee->coffeeOther;
            }

            $result_string .= $coffee_string;
        }

        if ($photo->softdrinks_id)
        {
            $softdrinks_string = '';
            $softdrinks = SoftDrinks::find($photo->softdrinks_id);

            if ($softdrinks->waterBottle) {
                $softdrinks_string .= ' Plastic Water Bottle: ' . $softdrinks->waterBottle;
            }
            if ($softdrinks->fizzyDrinkBottle) {
                $softdrinks_string .= ' Plastic FizzyDrink Bottle: ' . $softdrinks->fizzyDrinkBottle;
            }
            if ($softdrinks->bottleLid) {
                $softdrinks_string .= ' Plastic Lid: ' . $softdrinks->bottleLid;
            }
            if ($softdrinks->bottleLabel) {
                $softdrinks_string .= ' Plastic Label: ' . $softdrinks->bottleLabel;
            }
            if ($softdrinks->tinCan) {
                $softdrinks_string .= ' Can: ' . $softdrinks->tinCan;
            }
            if ($softdrinks->sportsDrink) {
                $softdrinks_string .= ' Sports Drink: ' . $softdrinks->sportsDrink;
            }
            if ($softdrinks->straws) {
                $softdrinks_string .= ' Straws: ' . $softdrinks->straws;
            }
            if ($softdrinks->plasticCups) {
                $softdrinks_string .= ' Plastic Cups: ' . $softdrinks->plasticCups;
            }
            if ($softdrinks->plasticCupTops) {
                $softdrinks_string .= ' Plastic Cup Tops: ' . $softdrinks->plasticCupTops;
            }
            if ($softdrinks->milk_bottle) {
                $softdrinks_string .= ' Milk Bottle: ' . $softdrinks->milk_bottle;
            }
            if ($softdrinks->milk_carton) {
                $softdrinks_string .= ' Milk Carton: ' . $softdrinks->milk_carton;
            }
            if ($softdrinks->pape_cups) {
                $softdrinks_string .= ' Paper Cups: ' . $softdrinks->paper_cups;
            }
            if ($softdrinks->juice_cartons) {
                $softdrinks_string .= ' Juice Cartons: ' . $softdrinks->juice_cartons;
            }
            if ($softdrinks->juice_packet) {
                $softdrinks_string .= ' Juice Packet: ' . $softdrinks->juice_packet;
            }
            if ($softdrinks->ice_tea_bottles) {
                $softdrinks_string .= ' Ice Tea: ' . $softdrinks->ice_tea_bottles;
            }
            if ($softdrinks->ice_tea_can) {
                $softdrinks_string .= ' Ice Tea Can: ' . $softdrinks->ice_tea_can;
            }
            if ($softdrinks->energy_can) {
                $softdrinks_string .= ' Energy Can: ' . $softdrinks->energy_can;
            }

            $result_string .= $softdrinks_string;;
        }

        if ($photo->alcohol_id)
        {
            $alcohol_string = '';
            $alcohol = Alcohol::find($photo->alcohol_id);

            if ($alcohol->beerBottle) {
                $alcohol_string .= ' Beer Bottle: ' . $alcohol->beerBottle;
            }
            if ($alcohol->wineBottle) {
                $alcohol_string .= ' Wine Bottle: ' . $alcohol->wineBottle;
            }
            if ($alcohol->spiritBottle) {
                $alcohol_string .= ' Spirit Bottle: ' . $alcohol->spiritBottle;
            }
            if ($alcohol->beerCan) {
                $alcohol_string .= ' Beer Can: ' . $alcohol->beerCan;
            }
            if ($alcohol->brokenGlass) {
                $alcohol_string .= ' Broken Glass: ' . $alcohol->brokenGlass;
            }
            if ($alcohol->paperCardAlcoholPackaging) {
                $alcohol_string .= ' Paper/Card Alcohol Packaging: ' . $alcohol->paperCardAlcoholPackaging;
            }
            if ($alcohol->plasticAlcoholPackaging) {
                $alcohol_string .= ' Plastic Alcohol Packaging: ' . $alcohol->plasticAlcoholPackaging;
            }
            if ($alcohol->bottleTops) {
                $alcohol_string .= ' Beer Bottle Tops: ' . $alcohol->bottleTops;
            }
            if ($alcohol->wine) {
                $alcohol_string .= ' Beer Bottle: ' . $alcohol->wine;
            }
            if ($alcohol->alcoholOther) {
                $alcohol_string .= ' Alcohol (other): ' . $alcohol->alcoholOther;
            }

            $result_string .= $alcohol_string;
        }

        if ($photo->sanitary_id)
        {
            $sanitary_string = '';
            $sanitary = Sanitary::find($photo->sanitary_id);

            if ($sanitary->gloves) {
                $sanitary_string .= ' Gloves: ' . $sanitary->gloves;
            }
            if ($sanitary->facemask) {
                $sanitary_string .= ' Facemask: ' . $sanitary->facemask;
            }
            if ($sanitary->condoms) {
                $sanitary_string .= ' Condoms: ' . $sanitary->condoms;
            }
            if ($sanitary->nappies) {
                $sanitary_string .= ' Nappies: ' . $sanitary->nappies;
            }
            if ($sanitary->menstral) {
                $sanitary_string .= ' Menstral: ' . $sanitary->menstral;
            }
            if ($sanitary->deodorant) {
                $sanitary_string .= ' Deodorant: ' . $sanitary->deodorant;
            }
            if ($sanitary->ear_swabs) {
                $sanitary_string .= ' Ear swabs: ' . $sanitary->ear_swabs;
            }
            if ($sanitary->tooth_pick) {
                $sanitary_string .= ' Tooth pick: ' . $sanitary->tooth_pick;
            }
            if ($sanitary->sanitaryOther) {
                $sanitary_string .= ' Sanitary (other): ' . $sanitary->sanitaryOther;
            }

            $result_string .= $sanitary_string;
        }

        if ($photo->coastal_id)
        {
            $coastal_string = '';
            $coastal = Coastal::find($photo->coastal_id);

            if ($coastal->microplastics) {
                $coastal_string .= ' Microplastics: ' . $coastal->microplastics;
            }
            if ($coastal->mediumplastics) {
                $coastal_string .= ' Mediumplastics: ' . $coastal->mediumplastics;
            }
            if ($coastal->macroplastics) {
                $coastal_string .= ' Macroplastics: ' . $coastal->macroplastics;
            }
            if ($coastal->rope_small) {
                $coastal_string .= ' Rope (small): ' . $coastal->rope_small;
            }
            if ($coastal->rope_medium) {
                $coastal_string .= ' Rope (medium): ' . $coastal->rope_medium;
            }
            if ($coastal->rope_large) {
                $coastal_string .= ' Rope (large): ' . $coastal->rope_large;
            }
            if ($coastal->fishing_gear_nets) {
                $coastal_string .= ' Fishing Nets: ' . $coastal->fishing_gear_nets;
            }
            if ($coastal->buoys) {
                $coastal_string .= ' Buoys: ' . $coastal->buoys;
            }
            if ($coastal->degraded_plasticbottle) {
                $coastal_string .= ' Degraded plastic bottle: ' . $coastal->degraded_plasticbottle;
            }
            if ($coastal->degraded_plasticbag) {
                $coastal_string .= ' Degraded plastic bag: ' . $coastal->degraded_plasticbag;
            }
            if ($coastal->degraded_straws) {
                $coastal_string .= ' Degraded straws: ' . $coastal->degraded_straws;
            }
            if ($coastal->degraded_lighters) {
                $coastal_string .= ' Degraded lighters: ' . $coastal->degraded_lighters;
            }
            if ($coastal->balloons) {
                $coastal_string .= ' Balloons: ' . $coastal->balloons;
            }
            if ($coastal->lego) {
                $coastal_string .= ' Lego: ' . $coastal->lego;
            }
            if ($coastal->shotgun_cartridges) {
                $coastal_string .= ' Shutgun Cartridges: ' . $coastal->shotgun_cartridges;
            }

            $result_string .= $coastal_string;
        }

        if ($photo->other_id)
        {
            $other_string = '';
            $other = Other::find($photo->other_id);

            if ($other->dump) {
                $other_string .= ' Large/Random Dump: ' . $other->dump;
            }
            if ($other->plastic) {
                $other_string .= ' Unidentified Plastic: ' . $other->plastic;
            }
            if ($other->metal) {
                $other_string .= ' Metal Object: ' . $other->metal;
            }
            if ($other->plastic_bags) {
                $other_string .= ' Plastic Bags: ' . $other->plastic_bags;
            }
            if ($other->election_posters) {
                $other_string .= ' Election Posters: ' . $other->election_posters;
            }
            if ($other->forsale_posters) {
                $other_string .= ' For Sale Posters: ' . $other->forsale_posters;
            }
            if ($other->books) {
                $other_string .= ' Books: ' . $other->books;
            }
            if ($other->magazines) {
                $other_string .= ' Magazines: ' . $other->magazines;
            }
            if ($other->paper) {
                $other_string .= ' Paper: ' . $other->paper;
            }
            if ($other->stationary) {
                $other_string .= ' Stationary: ' . $other->stationary;
            }
            if ($other->hair_tie) {
                $other_string .= ' Hair Tie: ' . $other->hair_tie;
            }
            if ($other->ear_plugs) {
                $other_string .= ' Ear Plugs: ' . $other->ear_plugs;
            }
            if ($other->bags_litter) {
                $other_string .= ' Bags of Litter: ' . $other->bags_litter;
            }
            if ($other->cable_tie) {
                $other_string .= 'Cable Tie: ' . $other->cable_tie;
            }
            if ($other->tyre) {
                $other_string .= ' Tyre: ' . $other->tyre;
            }
            if ($other->overflowing_bins) {
                $other_string .= ' Overflowing Bins: ' . $other->overflowing_bins;
            }
            if ($other->random_litter) {
                $other_string .= 'Random Litter: ' . $other->random_litter;
            }


            $result_string .= $other_string;
        }

        if ($photo->brands_id)
        {
            $brands_string = '';
            $brand = Brand::find($photo->brands_id);

            if ($brand->adidas) {
                $brands_string .= ' Adidas: ' . $brand->adidas;
            }
            if ($brand->amazon) {
                $brands_string .= ' Amazon: ' . $brand->amazon;
            }
            if ($brand->apple) {
                $brands_string .= ' Apple: ' . $brand->apple;
            }
            if ($brand->budweiser) {
                $brands_string .= ' Budweiser: ' . $brand->budweiser;
            }
            if ($brand->coke) {
                $brands_string .= ' Coke: ' . $brand->coke;
            }
            if ($brand->colgate) {
                $brands_string .= ' Colgate: ' . $brand->colgate;
            }
            if ($brand->corona) {
                $brands_string .= ' Corona: ' . $brand->corona;
            }
            if ($brand->fritolay) {
                $brands_string .= ' Fritolay: ' . $brand->fritolay;
            }
            if ($brand->gillette) {
                $brands_string .= ' Gillette: ' . $brand->gillette;
            }
            if ($brand->heineken) {
                $brands_string .= ' Heineken: ' . $brand->heineken;
            }
            if ($brand->kellogs) {
                $brands_string .= ' Kellogs: ' . $brand->kellogs;
            }
            if ($brand->lego) {
                $brands_string .= ' Lego: ' . $brand->lego;
            }
            if ($brand->loreal) {
                $brands_string .= ' Loreal: ' . $brand->loreal;
            }
            if ($brand->nescafe) {
                $brands_string .= ' Nescafe: ' . $brand->nescafe;
            }
            if ($brand->nestle) {
                $brands_string .= ' Nestle: ' . $brand->nestle;
            }
            if ($brand->marlboro) {
                $brands_string .= ' Marlboro: ' . $brand->marlboro;
            }
            if ($brand->mcdonalds) {
                $brands_string .= ' McDonalds: ' . $brand->mcdonalds;
            }
            if ($brand->nike) {
                $brands_string .= ' Nike: ' . $brand->nike;
            }
            if ($brand->pepsi) {
                $brands_string .= ' Pepsi: ' . $brand->pepsi;
            }
            if ($brand->redbull) {
                $brands_string .= ' Redbull: ' . $brand->redbull;
            }
            if ($brand->samsung) {
                $brands_string .= ' Samsung: ' . $brand->samsung;
            }
            if ($brand->subway) {
                $brands_string .= ' Subway: ' . $brand->subway;
            }
            if ($brand->starbucks) {
                $brands_string .= ' Starbucks: ' . $brand->starbucks;
            }
            if ($brand->tayto) {
                $brands_string .= ' Tayto: ' . $brand->tayto;
            }
            if ($brand->applegreen) {
                $brands_string .= ' Applegreen: ' . $brand->applegreen;
            }
            if ($brand->avoca) {
                $brands_string .= ' Avoca: ' . $brand->avoca;
            }
            if ($brand->bewleys) {
                $brands_string .= ' Bewleys: ' . $brand->bewleys;
            }
            if ($brand->brambels) {
                $brands_string .= ' Brambels: ' . $brand->brambels;
            }
            if ($brand->butlers) {
                $brands_string .= ' Butlers: ' . $brand->butlers;
            }
            if ($brand->cafe_nero) {
                $brands_string .= ' Cafe_nero: ' . $brand->cafe_nero;
            }
            if ($brand->centra) {
                $brands_string .= ' Centra: ' . $brand->centra;
            }
            if ($brand->costa) {
                $brands_string .= ' Costa: ' . $brand->costa;
            }
            if ($brand->esquires) {
                $brands_string .= ' Esquires: ' . $brand->esquires;
            }
            if ($brand->frank_and_honest) {
                $brands_string .= ' Frank & Honest: ' . $brand->frank_and_honest;
            }
            if ($brand->insomnia) {
                $brands_string .= ' Insomnia: ' . $brand->insomnia;
            }
            if ($brand->lolly_and_cookes) {
                $brands_string .= ' Lolly & Cookes: ' . $brand->lolly_and_cookes;
            }
            if ($brand->obriens) {
                $brands_string .= ' O Briens: ' . $brand->obriens;
            }
            if ($brand->supermacs) {
                $brands_string .= ' Supermacs: ' . $brand->supermacs;
            }
            if ($brand->wilde_and_greene) {
                $brands_string .= ' Wilde & Greene: ' . $brand->wilde_and_greene;
            }

            $result_string .= $brands_string;
        }

        if ($photo->dumping_id)
        {
            $dumping_string = '';
            $dumping = Dumping::find($photo->dumping_id);

            if ($dumping->small) {
                $dumping_string .= ' Dumping (small)';
            }
            if ($dumping->medium) {
                $dumping_string .= ' Dumping (medium)';
            }
            if ($dumping->large) {
                $dumping_string .= ' Dumping (large)';
            }

            $result_string .= $dumping_string;
        }

        if ($photo->industrial_id)
        {
            $industrial_string = '';
            $industrial = Industrial::find($photo->industrial_id);

            if ($industrial->oil) {
                $industrial_string .= ' Oil: ' . $industrial->oil;
            }
            if ($industrial->chemical) {
                $industrial_string .= ' Chemical: ' . $industrial->chemical;
            }
            if ($industrial->plastic) {
                $industrial_string .= ' Plastic: ' . $industrial->industrial_plastic;
            }
            if ($industrial->bricks) {
                $industrial_string .= ' Bricks: ' . $industrial->bricks;
            }
            if ($industrial->tape) {
                $industrial_string .= ' Tape: ' . $industrial->tape;
            }
            if ($industrial->other) {
                $industrial_string .= ' Other: ' . $industrial->industrial_other;
            }

            $result_string .= $industrial_string;
        }

        $photo->result_string = $result_string;
        $photo->save();
    }
}
