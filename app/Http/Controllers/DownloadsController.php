<?php

namespace App\Http\Controllers;

use Excel;
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
use App\Models\Litter\Categories\Dumping;
use App\Models\Litter\Categories\Industrial;

use App\Models\Photo;

use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Location\City;
use Illuminate\Http\Request;

class DownloadsController extends Controller
{

    public function getDataByState ($state = null)
    {
    	if ($state)
    	{
    		// State only
    		// need to pass the variable down the chain
	    	Excel::create('Open Litter Map', function($excel) use ($state) {

	    		$excel->sheet('OLM', function($sheet) use ($state) {

	    			$state = State::where('state', $state)->first();

			    	$photos = Photo::where([
			    		['state_id', $state->id],
			    		['verified', '>', 0]
			    	])->get();

			    	$export = [];
			    	foreach($photos as $index => $photo) {
			    		$index++;
			    		$export[$index]['id'] = $index;
			    		$export[$index]['verification'] = $photo->verified;
			    		$export[$index]['phone'] = $photo->model;
			    		$export[$index]['datetime'] = $photo->datetime;
			    		$export[$index]['lat'] = $photo->lat;
			    		$export[$index]['lon'] = $photo->lon;
			    		$export[$index]['city'] = $photo->city;
			    		$export[$index]['state'] = $photo->county;
			    		$export[$index]['country'] = $photo->country;
			    		$export[$index]['remaining_beta'] = $photo->remaining;
			    		$export[$index]['address'] = $photo->display_name;
			    		$export[$index]['total_litter'] = $photo->total_litter;

			    		$export[$index]['cigaretteButts'] = 0;
			    		$export[$index]['lighters'] = 0;
			    		$export[$index]['cigaretteBox'] = 0;
			    		$export[$index]['tobaccoPouch'] = 0;
			    		$export[$index]['papers_filters'] = 0;
			    		$export[$index]['plastic_smoking_pk'] = 0;
			    		$export[$index]['filters'] = 0;
			    		$export[$index]['filterbox'] = 0;
			    		$export[$index]['smokingOther'] = 0;

			    		$export[$index]['sweetWrappers'] = 0;
			    		$export[$index]['paperFoodPackaging'] = 0;
			    		$export[$index]['plasticFoodPackaging'] = 0;
			    		$export[$index]['plasticCutlery'] = 0;
			    		$export[$index]['crisp_small'] = 0;
			    		$export[$index]['crisp_large'] = 0;
			    		$export[$index]['styrofoam_plate'] = 0;
			    		$export[$index]['napkins'] = 0;
			    		$export[$index]['sauce_packet'] = 0;
			    		$export[$index]['glass_jar'] = 0;
			    		$export[$index]['glass_jar_lid'] = 0;
			    		$export[$index]['foodOther'] = 0;

			    		$export[$index]['coffeeCups'] = 0;
			    		$export[$index]['coffeeLids'] = 0;
			    		$export[$index]['coffeeOther'] = 0;

			    		$export[$index]['beerCan'] = 0;
			    		$export[$index]['beerBottle'] = 0;
			    		$export[$index]['spiritBottle'] = 0;
			    		$export[$index]['wineBottle'] = 0;
			    		$export[$index]['brokenGlass'] = 0;
			    		$export[$index]['paperCardAlcoholPackaging'] = 0;
			    		$export[$index]['plasticAlcoholPackaging'] = 0;
			    		$export[$index]['bottleTops'] = 0;
			    		$export[$index]['alcoholOther'] = 0;

			    		$export[$index]['plasticWaterBottle'] = 0;
			    		$export[$index]['fizzyDrinkBottle'] = 0;
			    		$export[$index]['bottleLid'] = 0;
			    		$export[$index]['bottleLabel'] = 0;
			    		$export[$index]['tinCan'] = 0;
			    		$export[$index]['sportsDrink'] = 0;
			    		$export[$index]['straws'] = 0;
			    		$export[$index]['plastic_cups'] = 0;
			    		$export[$index]['plastic_cup_tops'] = 0;
			    		$export[$index]['milk_bottle'] = 0;
			    		$export[$index]['milk_carton'] = 0;
			    		$export[$index]['paper_cups'] = 0;
			    		$export[$index]['juice_cartons'] = 0;
			    		$export[$index]['juice_bottles'] = 0;
			    		$export[$index]['juice_packet'] = 0;
			    		$export[$index]['ice_tea_bottles'] = 0;
			    		$export[$index]['ice_tea_can'] = 0;
			    		$export[$index]['energy_can'] = 0;
			    		$export[$index]['softDrinkOther'] = 0;

			    		$export[$index]['gloves'] = 0;
			    		$export[$index]['facemasks'] = 0;
			    		$export[$index]['condoms'] = 0;
			    		$export[$index]['mental'] = 0;
			    		$export[$index]['deodorant'] = 0;
			    		$export[$index]['ear_swabs'] = 0;
			    		$export[$index]['tooth_pick'] = 0;
			    		$export[$index]['tooth_brush'] = 0;
			    		$export[$index]['sanitaryOther'] = 0;

			    		$export[$index]['dogshit'] = 0;
			    		$export[$index]['Random_dump'] = 0;
			    		$export[$index]['No_id_plastic'] = 0;
			    		$export[$index]['Metal_object'] = 0;
			    		$export[$index]['plastic_bags'] = 0;
			    		$export[$index]['election_posters'] = 0;
			    		$export[$index]['forsale_posters'] = 0;
			    		$export[$index]['books'] = 0;
			    		$export[$index]['magazines'] = 0;
			    		$export[$index]['paper'] = 0;
			    		$export[$index]['stationary'] = 0;
			    		$export[$index]['washing_up'] = 0;
			    		$export[$index]['hair_tie'] = 0;
			    		$export[$index]['ear_plugs'] = 0;
			    		$export[$index]['batteries'] = 0;
			    		$export[$index]['elec_small'] = 0;
			    		$export[$index]['elec_large'] = 0;
			    		$export[$index]['Other_Unknown'] = 0;

			    		$export[$index]['microplastics'] = 0;
			    		$export[$index]['mediumplastics'] = 0;
			    		$export[$index]['macroplastics'] = 0;
			    		$export[$index]['rope_small'] = 0;
			    		$export[$index]['rope_medium'] = 0;
			    		$export[$index]['rope_large'] = 0;
			    		$export[$index]['fishing_gear_nets'] = 0;
			    		$export[$index]['buoys'] = 0;
			    		$export[$index]['degraded_plasticbottle'] = 0;
			    		$export[$index]['degraded_plasticbag'] = 0;
			    		$export[$index]['degraded_straws'] = 0;
			    		$export[$index]['degraded_lighters'] = 0;
			    		$export[$index]['baloons'] = 0;
			    		$export[$index]['lego'] = 0;
			    		$export[$index]['shotgun_cartridges'] = 0;
			    		$export[$index]['coastal_other'] = 0;

			    		$export[$index]['adidas'] = 0;
			    		$export[$index]['amazon'] = 0;
			    		$export[$index]['aldi'] = 0;
			    		$export[$index]['apple'] = 0;
			    		$export[$index]['applegreen'] = 0;
			    		$export[$index]['asahi'] = 0;
			    		$export[$index]['avoca'] = 0;

			    		$export[$index]['ballygowan'] = 0;
			    		$export[$index]['bewleys'] = 0;
			    		$export[$index]['brambles'] = 0;
			    		$export[$index]['budweiser'] = 0;
			    		$export[$index]['bulmers'] = 0;
			    		$export[$index]['burgerking'] = 0;
			    		$export[$index]['butlers'] = 0;

			    		$export[$index]['cadburys'] = 0;
			    		$export[$index]['cafe_nero'] = 0;
			    		$export[$index]['camel'] = 0;
			    		$export[$index]['carlsberg'] = 0;
			    		$export[$index]['centra'] = 0;
			    		$export[$index]['coke'] = 0;
			    		$export[$index]['circlek'] = 0;
			    		$export[$index]['coles'] = 0;
			    		$export[$index]['colgate'] = 0;
			    		$export[$index]['corona'] = 0;
			    		$export[$index]['costa'] = 0;

			    		$export[$index]['doritos'] = 0;
			    		$export[$index]['drpepper'] = 0;
			    		$export[$index]['dunnes'] = 0;
			    		$export[$index]['duracell'] = 0;
			    		$export[$index]['durex'] = 0;

			    		$export[$index]['esquires'] = 0;

			    		$export[$index]['frank_and_honest'] = 0;
			    		$export[$index]['fritolay'] = 0;

			    		$export[$index]['gatorade'] = 0;
			    		$export[$index]['gillette'] = 0;
			    		$export[$index]['guinness'] = 0;

			    		$export[$index]['haribo'] = 0;
			    		$export[$index]['heineken'] = 0;

			    		$export[$index]['insomnia'] = 0;

			    		$export[$index]['kellogs'] = 0;
			    		$export[$index]['kfc'] = 0;

			    		$export[$index]['lego'] = 0;
			    		$export[$index]['lidl'] = 0;
			    		$export[$index]['lindenvillage'] = 0;
			    		$export[$index]['lolly_and_cookes'] = 0;
			    		$export[$index]['loreal'] = 0;
			    		$export[$index]['lucozade'] = 0;

			    		$export[$index]['nero'] = 0;
			    		$export[$index]['nescafe'] = 0;
			    		$export[$index]['nestle'] = 0;

			    		$export[$index]['marlboro'] = 0;
			    		$export[$index]['mars'] = 0;
			    		$export[$index]['mcdonalds'] = 0;

			    		$export[$index]['nike'] = 0;

			    		$export[$index]['obriens'] = 0;

			    		$export[$index]['pepsi'] = 0;
			    		$export[$index]['powerade'] = 0;

			    		$export[$index]['redbull'] = 0;
			    		$export[$index]['ribena'] = 0;

			    		$export[$index]['samsung'] = 0;
			    		$export[$index]['sainsburys'] = 0;
			    		$export[$index]['spar'] = 0;
			    		$export[$index]['subway'] = 0;
			    		$export[$index]['supermacs'] = 0;
			    		$export[$index]['supervalu'] = 0;
			    		$export[$index]['starbucks'] = 0;

			    		$export[$index]['tayto'] = 0;
			    		$export[$index]['tesco'] = 0;
			    		$export[$index]['thins'] = 0;

			    		$export[$index]['volvic'] = 0;

			    		$export[$index]['waitrose'] = 0;
			    		$export[$index]['walkers'] = 0;
			    		$export[$index]['woolworths'] = 0;
			    		$export[$index]['wilde_and_greene'] = 0;
			    		$export[$index]['wrigleys'] = 0;

			    		if($photo['smoking_id']) {
			    			$smoking = Smoking::find($photo['smoking_id']);
			    			if($smoking['butts']) {
			    				$export[$index]['cigaretteButts'] = $smoking['butts'];
			    			}
			    			if($smoking['lighters']) {
			    				$export[$index]['lighters'] = $smoking['lighters'];
			    			}
			    			if($smoking['cigaretteBox']) {
			    				$export[$index]['cigaretteBox'] = $smoking['cigaretteBox'];
			    			}
			    			if($smoking['tobaccoPouch']) {
			    				$export[$index]['tobaccoPouch'] = $smoking['tobaccoPouch'];
			    			}
			    			if($smoking['skins']) {
			    				$export[$index]['papers_filters'] = $smoking['skins'];
			    			}
			    			if($smoking['plastic']) {
			    				$export[$index]['plastic_smoking_pk'] = $smoking['plastic'];
			    			}
			    			if($smoking['filters']) {
			    				$export[$index]['filters'] = $smoking['filters'];
			    			}
			    			if($smoking['filterbox']) {
			    				$export[$index]['filterbox'] = $smoking['filterbox'];
			    			}
			    			if($smoking['smokingOther']) {
			    				$export[$index]['smokingOther'] = $smoking['smokingOther'];
			    			}
			    		}

			    		if($photo['food_id']) {
			    			$food = Food::find($photo['food_id']);
			    			if($food['sweetWrappers']) {
			    				$export[$index]['sweetWrappers'] = $food['sweetWrappers'];
			    			}
			    			if($food['paperFoodPackaging']) {
			    				$export[$index]['paperFoodPackaging'] = $food['paperFoodPackaging'];
			    			}
			    			if($food['plasticFoodPackaging']) {
			    				$export[$index]['plasticFoodPackaging'] = $food['plasticFoodPackaging'];
			    			}
			    			if($food['plasticCutlery']) {
			    				$export[$index]['plasticCutlery'] = $food['plasticCutlery'];
			    			}
			    			if($food['crisp_small']) {
			    				$export[$index]['crisp_small'] = $food['crisp_small'];
			    			}
			    			if($food['crisp_large']) {
			    				$export[$index]['crisp_large'] = $food['crisp_large'];
			    			}
			    			if($food['styrofoam_plate']) {
			    				$export[$index]['styrofoam_plate'] = $food['styrofoam_plate'];
			    			}
			    			if($food['napkins']) {
			    				$export[$index]['napkins'] = $food['napkins'];
			    			}
			    			if($food['sauce_packet']) {
			    				$export[$index]['sauce_packet'] = $food['sauce_packet'];
			    			}
			    			if($food['glass_jar']) {
			    				$export[$index]['glass_jar'] = $food['glass_jar'];
			    			}
			    			if($food['glass_jar_lid']) {
			    				$export[$index]['glass_jar_lid'] = $food['glass_jar_lid'];
			    			}
			    			if($food['foodOther']) {
			    				$export[$index]['foodOther'] = $food['foodOther'];
			    			}
			    		}

			    		if($photo['coffee_id']) {
			    			$coffee = Coffee::find($photo['coffee_id']);
			    			if($coffee['coffeeCups']) {
			    				$export[$index]['coffeeCups'] = $coffee['coffeeCups'];
			    			}
			    			if($coffee['coffeeLids']) {
			    				$export[$index]['coffeeLids'] = $coffee['coffeeLids'];
			    			}
			    			if($coffee['coffeeOther']) {
			    				$export[$index]['coffeeOther'] = $coffee['coffeeOther'];
			    			}
			    		}

			    		if($photo['alcohol_id']) {
			    			$alcohol = Alcohol::find($photo['alcohol_id']);
			    			if($alcohol['beerBottle']) {
			    				$export[$index]['beerBottle'] = $alcohol['beerBottle'];
			    			}
			    			if($alcohol['spiritBottle']) {
			    				$export[$index]['spiritBottle'] = $alcohol['spiritBottle'];
			    			}
			    			if($alcohol['beerCan']) {
			    				$export[$index]['beerCan'] = $alcohol['beerCan'];
			    			}
			    			if($alcohol['brokenGlass']) {
			    				$export[$index]['brokenGlass_alcohol'] = $alcohol['brokenGlass'];
			    			}
			    			if($alcohol['paperCardAlcoholPackaging']) {
			    				$export[$index]['paperCardAlcoholPackaging'] = $alcohol['paperCardAlcoholPackaging'];
			    			}
			    			if($alcohol['plasticAlcoholPackaging']) {
			    				$export[$index]['plasticAlcoholPackaging'] = $alcohol['plasticAlcoholPackaging'];
			    			}
			    			if($alcohol['bottleTops']) {
			    				$export[$index]['bottleTops'] = $alcohol['bottleTops'];
			    			}
			    			if($alcohol['wineBottle']) {
			    				$export[$index]['wineBottle'] = $alcohol['wineBottle'];
			    			}
			    			if($alcohol['alcoholOther']) {
			    				$export[$index]['alcoholOther'] = $alcohol['alcoholOther'];
			    			}
			    		}

			    		if($photo["softdrinks_id"]) {
			    			$softdrinks = SoftDrinks::find($photo["softdrinks_id"]);
			    			if($softdrinks['waterBottle']) {
			    				$export[$index]['plasticWaterBottle'] = $softdrinks["waterBottle"];
			    			}
			    			if($softdrinks['fizzyDrinkBottle']) {
			    				$export[$index]['fizzyDrinkBottle'] = $softdrinks["fizzyDrinkBottle"];
			    			}
			    			if($softdrinks['bottleLid']) {
			    				$export[$index]['bottleLid'] = $softdrinks["bottleLid"];
			    			}
			    			if($softdrinks['bottleLabel']) {
			    				$export[$index]['bottleLabel'] = $softdrinks["bottleLabel"];
			    			}
			    			if($softdrinks['tinCan']) {
			    				$export[$index]['tinCan'] = $softdrinks["tinCan"];
			    			}
			    			if($softdrinks['sportsDrink']) {
			    				$export[$index]['sportsDrink'] = $softdrinks["sportsDrink"];
			    			}
			    			if($softdrinks['paper_cups']) {
			    				$export[$index]['paper_cups'] = $softdrinks["paper_cups"];
			    			}
			    			if($softdrinks['juice_cartons']) {
			    				$export[$index]['juice_cartons'] = $softdrinks["juice_cartons"];
			    			}
			    			if($softdrinks['juice_bottles']) {
			    				$export[$index]['juice_bottles'] = $softdrinks["juice_bottles"];
			    			}
			    			if($softdrinks['juice_packet']) {
			    				$export[$index]['juice_packet'] = $softdrinks["juice_packet"];
			    			}
			    			if($softdrinks['ice_tea_bottles']) {
			    				$export[$index]['ice_tea_bottles'] = $softdrinks["ice_tea_bottles"];
			    			}
			    			if($softdrinks['ice_tea_can']) {
			    				$export[$index]['ice_tea_can'] = $softdrinks["ice_tea_can"];
			    			}
			    			if($softdrinks['energy_can']) {
			    				$export[$index]['energy_can'] = $softdrinks["energy_can"];
			    			}
			    			if($softdrinks['softDrinkOther']) {
			    				$export[$index]['softDrinkOther'] = $softdrinks["softDrinkOther"];
			    			}
			    		}

			    		if($photo["sanitary_id"]){
			    			$sanitary = Sanitary::find($photo["sanitary_id"]);
			    			if ($sanitary['gloves']) {
			    				$export[$index]['gloves'] = $sanitary['gloves'];
			    			}
			    			if ($sanitary['facemasks']) {
			    				$export[$index]['facemasks'] = $sanitary['facemasks'];
			    			}
			    			if($sanitary["condoms"]) {
			    				$export[$index]["condoms"] = $sanitary["condoms"];
			    			}
			    			if($sanitary["nappies"]) {
			    				$export[$index]["nappies"] = $sanitary["nappies"];
			    			}
			    			if($sanitary["menstral"]) {
			    				$export[$index]["menstral"] = $sanitary["menstral"];
			    			}
			    			if($sanitary["deodorant"]) {
			    				$export[$index]["deodorant"] = $sanitary["deodorant"];
			    			}
			    			if($sanitary["sanitaryOther"]) {
			    				$export[$index]["sanitaryOther"] = $sanitary["sanitaryOther"];
			    			}
			    		}

			    		if($photo['other_id']) {
			    			$other = Other::find($photo["other_id"]);
			    			if($other['dogshit']) {
			    				$export[$index]["dogshit"] = $other['dogshit'];
			    			}
			    			if($other["dump"]) {
			    				$export[$index]["Random_dump"] = $other["dump"];
			    			}
			    			if($other["plastic"]) {
			    				$export[$index]["No_id_plastic"] = $other["plastic"];
			    			}
			    			if($other["metal"]) {
			    				$export[$index]["Metal_object"] = $other["metal"];
			    			}
			    			if($other["washing_up"]) {
			    				$export[$index]["washing_up"] = $other["washing_up"];
			    			}
			    			if($other["hair_tie"]) {
			    				$export[$index]["hair_tie"] = $other["hair_tie"];
			    			}
			    			if($other["ear_plugs"]) {
			    				$export[$index]["ear_plugs"] = $other["ear_plugs"];
			    			}
			    			if($other["batteries"]) {
			    				$export[$index]["batteries"] = $other["batteries"];
			    			}
			    			if($other["elec_small"]) {
			    				$export[$index]["elec_small"] = $other["elec_small"];
			    			}
			    			if($other["elec_large"]) {
			    				$export[$index]["elec_large"] = $other["elec_large"];
			    			}
			    			if($other["other"]) {
			    				$export[$index]["Unknown"] = $other["other"];
			    			}
			    		}

			    		if($photo['coastal_id']) {
			    			$coastal = Coastal::find($photo["coastal_id"]);
			    			if($coastal['microplastics']) {
			    				$export[$index]["microplastics"] = $coastal['microplastics'];
			    			}
			    			if($coastal['mediumplastics']) {
			    				$export[$index]["mediumplastics"] = $coastal['mediumplastics'];
			    			}
			    			if($coastal['macroplastics']) {
			    				$export[$index]['macroplastics'] = $coastal['macroplastics'];
			    			}
			    			if($coastal['rope_small']) {
			    				$export[$index]['rope_small'] = $coastal['rope_small'];
			    			}
			    			if($coastal['rope_medium']) {
			    				$export[$index]['rope_medium'] = $coastal['rope_medium'];
			    			}
			    			if($coastal['rope_large']) {
			    				$export[$index]['rope_large'] = $coastal['rope_large'];
			    			}
			    			if($coastal['fishing_gear_nets']) {
			    				$export[$index]['fishing_gear_nets'] = $coastal['fishing_gear_nets'];
			    			}
			    			if($coastal['buoys']) {
			    				$export[$index]['buoys'] = $coastal['buoys'];
			    			}
			    			if($coastal['degraded_plasticbottle']) {
			    				$export[$index]['degraded_plasticbottle'] = $coastal['degraded_plasticbottle'];
			    			}
			    			if($coastal['degraded_plasticbag']) {
			    				$export[$index]['degraded_plasticbag'] = $coastal['degraded_plasticbag'];
			    			}
			    			if($coastal['degraded_straws']) {
			    				$export[$index]['degraded_straws'] = $coastal['degraded_straws'];
			    			}
			    			if($coastal['degraded_lighters']) {
			    				$export[$index]['degraded_lighters'] = $coastal['degraded_lighters'];
			    			}
			    			if($coastal['baloons']) {
			    				$export[$index]['baloons'] = $coastal['baloons'];
			    			}
			    			if($coastal['lego']) {
			    				$export[$index]['lego'] = $coastal['lego'];
			    			}
			    			if($coastal['shotgun_cartridges']) {
			    				$export[$index]['shotgun_cartridges'] = $coastal['shotgun_cartridges'];
			    			}
			    			if($coastal['coastal_other']) {
			    				$export[$index]['coastal_other'] = $coastal['coastal_other'];
			    			}
			    		}

			    		// if($photo["art_id"]) {
			    		// 	$art = Art::find($photo["art_id"]);
			    		// 	if($art['item']) {
			    		// 		$export[$index]['art'] = $art['item'];
			    		// 	}
			    		// }

			    		if($photo["brands_id"]) {
			    			$brands = Brand::find($photo["brands_id"]);
			    			if($brands["adidas"]) {
			    				$export[$index]['adidas'] = $brands["adidas"];
			    			}
			    			if($brands["amazon"]) {
			    				$export[$index]['amazon'] = $brands["amazon"];
			    			}
			    			if($brands["apple"]) {
			    				$export[$index]['apple'] = $brands["apple"];
			    			}
			    			if($brands["budweiser"]) {
			    				$export[$index]['budweiser'] = $brands["budweiser"];
			    			}
			    			if($brands["coke"]) {
			    				$export[$index]['coke'] = $brands["coke"];
			    			}
			    			if($brands["colgate"]) {
			    				$export[$index]['colgate'] = $brands["colgate"];
			    			}
			    			if($brands["corona"]) {
			    				$export[$index]['corona'] = $brands["corona"];
			    			}
			    			if($brands["fritolay"]) {
			    				$export[$index]['fritolay'] = $brands["fritolay"];
			    			}
			    			if($brands["gillette"]) {
			    				$export[$index]['gillette'] = $brands["gillette"];
			    			}
			    			if($brands["heineken"]) {
			    				$export[$index]['heineken'] = $brands["heineken"];
			    			}
			    			if($brands["kellogs"]) {
			    				$export[$index]['kellogs'] = $brands["kellogs"];
			    			}
			    			if($brands["lego"]) {
			    				$export[$index]['lego'] = $brands["lego"];
			    			}
			    			if($brands["loreal"]) {
			    				$export[$index]['loreal'] = $brands["loreal"];
			    			}
			    			if($brands["nescafe"]) {
			    				$export[$index]['nescafe'] = $brands["nescafe"];
			    			}
			    			if($brands["nestle"]) {
			    				$export[$index]['nestle'] = $brands["nestle"];
			    			}
			    			if($brands["marlboro"]) {
			    				$export[$index]['marlboro'] = $brands["marlboro"];
			    			}
			    			if($brands["mcdonalds"]) {
			    				$export[$index]['mcdonalds'] = $brands["mcdonalds"];
			    			}
			    			if($brands["nike"]) {
			    				$export[$index]['nike'] = $brands["nike"];
			    			}
			    			if($brands["pepsi"]) {
			    				$export[$index]['pepsi'] = $brands["pepsi"];
			    			}
			    			if($brands["redbull"]) {
			    				$export[$index]['redbull'] = $brands["redbull"];
			    			}
			    			if($brands["samsung"]) {
			    				$export[$index]['samsung'] = $brands["samsung"];
			    			}
			    			if($brands["subway"]) {
			    				$export[$index]['subway'] = $brands["subway"];
			    			}
			    			if($brands["starbucks"]) {
			    				$export[$index]['starbucks'] = $brands["starbucks"];
			    			}
			    			if($brands["tayto"]) {
			    				$export[$index]['tayto'] = $brands["tayto"];
			    			}
			    		}
			    	}
			    	// return $export;
	    			$sheet->fromModel($export);
		    	})->export('csv');
	    		// $excel->setCreator('OpenLitterMap')->setCompany('GeoTech Innovations Ltd.');
	    	});
    	}
   	}

	/**
	 * Export Excel data from Verification Stage One to theauthenticated user
	 */
    public function getDataByCountry($country) {
		// Country only
		// need to pass the variable down the chain
    	Excel::create('Open Litter Map', function($excel) use ($country) {

    		$excel->sheet('OLM', function($sheet) use ($country) {

    			$country = Country::where('country', $country)->first();

		    	$photos = Photo::where([
		    		['country_id', $country->id],
		    		['verified', '>', 0]
		    	])->get();

		    	$export = [];

		    	foreach($photos as $index => $photo) {
		    		$index++;
		    		$export[$index]['id'] = $index;
		    		$export[$index]['verification'] = $photo->verified;
		    		$export[$index]['phone'] = $photo->model;
		    		$export[$index]['datetime'] = $photo->datetime;
		    		$export[$index]['lat'] = $photo->lat;
		    		$export[$index]['lon'] = $photo->lon;
		    		$export[$index]['country'] = $photo->country;
		    		$export[$index]['state'] = $photo->county;
		    		$export[$index]['city'] = $photo->city;
		    		$export[$index]['remaining_beta'] = $photo->remaining;
		    		$export[$index]['address'] = $photo->display_name;
			    	$export[$index]['total_litter'] = $photo->total_litter;

		    		$export[$index]['cigaretteButts'] = 0;
		    		$export[$index]['lighters'] = 0;
		    		$export[$index]['cigaretteBox'] = 0;
		    		$export[$index]['tobaccoPouch'] = 0;
		    		$export[$index]['papers_filters'] = 0;
		    		$export[$index]['plastic_smoking_pk'] = 0;
		    		$export[$index]['filters'] = 0;
		    		$export[$index]['filterbox'] = 0;
		    		$export[$index]['smokingOther'] = 0;

		    		$export[$index]['sweetWrappers'] = 0;
		    		$export[$index]['paperFoodPackaging'] = 0;
		    		$export[$index]['plasticFoodPackaging'] = 0;
		    		$export[$index]['plasticCutlery'] = 0;
		    		$export[$index]['crisp_small'] = 0;
		    		$export[$index]['crisp_large'] = 0;
		    		$export[$index]['styrofoam_plate'] = 0;
		    		$export[$index]['napkins'] = 0;
		    		$export[$index]['sauce_packet'] = 0;
		    		$export[$index]['glass_jar'] = 0;
		    		$export[$index]['glass_jar_lid'] = 0;
		    		$export[$index]['foodOther'] = 0;

		    		$export[$index]['coffeeCups'] = 0;
		    		$export[$index]['coffeeLids'] = 0;
		    		$export[$index]['coffeeOther'] = 0;

		    		$export[$index]['beerCan'] = 0;
		    		$export[$index]['beerBottle'] = 0;
		    		$export[$index]['spiritBottle'] = 0;
		    		$export[$index]['wineBottle'] = 0;
		    		$export[$index]['brokenGlass'] = 0;
		    		$export[$index]['paperCardAlcoholPackaging'] = 0;
		    		$export[$index]['plasticAlcoholPackaging'] = 0;
		    		$export[$index]['bottleTops'] = 0;
		    		$export[$index]['alcoholOther'] = 0;

		    		$export[$index]['plasticWaterBottle'] = 0;
		    		$export[$index]['fizzyDrinkBottle'] = 0;
		    		$export[$index]['bottleLid'] = 0;
		    		$export[$index]['bottleLabel'] = 0;
		    		$export[$index]['tinCan'] = 0;
		    		$export[$index]['sportsDrink'] = 0;
		    		$export[$index]['straws'] = 0;
		    		$export[$index]['plastic_cups'] = 0;
		    		$export[$index]['plastic_cup_tops'] = 0;
		    		$export[$index]['milk_bottle'] = 0;
		    		$export[$index]['milk_carton'] = 0;
		    		$export[$index]['paper_cups'] = 0;
		    		$export[$index]['juice_cartons'] = 0;
		    		$export[$index]['juice_bottles'] = 0;
		    		$export[$index]['juice_packet'] = 0;
		    		$export[$index]['ice_tea_bottles'] = 0;
		    		$export[$index]['ice_tea_can'] = 0;
		    		$export[$index]['energy_can'] = 0;
		    		$export[$index]['softDrinkOther'] = 0;


		    		$export[$index]['gloves'] = 0;
		    		$export[$index]['facemasks'] = 0;
		    		$export[$index]['condoms'] = 0;
		    		$export[$index]['mental'] = 0;
		    		$export[$index]['deodorant'] = 0;
		    		$export[$index]['ear_swabs'] = 0;
		    		$export[$index]['tooth_pick'] = 0;
		    		$export[$index]['tooth_brush'] = 0;
		    		$export[$index]['sanitaryOther'] = 0;
		    		$export[$index]['dogshit'] = 0;
		    		$export[$index]['Random_dump'] = 0;
		    		$export[$index]['No_id_plastic'] = 0;
		    		$export[$index]['Metal_object'] = 0;
		    		$export[$index]['plastic_bags'] = 0;
		    		$export[$index]['election_posters'] = 0;
		    		$export[$index]['forsale_posters'] = 0;
		    		$export[$index]['books'] = 0;
		    		$export[$index]['magazines'] = 0;
		    		$export[$index]['paper'] = 0;
		    		$export[$index]['stationary'] = 0;
		    		$export[$index]['washing_up'] = 0;
		    		$export[$index]['hair_tie'] = 0;
		    		$export[$index]['ear_plugs'] = 0;
		    		$export[$index]['batteries'] = 0;
		    		$export[$index]['elec_small'] = 0;
		    		$export[$index]['elec_large'] = 0;
		    		$export[$index]['Other_Unknown'] = 0;

		    		$export[$index]['microplastics'] = 0;
		    		$export[$index]['mediumplastics'] = 0;
		    		$export[$index]['macroplastics'] = 0;
		    		$export[$index]['rope_small'] = 0;
		    		$export[$index]['rope_medium'] = 0;
		    		$export[$index]['rope_large'] = 0;
		    		$export[$index]['fishing_gear_nets'] = 0;
		    		$export[$index]['buoys'] = 0;
		    		$export[$index]['degraded_plasticbottle'] = 0;
		    		$export[$index]['degraded_plasticbag'] = 0;
		    		$export[$index]['degraded_straws'] = 0;
		    		$export[$index]['degraded_lighters'] = 0;
		    		$export[$index]['baloons'] = 0;
		    		$export[$index]['lego'] = 0;
		    		$export[$index]['shotgun_cartridges'] = 0;
		    		$export[$index]['coastal_other'] = 0;

		    		$export[$index]['art'] = 0;

		    		$export[$index]['adidas'] = 0;
		    		$export[$index]['amazon'] = 0;
		    		$export[$index]['aldi'] = 0;
		    		$export[$index]['apple'] = 0;
		    		$export[$index]['applegreen'] = 0;
		    		$export[$index]['asahi'] = 0;
		    		$export[$index]['avoca'] = 0;

		    		$export[$index]['ballygowan'] = 0;
		    		$export[$index]['bewleys'] = 0;
		    		$export[$index]['brambles'] = 0;
		    		$export[$index]['budweiser'] = 0;
		    		$export[$index]['bulmers'] = 0;
		    		$export[$index]['burgerking'] = 0;
		    		$export[$index]['butlers'] = 0;

		    		$export[$index]['cadburys'] = 0;
		    		$export[$index]['cafe_nero'] = 0;
		    		$export[$index]['camel'] = 0;
		    		$export[$index]['carlsberg'] = 0;
		    		$export[$index]['centra'] = 0;
		    		$export[$index]['coke'] = 0;
		    		$export[$index]['circlek'] = 0;
		    		$export[$index]['coles'] = 0;
		    		$export[$index]['colgate'] = 0;
		    		$export[$index]['corona'] = 0;
		    		$export[$index]['costa'] = 0;

		    		$export[$index]['doritos'] = 0;
		    		$export[$index]['drpepper'] = 0;
		    		$export[$index]['dunnes'] = 0;
		    		$export[$index]['duracell'] = 0;
		    		$export[$index]['durex'] = 0;

		    		$export[$index]['esquires'] = 0;

		    		$export[$index]['frank_and_honest'] = 0;
		    		$export[$index]['fritolay'] = 0;

		    		$export[$index]['gatorade'] = 0;
		    		$export[$index]['gillette'] = 0;
		    		$export[$index]['guinness'] = 0;

		    		$export[$index]['haribo'] = 0;
		    		$export[$index]['heineken'] = 0;

		    		$export[$index]['insomnia'] = 0;

		    		$export[$index]['kellogs'] = 0;
		    		$export[$index]['kfc'] = 0;

		    		$export[$index]['lego'] = 0;
		    		$export[$index]['lidl'] = 0;
		    		$export[$index]['lindenvillage'] = 0;
		    		$export[$index]['lolly_and_cookes'] = 0;
		    		$export[$index]['loreal'] = 0;
		    		$export[$index]['lucozade'] = 0;

		    		$export[$index]['nero'] = 0;
		    		$export[$index]['nescafe'] = 0;
		    		$export[$index]['nestle'] = 0;

		    		$export[$index]['marlboro'] = 0;
		    		$export[$index]['mars'] = 0;
		    		$export[$index]['mcdonalds'] = 0;

		    		$export[$index]['nike'] = 0;

		    		$export[$index]['obriens'] = 0;

		    		$export[$index]['pepsi'] = 0;
		    		$export[$index]['powerade'] = 0;

		    		$export[$index]['redbull'] = 0;
		    		$export[$index]['ribena'] = 0;

		    		$export[$index]['samsung'] = 0;
		    		$export[$index]['sainsburys'] = 0;
		    		$export[$index]['spar'] = 0;
		    		$export[$index]['subway'] = 0;
		    		$export[$index]['supermacs'] = 0;
		    		$export[$index]['supervalu'] = 0;
		    		$export[$index]['starbucks'] = 0;

		    		$export[$index]['tayto'] = 0;
		    		$export[$index]['tesco'] = 0;
		    		$export[$index]['thins'] = 0;

		    		$export[$index]['volvic'] = 0;

		    		$export[$index]['waitrose'] = 0;
		    		$export[$index]['walkers'] = 0;
		    		$export[$index]['woolworths'] = 0;
		    		$export[$index]['wilde_and_greene'] = 0;
		    		$export[$index]['wrigleys'] = 0;

		    		if($photo['smoking_id']) {
		    			$smoking = Smoking::find($photo['smoking_id']);
		    			if($smoking['butts']) {
		    				$export[$index]['cigaretteButts'] = $smoking['butts'];
		    			}
		    			if($smoking['lighters']) {
		    				$export[$index]['lighters'] = $smoking['lighters'];
		    			}
		    			if($smoking['cigaretteBox']) {
		    				$export[$index]['cigaretteBox'] = $smoking['cigaretteBox'];
		    			}
		    			if($smoking['tobaccoPouch']) {
		    				$export[$index]['tobaccoPouch'] = $smoking['tobaccoPouch'];
		    			}
		    			if($smoking['skins']) {
		    				$export[$index]['papers_filters'] = $smoking['skins'];
		    			}
		    			if($smoking['plastic']) {
		    				$export[$index]['plastic_smoking_pk'] = $smoking['plastic'];
		    			}
		    			if($smoking['filters']) {
		    				$export[$index]['filters'] = $smoking['filters'];
		    			}
		    			if($smoking['filterbox']) {
		    				$export[$index]['filterbox'] = $smoking['filterbox'];
		    			}
		    			if($smoking['smokingOther']) {
		    				$export[$index]['smokingOther'] = $smoking['smokingOther'];
		    			}
		    		}

		    		if($photo['food_id']) {
		    			$food = Food::find($photo['food_id']);
		    			if($food['sweetWrappers']) {
		    				$export[$index]['sweetWrappers'] = $food['sweetWrappers'];
		    			}
		    			if($food['paperFoodPackaging']) {
		    				$export[$index]['paperFoodPackaging'] = $food['paperFoodPackaging'];
		    			}
		    			if($food['plasticFoodPackaging']) {
		    				$export[$index]['plasticFoodPackaging'] = $food['plasticFoodPackaging'];
		    			}
		    			if($food['plasticCutlery']) {
		    				$export[$index]['plasticCutlery'] = $food['plasticCutlery'];
		    			}
		    			if($food['crisp_small']) {
		    				$export[$index]['crisp_small'] = $food['crisp_small'];
		    			}
		    			if($food['crisp_large']) {
		    				$export[$index]['crisp_large'] = $food['crisp_large'];
		    			}
		    			if($food['styrofoam_plate']) {
		    				$export[$index]['styrofoam_plate'] = $food['styrofoam_plate'];
		    			}
		    			if($food['napkins']) {
		    				$export[$index]['napkins'] = $food['napkins'];
		    			}
		    			if($food['sauce_packet']) {
		    				$export[$index]['sauce_packet'] = $food['sauce_packet'];
		    			}
		    			if($food['glass_jar']) {
		    				$export[$index]['glass_jar'] = $food['glass_jar'];
		    			}
		    			if($food['glass_jar_lid']) {
		    				$export[$index]['glass_jar_lid'] = $food['glass_jar_lid'];
		    			}
		    			if($food['foodOther']) {
		    				$export[$index]['foodOther'] = $food['foodOther'];
		    			}
		    		}

		    		if($photo['coffee_id']) {
		    			$coffee = Coffee::find($photo['coffee_id']);
		    			if($coffee['coffeeCups']) {
		    				$export[$index]['coffeeCups'] = $coffee['coffeeCups'];
		    			}
		    			if($coffee['coffeeLids']) {
		    				$export[$index]['coffeeLids'] = $coffee['coffeeLids'];
		    			}
		    			if($coffee['coffeeOther']) {
		    				$export[$index]['coffeeOther'] = $coffee['coffeeOther'];
		    			}
		    		}

		    		if($photo['alcohol_id']) {
		    			$alcohol = Alcohol::find($photo['alcohol_id']);
		    			if($alcohol['beerBottle']) {
		    				$export[$index]['beerBottle'] = $alcohol['beerBottle'];
		    			}
		    			if($alcohol['spiritBottle']) {
		    				$export[$index]['spiritBottle'] = $alcohol['spiritBottle'];
		    			}
		    			if($alcohol['beerCan']) {
		    				$export[$index]['beerCan'] = $alcohol['beerCan'];
		    			}
		    			if($alcohol['brokenGlass']) {
		    				$export[$index]['brokenGlass_alcohol'] = $alcohol['brokenGlass'];
		    			}
		    			if($alcohol['paperCardAlcoholPackaging']) {
		    				$export[$index]['paperCardAlcoholPackaging'] = $alcohol['paperCardAlcoholPackaging'];
		    			}
		    			if($alcohol['plasticAlcoholPackaging']) {
		    				$export[$index]['plasticAlcoholPackaging'] = $alcohol['plasticAlcoholPackaging'];
		    			}
		    			if($alcohol['bottleTops']) {
		    				$export[$index]['bottleTops'] = $alcohol['bottleTops'];
		    			}
		    			if($alcohol['wineBottle']) {
		    				$export[$index]['wineBottle'] = $alcohol['wineBottle'];
		    			}
		    			if($alcohol['alcoholOther']) {
		    				$export[$index]['alcoholOther'] = $alcohol['alcoholOther'];
		    			}
		    		}

		    		if($photo["softdrinks_id"]) {
		    			$softdrinks = SoftDrinks::find($photo["softdrinks_id"]);
		    			if($softdrinks['waterBottle']) {
		    				$export[$index]['plasticWaterBottle'] = $softdrinks["waterBottle"];
		    			}
		    			if($softdrinks['fizzyDrinkBottle']) {
		    				$export[$index]['fizzyDrinkBottle'] = $softdrinks["fizzyDrinkBottle"];
		    			}
		    			if($softdrinks['bottleLid']) {
		    				$export[$index]['bottleLid'] = $softdrinks["bottleLid"];
		    			}
		    			if($softdrinks['bottleLabel']) {
		    				$export[$index]['bottleLabel'] = $softdrinks["bottleLabel"];
		    			}
		    			if($softdrinks['tinCan']) {
		    				$export[$index]['tinCan'] = $softdrinks["tinCan"];
		    			}
		    			if($softdrinks['sportsDrink']) {
		    				$export[$index]['sportsDrink'] = $softdrinks["sportsDrink"];
		    			}
		    			if($softdrinks['paper_cups']) {
		    				$export[$index]['paper_cups'] = $softdrinks["paper_cups"];
		    			}
		    			if($softdrinks['juice_cartons']) {
		    				$export[$index]['juice_cartons'] = $softdrinks["juice_cartons"];
		    			}
		    			if($softdrinks['juice_bottles']) {
		    				$export[$index]['juice_bottles'] = $softdrinks["juice_bottles"];
		    			}
		    			if($softdrinks['juice_packet']) {
		    				$export[$index]['juice_packet'] = $softdrinks["juice_packet"];
		    			}
		    			if($softdrinks['ice_tea_bottles']) {
		    				$export[$index]['ice_tea_bottles'] = $softdrinks["ice_tea_bottles"];
		    			}
		    			if($softdrinks['ice_tea_can']) {
		    				$export[$index]['ice_tea_can'] = $softdrinks["ice_tea_can"];
		    			}
		    			if($softdrinks['energy_can']) {
		    				$export[$index]['energy_can'] = $softdrinks["energy_can"];
		    			}
		    			if($softdrinks['softDrinkOther']) {
		    				$export[$index]['softDrinkOther'] = $softdrinks["softDrinkOther"];
		    			}
		    		}

		    		if($photo["sanitary_id"]){
		    			$sanitary = Sanitary::find($photo["sanitary_id"]);
		    			if ($sanitary['gloves']) {
		    				$export[$index]['gloves'] = $sanitary['gloves'];
		    			}
		    			if ($sanitary['facemasks']) {
		    				$export[$index]['facemasks'] = $sanitary['facemasks'];
		    			}
		    			if($sanitary["condoms"]) {
		    				$export[$index]["condoms"] = $sanitary["condoms"];
		    			}
		    			if($sanitary["nappies"]) {
		    				$export[$index]["nappies"] = $sanitary["nappies"];
		    			}
		    			if($sanitary["menstral"]) {
		    				$export[$index]["menstral"] = $sanitary["menstral"];
		    			}
		    			if($sanitary["deodorant"]) {
		    				$export[$index]["deodorant"] = $sanitary["deodorant"];
		    			}
		    			if($sanitary["sanitaryOther"]) {
		    				$export[$index]["sanitaryOther"] = $sanitary["sanitaryOther"];
		    			}
		    		}

		    		if($photo['other_id']) {
		    			$other = Other::find($photo["other_id"]);
		    			if($other['dogshit']) {
		    				$export[$index]["dogshit"] = $other['dogshit'];
		    			}
		    			if($other["dump"]) {
		    				$export[$index]["Random_dump"] = $other["dump"];
		    			}
		    			if($other["plastic"]) {
		    				$export[$index]["No_id_plastic"] = $other["plastic"];
		    			}
		    			if($other["metal"]) {
		    				$export[$index]["Metal_object"] = $other["metal"];
		    			}
		    			if($other["washing_up"]) {
		    				$export[$index]["washing_up"] = $other["washing_up"];
		    			}
		    			if($other["hair_tie"]) {
		    				$export[$index]["hair_tie"] = $other["hair_tie"];
		    			}
		    			if($other["ear_plugs"]) {
		    				$export[$index]["ear_plugs"] = $other["ear_plugs"];
		    			}
		    			if($other["batteries"]) {
		    				$export[$index]["batteries"] = $other["batteries"];
		    			}
		    			if($other["elec_small"]) {
		    				$export[$index]["elec_small"] = $other["elec_small"];
		    			}
		    			if($other["elec_large"]) {
		    				$export[$index]["elec_large"] = $other["elec_large"];
		    			}
		    			if($other["other"]) {
		    				$export[$index]["Unknown"] = $other["other"];
		    			}
		    		}

		    		if($photo['coastal_id']) {
		    			$coastal = Coastal::find($photo["coastal_id"]);
		    			if($coastal['microplastics']) {
		    				$export[$index]["microplastics"] = $coastal['microplastics'];
		    			}
		    			if($coastal['mediumplastics']) {
		    				$export[$index]["mediumplastics"] = $coastal['mediumplastics'];
		    			}
		    			if($coastal['macroplastics']) {
		    				$export[$index]['macroplastics'] = $coastal['macroplastics'];
		    			}
		    			if($coastal['rope_small']) {
		    				$export[$index]['rope_small'] = $coastal['rope_small'];
		    			}
		    			if($coastal['rope_medium']) {
		    				$export[$index]['rope_medium'] = $coastal['rope_medium'];
		    			}
		    			if($coastal['rope_large']) {
		    				$export[$index]['rope_large'] = $coastal['rope_large'];
		    			}
		    			if($coastal['fishing_gear_nets']) {
		    				$export[$index]['fishing_gear_nets'] = $coastal['fishing_gear_nets'];
		    			}
		    			if($coastal['buoys']) {
		    				$export[$index]['buoys'] = $coastal['buoys'];
		    			}
		    			if($coastal['degraded_plasticbottle']) {
		    				$export[$index]['degraded_plasticbottle'] = $coastal['degraded_plasticbottle'];
		    			}
		    			if($coastal['degraded_plasticbag']) {
		    				$export[$index]['degraded_plasticbag'] = $coastal['degraded_plasticbag'];
		    			}
		    			if($coastal['degraded_straws']) {
		    				$export[$index]['degraded_straws'] = $coastal['degraded_straws'];
		    			}
		    			if($coastal['degraded_lighters']) {
		    				$export[$index]['degraded_lighters'] = $coastal['degraded_lighters'];
		    			}
		    			if($coastal['baloons']) {
		    				$export[$index]['baloons'] = $coastal['baloons'];
		    			}
		    			if($coastal['lego']) {
		    				$export[$index]['lego'] = $coastal['lego'];
		    			}
		    			if($coastal['shotgun_cartridges']) {
		    				$export[$index]['shotgun_cartridges'] = $coastal['shotgun_cartridges'];
		    			}
		    			if($coastal['coastal_other']) {
		    				$export[$index]['coastal_other'];
		    			}
		    		}

		    		// if($photo["art_id"]) {
		    		// 	$art = Art::find($photo["art_id"]);
		    		// 	if($art['item']) {
		    		// 		$export[$index]['art'] = $art['item'];
		    		// 	}
		    		// }

		    		if($photo["brands_id"]) {
		    			$brands = Brand::find($photo["brands_id"]);
		    			if($brands["adidas"]) {
		    				$export[$index]['adidas'] = $brands["adidas"];
		    			}
		    			if($brands["amazon"]) {
		    				$export[$index]['amazon'] = $brands["amazon"];
		    			}
		    			if($brands["aldi"]) {
		    				$export[$index]['aldi'] = $brands["aldi"];
		    			}
		    			if($brands["apple"]) {
		    				$export[$index]['apple'] = $brands["apple"];
		    			}
		    			if($brands["applegreen"]) {
		    				$export[$index]['applegreen'] = $brands["applegreen"];
		    			}
		    			if($brands["asahi"]) {
		    				$export[$index]['asahi'] = $brands["asahi"];
		    			}
		    			if($brands["avoca"]) {
		    				$export[$index]['avoca'] = $brands["avoca"];
		    			}
		    			if($brands["ballygowan"]) {
		    				$export[$index]['ballygowan'] = $brands["ballygowan"];
		    			}
		    			if($brands["bewleys"]) {
		    				$export[$index]['bewleys'] = $brands["bewleys"];
		    			}
		    			if($brands["brambles"]) {
		    				$export[$index]['brambles'] = $brands["brambles"];
		    			}
		    			if($brands["budweiser"]) {
		    				$export[$index]['budweiser'] = $brands["budweiser"];
		    			}
		    			if($brands["bulmers"]) {
		    				$export[$index]['bulmers'] = $brands["bulmers"];
		    			}
		    			if($brands["burgerking"]) {
		    				$export[$index]['burgerking'] = $brands["burgerking"];
		    			}
		    			if($brands["butlers"]) {
		    				$export[$index]['butlers'] = $brands["butlers"];
		    			}

		    			if($brands["cadburys"]) {
		    				$export[$index]['cadburys'] = $brands["cadburys"];
		    			}
		    			if($brands["cafe_nero"]) {
		    				$export[$index]['cafe_nero'] = $brands["cafe_nero"];
		    			}
		    			if($brands["camel"]) {
		    				$export[$index]['camel'] = $brands["camel"];
		    			}
		    			if($brands["carlsberg"]) {
		    				$export[$index]['carlsberg'] = $brands["carlsberg"];
		    			}
		    			if($brands["centra"]) {
		    				$export[$index]['centra'] = $brands["centra"];
		    			}
		    			if($brands["coke"]) {
		    				$export[$index]['coke'] = $brands["coke"];
		    			}
		    			if($brands["circlek"]) {
		    				$export[$index]['circlek'] = $brands["circlek"];
		    			}
		    			if($brands["coles"]) {
		    				$export[$index]['coles'] = $brands["coles"];
		    			}
		    			if($brands["colgate"]) {
		    				$export[$index]['colgate'] = $brands["colgate"];
		    			}
		    			if($brands["corona"]) {
		    				$export[$index]['corona'] = $brands["corona"];
		    			}
		    			if($brands["costa"]) {
		    				$export[$index]['costa'] = $brands["costa"];
		    			}

		    			if($brands["doritos"]) {
		    				$export[$index]['doritos'] = $brands["doritos"];
		    			}
		    			if($brands["drpepper"]) {
		    				$export[$index]['drpepper'] = $brands["drpepper"];
		    			}
		    			if($brands["dunnes"]) {
		    				$export[$index]['dunnes'] = $brands["dunnes"];
		    			}
		    			if($brands["duracell"]) {
		    				$export[$index]['duracell'] = $brands["duracell"];
		    			}
		    			if($brands["durex"]) {
		    				$export[$index]['durex'] = $brands["durex"];
		    			}

		    			if($brands["esquires"]) {
		    				$export[$index]['esquires'] = $brands["esquires"];
		    			}

						if($brands["frank_and_honest"]) {
		    			   $export[$index]['frank_and_honest'] = $brands["frank_and_honest"];
		    			}
		    			if($brands["fritolay"]) {
		    				$export[$index]['fritolay'] = $brands["fritolay"];
		    			}

		    			if($brands["gatorade"]) {
		    				$export[$index]['gatorade'] = $brands["gatorade"];
		    			}
		    			if($brands["gillette"]) {
		    				$export[$index]['gillette'] = $brands["gillette"];
		    			}
		    			if($brands["guinness"]) {
		    				$export[$index]['guinness'] = $brands["gillette"];
		    			}

		    			if($brands["haribo"]) {
		    				$export[$index]['haribo'] = $brands["haribo"];
		    			}
		    			if($brands["heineken"]) {
		    				$export[$index]['heineken'] = $brands["heineken"];
		    			}

		    			if($brands["insomnia"]) {
		    				$export[$index]['insomnia'] = $brands["insomnia"];
		    			}

		    			if($brands["kellogs"]) {
		    				$export[$index]['kellogs'] = $brands["kellogs"];
		    			}
		    			if($brands["kfc"]) {
		    				$export[$index]['kfc'] = $brands["kfc"];
		    			}

		    			if($brands["lego"]) {
		    				$export[$index]['lego'] = $brands["lego"];
		    			}
		    			if($brands["lidl"]) {
		    				$export[$index]['lidl'] = $brands["lidl"];
		    			}
		    			if($brands["lindenvillage"]) {
		    				$export[$index]['lindenvillage'] = $brands["lindenvillage"];
		    			}
		    			if($brands["lolly_and_cookes"]) {
		    			   $export[$index]['lolly_and_cookes'] = $brands["lolly_and_cookes"];
		    			}
		    			if($brands["loreal"]) {
		    				$export[$index]['loreal'] = $brands["loreal"];
		    			}
		    			if($brands["lucozade"]) {
		    				$export[$index]['lucozade'] = $brands["lucozade"];
		    			}

		    			if($brands["marlboro"]) {
		    				$export[$index]['marlboro'] = $brands["marlboro"];
		    			}
		    			if($brands["mars"]) {
		    				$export[$index]['mars'] = $brands["mars"];
		    			}
		    			if($brands["mcdonalds"]) {
		    				$export[$index]['mcdonalds'] = $brands["mcdonalds"];
		    			}

		    			if($brands["nero"]) {
		    				$export[$index]['nero'] = $brands["nero"];
		    			}
		    			if($brands["nescafe"]) {
		    				$export[$index]['nescafe'] = $brands["nescafe"];
		    			}
		    			if($brands["nestle"]) {
		    				$export[$index]['nestle'] = $brands["nestle"];
		    			}
		    			if($brands["nike"]) {
		    				$export[$index]['nike'] = $brands["nike"];
		    			}

		    			if($brands["obriens"]) {
		    				$export[$index]['obriens'] = $brands["obriens"];
		    			}

		    			if($brands["pepsi"]) {
		    				$export[$index]['pepsi'] = $brands["pepsi"];
		    			}
		    			if($brands["powerade"]) {
		    				$export[$index]['powerade'] = $brands["powerade"];
		    			}

		    			if($brands["redbull"]) {
		    				$export[$index]['redbull'] = $brands["redbull"];
		    			}
		    			if($brands["ribena"]) {
		    				$export[$index]['ribena'] = $brands["ribena"];
		    			}

		    			if($brands["samsung"]) {
		    				$export[$index]['samsung'] = $brands["samsung"];
		    			}
		    			if($brands["sainsburys"]) {
		    				$export[$index]['sainsburys'] = $brands["sainsburys"];
		    			}
		    			if($brands["spar"]) {
		    				$export[$index]['spar'] = $brands["spar"];
		    			}
		    			if($brands["stella"]) {
		    				$export[$index]['stella'] = $brands["stella"];
		    			}
		    			if($brands["subway"]) {
		    				$export[$index]['subway'] = $brands["subway"];
		    			}
		    			if($brands["supermacs"]) {
		    				$export[$index]['supermacs'] = $brands["supermacs"];
		    			}
		    			if($brands["supervale"]) {
		    				$export[$index]['supervale'] = $brands["supervale"];
		    			}
		    			if($brands["starbucks"]) {
		    				$export[$index]['starbucks'] = $brands["starbucks"];
		    			}

		    			if($brands["tayto"]) {
		    				$export[$index]['tayto'] = $brands["tayto"];
		    			}
		    			if($brands["tesco"]) {
		    				$export[$index]['tesco'] = $brands["tesco"];
		    			}
		    			if($brands["thins"]) {
		    				$export[$index]['thins'] = $brands["thins"];
		    			}

		    			if($brands["volvic"]) {
		    				$export[$index]['volvic'] = $brands["volvic"];
		    			}

		    			if($brands["waitrose"]) {
		    				$export[$index]['waitrose'] = $brands["waitrose"];
		    			}
		    			if($brands["walkers"]) {
		    				$export[$index]['walkers'] = $brands["walkers"];
		    			}
		    			if($brands["woolworths"]) {
		    				$export[$index]['woolworths'] = $brands["woolworths"];
		    			}
		    			if($brands["wilde_and_greene"]) {
		    			   $export[$index]['wilde_and_greene'] = $brands["wilde_and_greene"];
		    			}
		    			if($brands["wrigleys"]) {
		    				$export[$index]['wrigleys'] = $brands["wrigleys"];
		    			}
		    		}
		    	}
		    	// return $export;
    			$sheet->fromModel($export);
	    	})->export('csv');
    		// $excel->setCreator('OpenLitterMap')->setCompany('GeoTech Innovations Ltd.');
	    });
    }

    public function getDataByCity($country, $state, $city) {
    	if($city) {
    		// City only
    		// need to pass the variable down the chain
	    	Excel::create('Open Litter Map', function($excel) use ($city) {
	    		$excel->sheet('OLM', function($sheet) use ($city) {

	    			$theCity = City::where('city', $city)->first();
			    	$photos = Photo::where([
			    		['city_id', $theCity->id],
			    		['verified', '>', 0]
			    	])->get();

			    	$export = [];
			    	foreach($photos as $index => $photo) {
			    		$index++;
			    		$export[$index]['id'] = $index;
			    		$export[$index]['verification'] = $photo->verified;
			    		$export[$index]['phone'] = $photo->model;
			    		$export[$index]['datetime'] = $photo->datetime;
			    		$export[$index]['lat'] = $photo->lat;
			    		$export[$index]['lon'] = $photo->lon;
			    		$export[$index]['city'] = $photo->city;
			    		$export[$index]['state'] = $photo->county;
			    		$export[$index]['country'] = $photo->country;
			    		$export[$index]['remaining_beta'] = $photo->remaining;
			    		$export[$index]['address'] = $photo->display_name;
			    		$export[$index]['total_litter'] = $photo->total_litter;

			    		$export[$index]['cigaretteButts'] = 0;
			    		$export[$index]['lighters'] = 0;
			    		$export[$index]['cigaretteBox'] = 0;
			    		$export[$index]['tobaccoPouch'] = 0;
			    		$export[$index]['papers_filters'] = 0;
						$export[$index]['plastic_smoking_pk'] = 0;
			    		$export[$index]['filters'] = 0;
			    		$export[$index]['filterbox'] = 0;
			    		$export[$index]['smokingOther'] = 0;

			    		$export[$index]['sweetWrappers'] = 0;
			    		$export[$index]['paperFoodPackaging'] = 0;
			    		$export[$index]['plasticFoodPackaging'] = 0;
			    		$export[$index]['plasticCutlery'] = 0;
			    		$export[$index]['crisp_small'] = 0;
			    		$export[$index]['crisp_large'] = 0;
			    		$export[$index]['styrofoam_plate'] = 0;
			    		$export[$index]['napkins'] = 0;
			    		$export[$index]['sauce_packet'] = 0;
			    		$export[$index]['glass_jar'] = 0;
			    		$export[$index]['glass_jar_lid'] = 0;
			    		$export[$index]['foodOther'] = 0;

			    		$export[$index]['coffeeCups'] = 0;
			    		$export[$index]['coffeeLids'] = 0;
			    		$export[$index]['coffeeOther'] = 0;

			    		$export[$index]['beerCan'] = 0;
			    		$export[$index]['beerBottle'] = 0;
			    		$export[$index]['spiritBottle'] = 0;
			    		$export[$index]['wineBottle'] = 0;
			    		$export[$index]['brokenGlass'] = 0;
			    		$export[$index]['paperCardAlcoholPackaging'] = 0;
			    		$export[$index]['plasticAlcoholPackaging'] = 0;
			    		$export[$index]['bottleTops'] = 0;
			    		$export[$index]['alcoholOther'] = 0;

			    		$export[$index]['plasticWaterBottle'] = 0;
			    		$export[$index]['fizzyDrinkBottle'] = 0;
			    		$export[$index]['bottleLid'] = 0;
			    		$export[$index]['bottleLabel'] = 0;
			    		$export[$index]['tinCan'] = 0;
			    		$export[$index]['sportsDrink'] = 0;
			    		$export[$index]['straws'] = 0;
			    		$export[$index]['plastic_cups'] = 0;
			    		$export[$index]['plastic_cup_tops'] = 0;
			    		$export[$index]['milk_bottle'] = 0;
			    		$export[$index]['milk_carton'] = 0;
			    		$export[$index]['paper_cups'] = 0;
			    		$export[$index]['juice_cartons'] = 0;
			    		$export[$index]['juice_bottles'] = 0;
			    		$export[$index]['juice_packet'] = 0;
			    		$export[$index]['ice_tea_bottles'] = 0;
			    		$export[$index]['ice_tea_can'] = 0;
			    		$export[$index]['energy_can'] = 0;
			    		$export[$index]['softDrinkOther'] = 0;


			    		$export[$index]['gloves'] = 0;
			    		$export[$index]['facemasks'] = 0;
			    		$export[$index]['condoms'] = 0;
			    		$export[$index]['mental'] = 0;
			    		$export[$index]['deodorant'] = 0;
			    		$export[$index]['ear_swabs'] = 0;
			    		$export[$index]['tooth_pick'] = 0;
			    		$export[$index]['tooth_brush'] = 0;
			    		$export[$index]['sanitaryOther'] = 0;
			    		$export[$index]['dogshit'] = 0;
			    		$export[$index]['Random_dump'] = 0;
			    		$export[$index]['No_id_plastic'] = 0;
			    		$export[$index]['Metal_object'] = 0;
			    		$export[$index]['plastic_bags'] = 0;
			    		$export[$index]['election_posters'] = 0;
			    		$export[$index]['forsale_posters'] = 0;
			    		$export[$index]['books'] = 0;
			    		$export[$index]['magazines'] = 0;
			    		$export[$index]['paper'] = 0;
			    		$export[$index]['stationary'] = 0;
			    		$export[$index]['washing_up'] = 0;
			    		$export[$index]['hair_tie'] = 0;
			    		$export[$index]['ear_plugs'] = 0;
			    		$export[$index]['batteries'] = 0;
			    		$export[$index]['elec_small'] = 0;
			    		$export[$index]['elec_large'] = 0;
			    		$export[$index]['Other_Unknown'] = 0;

			    		$export[$index]['microplastics'] = 0;
			    		$export[$index]['mediumplastics'] = 0;
			    		$export[$index]['macroplastics'] = 0;
			    		$export[$index]['rope_small'] = 0;
			    		$export[$index]['rope_medium'] = 0;
			    		$export[$index]['rope_large'] = 0;
			    		$export[$index]['fishing_gear_nets'] = 0;
			    		$export[$index]['buoys'] = 0;
			    		$export[$index]['degraded_plasticbottle'] = 0;
			    		$export[$index]['degraded_plasticbag'] = 0;
			    		$export[$index]['degraded_straws'] = 0;
			    		$export[$index]['degraded_lighters'] = 0;
			    		$export[$index]['baloons'] = 0;
			    		$export[$index]['lego'] = 0;
			    		$export[$index]['shotgun_cartridges'] = 0;
			    		$export[$index]['coastal_other'] = 0;

			    		$export[$index]['art'] = 0;

			    		$export[$index]['adidas'] = 0;
			    		$export[$index]['amazon'] = 0;
			    		$export[$index]['aldi'] = 0;
			    		$export[$index]['apple'] = 0;
			    		$export[$index]['applegreen'] = 0;
			    		$export[$index]['asahi'] = 0;
			    		$export[$index]['avoca'] = 0;

			    		$export[$index]['ballygowan'] = 0;
			    		$export[$index]['bewleys'] = 0;
			    		$export[$index]['brambles'] = 0;
			    		$export[$index]['budweiser'] = 0;
			    		$export[$index]['bulmers'] = 0;
			    		$export[$index]['burgerking'] = 0;
			    		$export[$index]['butlers'] = 0;

			    		$export[$index]['cadburys'] = 0;
			    		$export[$index]['cafe_nero'] = 0;
			    		$export[$index]['camel'] = 0;
			    		$export[$index]['carlsberg'] = 0;
			    		$export[$index]['centra'] = 0;
			    		$export[$index]['coke'] = 0;
			    		$export[$index]['circlek'] = 0;
			    		$export[$index]['coles'] = 0;
			    		$export[$index]['colgate'] = 0;
			    		$export[$index]['corona'] = 0;
			    		$export[$index]['costa'] = 0;

			    		$export[$index]['doritos'] = 0;
			    		$export[$index]['drpepper'] = 0;
			    		$export[$index]['dunnes'] = 0;
			    		$export[$index]['duracell'] = 0;
			    		$export[$index]['durex'] = 0;

			    		$export[$index]['esquires'] = 0;

			    		$export[$index]['frank_and_honest'] = 0;
			    		$export[$index]['fritolay'] = 0;

			    		$export[$index]['gatorade'] = 0;
			    		$export[$index]['gillette'] = 0;
			    		$export[$index]['guinness'] = 0;

			    		$export[$index]['haribo'] = 0;
			    		$export[$index]['heineken'] = 0;

			    		$export[$index]['insomnia'] = 0;

			    		$export[$index]['kellogs'] = 0;
			    		$export[$index]['kfc'] = 0;

			    		$export[$index]['lego'] = 0;
			    		$export[$index]['lidl'] = 0;
			    		$export[$index]['lindenvillage'] = 0;
			    		$export[$index]['lolly_and_cookes'] = 0;
			    		$export[$index]['loreal'] = 0;
			    		$export[$index]['lucozade'] = 0;

			    		$export[$index]['nero'] = 0;
			    		$export[$index]['nescafe'] = 0;
			    		$export[$index]['nestle'] = 0;

			    		$export[$index]['marlboro'] = 0;
			    		$export[$index]['mars'] = 0;
			    		$export[$index]['mcdonalds'] = 0;

			    		$export[$index]['nike'] = 0;

			    		$export[$index]['obriens'] = 0;

			    		$export[$index]['pepsi'] = 0;
			    		$export[$index]['powerade'] = 0;

			    		$export[$index]['redbull'] = 0;
			    		$export[$index]['ribena'] = 0;

			    		$export[$index]['samsung'] = 0;
			    		$export[$index]['sainsburys'] = 0;
			    		$export[$index]['spar'] = 0;
			    		$export[$index]['subway'] = 0;
			    		$export[$index]['supermacs'] = 0;
			    		$export[$index]['supervalu'] = 0;
			    		$export[$index]['starbucks'] = 0;

			    		$export[$index]['tayto'] = 0;
			    		$export[$index]['tesco'] = 0;
			    		$export[$index]['thins'] = 0;

			    		$export[$index]['volvic'] = 0;

			    		$export[$index]['waitrose'] = 0;
			    		$export[$index]['walkers'] = 0;
			    		$export[$index]['woolworths'] = 0;
			    		$export[$index]['wilde_and_greene'] = 0;
			    		$export[$index]['wrigleys'] = 0;


			    		if($photo['smoking_id']) {
			    			$smoking = Smoking::find($photo['smoking_id']);
			    			if($smoking['butts']) {
			    				$export[$index]['cigaretteButts'] = $smoking['butts'];
			    			}
			    			if($smoking['lighters']) {
			    				$export[$index]['lighters'] = $smoking['lighters'];
			    			}
			    			if($smoking['cigaretteBox']) {
			    				$export[$index]['cigaretteBox'] = $smoking['cigaretteBox'];
			    			}
			    			if($smoking['tobaccoPouch']) {
			    				$export[$index]['tobaccoPouch'] = $smoking['tobaccoPouch'];
			    			}
			    			if($smoking['skins']) {
			    				$export[$index]['papers_filters'] = $smoking['skins'];
			    			}
			    			if($smoking['plastic']) {
		    					$export[$index]['plastic_smoking_pk'] = $smoking['plastic'];
		    				}
		    				if($smoking['filters']) {
		    					$export[$index]['filters'] = $smoking['filters'];
		    				}
		    				if($smoking['filterbox']) {
		    					$export[$index]['filterbox'] = $smoking['filterbox'];
		    				}
			    			if($smoking['smokingOther']) {
			    				$export[$index]['smokingOther'] = $smoking['smokingOther'];
			    			}
			    		}

			    		if($photo['food_id']) {
			    			$food = Food::find($photo['food_id']);
			    			if($food['sweetWrappers']) {
			    				$export[$index]['sweetWrappers'] = $food['sweetWrappers'];
			    			}
			    			if($food['paperFoodPackaging']) {
			    				$export[$index]['paperFoodPackaging'] = $food['paperFoodPackaging'];
			    			}
			    			if($food['plasticFoodPackaging']) {
			    				$export[$index]['plasticFoodPackaging'] = $food['plasticFoodPackaging'];
			    			}
			    			if($food['plasticCutlery']) {
			    				$export[$index]['plasticCutlery'] = $food['plasticCutlery'];
			    			}
			    			if($food['crisp_small']) {
			    				$export[$index]['crisp_small'] = $food['crisp_small'];
			    			}
			    			if($food['crisp_large']) {
			    				$export[$index]['crisp_large'] = $food['crisp_large'];
			    			}
			    			if($food['styrofoam_plate']) {
			    				$export[$index]['styrofoam_plate'] = $food['styrofoam_plate'];
			    			}
			    			if($food['napkins']) {
			    				$export[$index]['napkins'] = $food['napkins'];
			    			}
			    			if($food['sauce_packet']) {
			    				$export[$index]['sauce_packet'] = $food['sauce_packet'];
			    			}
			    			if($food['glass_jar']) {
			    				$export[$index]['glass_jar'] = $food['glass_jar'];
			    			}
			    			if($food['glass_jar_lid']) {
			    				$export[$index]['glass_jar_lid'] = $food['glass_jar_lid'];
			    			}
			    			if($food['foodOther']) {
			    				$export[$index]['foodOther'] = $food['foodOther'];
			    			}
			    		}

			    		if($photo['coffee_id']) {
			    			$coffee = Coffee::find($photo['coffee_id']);
			    			if($coffee['coffeeCups']) {
			    				$export[$index]['coffeeCups'] = $coffee['coffeeCups'];
			    			}
			    			if($coffee['coffeeLids']) {
			    				$export[$index]['coffeeLids'] = $coffee['coffeeLids'];
			    			}
			    			if($coffee['coffeeOther']) {
			    				$export[$index]['coffeeOther'] = $coffee['coffeeOther'];
			    			}
			    		}

			    		if($photo['alcohol_id']) {
			    			$alcohol = Alcohol::find($photo['alcohol_id']);
			    			if($alcohol['beerBottle']) {
			    				$export[$index]['beerBottle'] = $alcohol['beerBottle'];
			    			}
			    			if($alcohol['spiritBottle']) {
			    				$export[$index]['spiritBottle'] = $alcohol['spiritBottle'];
			    			}
			    			if($alcohol['beerCan']) {
			    				$export[$index]['beerCan'] = $alcohol['beerCan'];
			    			}
			    			if($alcohol['brokenGlass']) {
			    				$export[$index]['brokenGlass_alcohol'] = $alcohol['brokenGlass'];
			    			}
			    			if($alcohol['paperCardAlcoholPackaging']) {
			    				$export[$index]['paperCardAlcoholPackaging'] = $alcohol['paperCardAlcoholPackaging'];
			    			}
			    			if($alcohol['plasticAlcoholPackaging']) {
			    				$export[$index]['plasticAlcoholPackaging'] = $alcohol['plasticAlcoholPackaging'];
			    			}
			    			if($alcohol['bottleTops']) {
			    				$export[$index]['bottleTops'] = $alcohol['bottleTops'];
			    			}
			    			if($alcohol['wineBottle']) {
			    				$export[$index]['wineBottle'] = $alcohol['wineBottle'];
			    			}
			    			if($alcohol['alcoholOther']) {
			    				$export[$index]['alcoholOther'] = $alcohol['alcoholOther'];
			    			}
			    		}

			    		if($photo["softdrinks_id"]) {
			    			$softdrinks = SoftDrinks::find($photo["softdrinks_id"]);
			    			if($softdrinks['waterBottle']) {
			    				$export[$index]['plasticWaterBottle'] = $softdrinks["waterBottle"];
			    			}
			    			if($softdrinks['fizzyDrinkBottle']) {
			    				$export[$index]['fizzyDrinkBottle'] = $softdrinks["fizzyDrinkBottle"];
			    			}
			    			if($softdrinks['bottleLid']) {
			    				$export[$index]['bottleLid'] = $softdrinks["bottleLid"];
			    			}
			    			if($softdrinks['bottleLabel']) {
			    				$export[$index]['bottleLabel'] = $softdrinks["bottleLabel"];
			    			}
			    			if($softdrinks['tinCan']) {
			    				$export[$index]['tinCan'] = $softdrinks["tinCan"];
			    			}
			    			if($softdrinks['sportsDrink']) {
			    				$export[$index]['sportsDrink'] = $softdrinks["sportsDrink"];
			    			}
			    			if($softdrinks['paper_cups']) {
			    				$export[$index]['paper_cups'] = $softdrinks["paper_cups"];
			    			}
			    			if($softdrinks['juice_cartons']) {
			    				$export[$index]['juice_cartons'] = $softdrinks["juice_cartons"];
			    			}
			    			if($softdrinks['juice_bottles']) {
			    				$export[$index]['juice_bottles'] = $softdrinks["juice_bottles"];
			    			}
			    			if($softdrinks['juice_packet']) {
			    				$export[$index]['juice_packet'] = $softdrinks["juice_packet"];
			    			}
			    			if($softdrinks['ice_tea_bottles']) {
			    				$export[$index]['ice_tea_bottles'] = $softdrinks["ice_tea_bottles"];
			    			}
			    			if($softdrinks['ice_tea_can']) {
			    				$export[$index]['ice_tea_can'] = $softdrinks["ice_tea_can"];
			    			}
			    			if($softdrinks['energy_can']) {
			    				$export[$index]['energy_can'] = $softdrinks["energy_can"];
			    			}
			    			if($softdrinks['softDrinkOther']) {
			    				$export[$index]['softDrinkOther'] = $softdrinks["softDrinkOther"];
			    			}
			    		}

			    		if($photo["sanitary_id"]){
			    			$sanitary = Sanitary::find($photo["sanitary_id"]);
			    			if ($sanitary['gloves']) {
			    				$export[$index]['gloves'] = $sanitary['gloves'];
			    			}
			    			if ($sanitary['facemasks']) {
			    				$export[$index]['facemasks'] = $sanitary['facemasks'];
			    			}
			    			if($sanitary["condoms"]) {
			    				$export[$index]["condoms"] = $sanitary["condoms"];
			    			}
			    			if($sanitary["nappies"]) {
			    				$export[$index]["nappies"] = $sanitary["nappies"];
			    			}
			    			if($sanitary["menstral"]) {
			    				$export[$index]["menstral"] = $sanitary["menstral"];
			    			}
			    			if($sanitary["deodorant"]) {
			    				$export[$index]["deodorant"] = $sanitary["deodorant"];
			    			}
			    			if($sanitary["sanitaryOther"]) {
			    				$export[$index]["sanitaryOther"] = $sanitary["sanitaryOther"];
			    			}
			    		}

			    		if($photo['other_id']) {
			    			$other = Other::find($photo["other_id"]);
			    			if($other['dogshit']) {
			    				$export[$index]["dogshit"] = $other['dogshit'];
			    			}
			    			if($other["dump"]) {
			    				$export[$index]["Random_dump"] = $other["dump"];
			    			}
			    			if($other["plastic"]) {
			    				$export[$index]["No_id_plastic"] = $other["plastic"];
			    			}
			    			if($other["metal"]) {
			    				$export[$index]["Metal_object"] = $other["metal"];
			    			}
			    			if($other["washing_up"]) {
			    				$export[$index]["washing_up"] = $other["washing_up"];
			    			}
			    			if($other["hair_tie"]) {
			    				$export[$index]["hair_tie"] = $other["hair_tie"];
			    			}
			    			if($other["ear_plugs"]) {
			    				$export[$index]["ear_plugs"] = $other["ear_plugs"];
			    			}
			    			if($other["other"]) {
			    				$export[$index]["Unknown"] = $other["other"];
			    			}
			    		}

			    		if($photo['coastal_id']) {
			    			$coastal = Coastal::find($photo["coastal_id"]);
			    			if($coastal['microplastics']) {
			    				$export[$index]["microplastics"] = $coastal['microplastics'];
			    			}
			    			if($coastal['mediumplastics']) {
			    				$export[$index]["mediumplastics"] = $coastal['mediumplastics'];
			    			}
			    			if($coastal['macroplastics']) {
			    				$export[$index]['macroplastics'] = $coastal['macroplastics'];
			    			}
			    			if($coastal['rope_small']) {
			    				$export[$index]['rope_small'] = $coastal['rope_small'];
			    			}
			    			if($coastal['rope_medium']) {
			    				$export[$index]['rope_medium'] = $coastal['rope_medium'];
			    			}
			    			if($coastal['rope_large']) {
			    				$export[$index]['rope_large'] = $coastal['rope_large'];
			    			}
			    			if($coastal['fishing_gear_nets']) {
			    				$export[$index]['fishing_gear_nets'] = $coastal['fishing_gear_nets'];
			    			}
			    			if($coastal['buoys']) {
			    				$export[$index]['buoys'] = $coastal['buoys'];
			    			}
			    			if($coastal['degraded_plasticbottle']) {
			    				$export[$index]['degraded_plasticbottle'] = $coastal['degraded_plasticbottle'];
			    			}
			    			if($coastal['degraded_plasticbag']) {
			    				$export[$index]['degraded_plasticbag'] = $coastal['degraded_plasticbag'];
			    			}
			    			if($coastal['degraded_straws']) {
			    				$export[$index]['degraded_straws'] = $coastal['degraded_straws'];
			    			}
			    			if($coastal['degraded_lighters']) {
			    				$export[$index]['degraded_lighters'] = $coastal['degraded_lighters'];
			    			}
			    			if($coastal['baloons']) {
			    				$export[$index]['baloons'] = $coastal['baloons'];
			    			}
			    			if($coastal['lego']) {
			    				$export[$index]['lego'] = $coastal['lego'];
			    			}
			    			if($coastal['shotgun_cartridges']) {
			    				$export[$index]['shotgun_cartridges'] = $coastal['shotgun_cartridges'];
			    			}
			    			if($coastal['coastal_other']) {
			    				$export[$index]['coastal_other'];
			    			}
			    		}

			    		if($photo["pathway_id"]) {
			    			$pathway = Pathway::find($photo["pathway_id"]);
			    			if($pathway["gutter"]) {
			    				$export[$index]['gutter'] = $pathway["gutter"];
			    			}
			    			if($pathway["gutter_long"]) {
			    				$export[$index]['gutter_long'] = $pathway["gutter_long"];
			    			}
			    			if($pathway["kerb_hole_small"]) {
			    				$export[$index]['kerb_hole_small'] = $pathway["kerb_hole_small"];
			    			}
			    			if($pathway["kerb_hole_large"]) {
			    				$export[$index]['kerb_hole_large'] = $pathway["kerb_hole_large"];
			    			}
			    			if($pathway["pathwayOther"]) {
			    				$export[$index]['pathwayOther'] = $pathway["pathwayOther"];
			    			}
			    		}

			    		// if($photo["art_id"]) {
			    		// 	$art = Art::find($photo["art_id"]);
			    		// 	if($art['item']) {
			    		// 		$export[$index]['art'] = $art['item'];
			    		// 	}
			    		// }

			    		if($photo["brands_id"]) {
			    			$brands = Brand::find($photo["brands_id"]);
			    			if($brands["adidas"]) {
			    				$export[$index]['adidas'] = $brands["adidas"];
			    			}
			    			if($brands["amazon"]) {
			    				$export[$index]['amazon'] = $brands["amazon"];
			    			}
			    			if($brands["aldi"]) {
			    				$export[$index]['aldi'] = $brands["aldi"];
			    			}
			    			if($brands["apple"]) {
			    				$export[$index]['apple'] = $brands["apple"];
			    			}
			    			if($brands["applegreen"]) {
			    				$export[$index]['applegreen'] = $brands["applegreen"];
			    			}
			    			if($brands["asahi"]) {
			    				$export[$index]['asahi'] = $brands["asahi"];
			    			}
			    			if($brands["avoca"]) {
			    				$export[$index]['avoca'] = $brands["avoca"];
			    			}
			    			if($brands["ballygowan"]) {
			    				$export[$index]['ballygowan'] = $brands["ballygowan"];
			    			}
			    			if($brands["bewleys"]) {
			    				$export[$index]['bewleys'] = $brands["bewleys"];
			    			}
			    			if($brands["brambles"]) {
			    				$export[$index]['brambles'] = $brands["brambles"];
			    			}
			    			if($brands["budweiser"]) {
			    				$export[$index]['budweiser'] = $brands["budweiser"];
			    			}
			    			if($brands["bulmers"]) {
			    				$export[$index]['bulmers'] = $brands["bulmers"];
			    			}
			    			if($brands["burgerking"]) {
			    				$export[$index]['burgerking'] = $brands["burgerking"];
			    			}
			    			if($brands["butlers"]) {
			    				$export[$index]['butlers'] = $brands["butlers"];
			    			}

			    			if($brands["cadburys"]) {
			    				$export[$index]['cadburys'] = $brands["cadburys"];
			    			}
			    			if($brands["cafe_nero"]) {
			    				$export[$index]['cafe_nero'] = $brands["cafe_nero"];
			    			}
			    			if($brands["camel"]) {
			    				$export[$index]['camel'] = $brands["camel"];
			    			}
			    			if($brands["carlsberg"]) {
			    				$export[$index]['carlsberg'] = $brands["carlsberg"];
			    			}
			    			if($brands["centra"]) {
			    				$export[$index]['centra'] = $brands["centra"];
			    			}
			    			if($brands["coke"]) {
			    				$export[$index]['coke'] = $brands["coke"];
			    			}
			    			if($brands["circlek"]) {
			    				$export[$index]['circlek'] = $brands["circlek"];
			    			}
			    			if($brands["coles"]) {
			    				$export[$index]['coles'] = $brands["coles"];
			    			}
			    			if($brands["colgate"]) {
			    				$export[$index]['colgate'] = $brands["colgate"];
			    			}
			    			if($brands["corona"]) {
			    				$export[$index]['corona'] = $brands["corona"];
			    			}
			    			if($brands["costa"]) {
			    				$export[$index]['costa'] = $brands["costa"];
			    			}

			    			if($brands["doritos"]) {
			    				$export[$index]['doritos'] = $brands["doritos"];
			    			}
			    			if($brands["drpepper"]) {
			    				$export[$index]['drpepper'] = $brands["drpepper"];
			    			}
			    			if($brands["dunnes"]) {
			    				$export[$index]['dunnes'] = $brands["dunnes"];
			    			}
			    			if($brands["duracell"]) {
			    				$export[$index]['duracell'] = $brands["duracell"];
			    			}
			    			if($brands["durex"]) {
			    				$export[$index]['durex'] = $brands["durex"];
			    			}

			    			if($brands["esquires"]) {
			    				$export[$index]['esquires'] = $brands["esquires"];
			    			}

							if($brands["frank_and_honest"]) {
			    			   $export[$index]['frank_and_honest'] = $brands["frank_and_honest"];
			    			}
			    			if($brands["fritolay"]) {
			    				$export[$index]['fritolay'] = $brands["fritolay"];
			    			}

			    			if($brands["gatorade"]) {
			    				$export[$index]['gatorade'] = $brands["gatorade"];
			    			}
			    			if($brands["gillette"]) {
			    				$export[$index]['gillette'] = $brands["gillette"];
			    			}
			    			if($brands["guinness"]) {
			    				$export[$index]['guinness'] = $brands["gillette"];
			    			}

			    			if($brands["haribo"]) {
			    				$export[$index]['haribo'] = $brands["haribo"];
			    			}
			    			if($brands["heineken"]) {
			    				$export[$index]['heineken'] = $brands["heineken"];
			    			}

			    			if($brands["insomnia"]) {
			    				$export[$index]['insomnia'] = $brands["insomnia"];
			    			}

			    			if($brands["kellogs"]) {
			    				$export[$index]['kellogs'] = $brands["kellogs"];
			    			}
			    			if($brands["kfc"]) {
			    				$export[$index]['kfc'] = $brands["kfc"];
			    			}

			    			if($brands["lego"]) {
			    				$export[$index]['lego'] = $brands["lego"];
			    			}
			    			if($brands["lidl"]) {
			    				$export[$index]['lidl'] = $brands["lidl"];
			    			}
			    			if($brands["lindenvillage"]) {
			    				$export[$index]['lindenvillage'] = $brands["lindenvillage"];
			    			}
			    			if($brands["lolly_and_cookes"]) {
			    			   $export[$index]['lolly_and_cookes'] = $brands["lolly_and_cookes"];
			    			}
			    			if($brands["loreal"]) {
			    				$export[$index]['loreal'] = $brands["loreal"];
			    			}
			    			if($brands["lucozade"]) {
			    				$export[$index]['lucozade'] = $brands["lucozade"];
			    			}

			    			if($brands["marlboro"]) {
			    				$export[$index]['marlboro'] = $brands["marlboro"];
			    			}
			    			if($brands["mars"]) {
			    				$export[$index]['mars'] = $brands["mars"];
			    			}
			    			if($brands["mcdonalds"]) {
			    				$export[$index]['mcdonalds'] = $brands["mcdonalds"];
			    			}

			    			if($brands["nero"]) {
			    				$export[$index]['nero'] = $brands["nero"];
			    			}
			    			if($brands["nescafe"]) {
			    				$export[$index]['nescafe'] = $brands["nescafe"];
			    			}
			    			if($brands["nestle"]) {
			    				$export[$index]['nestle'] = $brands["nestle"];
			    			}
			    			if($brands["nike"]) {
			    				$export[$index]['nike'] = $brands["nike"];
			    			}

			    			if($brands["obriens"]) {
			    				$export[$index]['obriens'] = $brands["obriens"];
			    			}

			    			if($brands["pepsi"]) {
			    				$export[$index]['pepsi'] = $brands["pepsi"];
			    			}
			    			if($brands["powerade"]) {
			    				$export[$index]['powerade'] = $brands["powerade"];
			    			}

			    			if($brands["redbull"]) {
			    				$export[$index]['redbull'] = $brands["redbull"];
			    			}
			    			if($brands["ribena"]) {
			    				$export[$index]['ribena'] = $brands["ribena"];
			    			}

			    			if($brands["samsung"]) {
			    				$export[$index]['samsung'] = $brands["samsung"];
			    			}
			    			if($brands["sainsburys"]) {
			    				$export[$index]['sainsburys'] = $brands["sainsburys"];
			    			}
			    			if($brands["spar"]) {
			    				$export[$index]['spar'] = $brands["spar"];
			    			}
			    			if($brands["stella"]) {
			    				$export[$index]['stella'] = $brands["stella"];
			    			}
			    			if($brands["subway"]) {
			    				$export[$index]['subway'] = $brands["subway"];
			    			}
			    			if($brands["supermacs"]) {
			    				$export[$index]['supermacs'] = $brands["supermacs"];
			    			}
			    			if($brands["supervale"]) {
			    				$export[$index]['supervale'] = $brands["supervale"];
			    			}
			    			if($brands["starbucks"]) {
			    				$export[$index]['starbucks'] = $brands["starbucks"];
			    			}

			    			if($brands["tayto"]) {
			    				$export[$index]['tayto'] = $brands["tayto"];
			    			}
			    			if($brands["tesco"]) {
			    				$export[$index]['tesco'] = $brands["tesco"];
			    			}
			    			if($brands["thins"]) {
			    				$export[$index]['thins'] = $brands["thins"];
			    			}

			    			if($brands["volvic"]) {
			    				$export[$index]['volvic'] = $brands["volvic"];
			    			}

			    			if($brands["waitrose"]) {
			    				$export[$index]['waitrose'] = $brands["waitrose"];
			    			}
			    			if($brands["walkers"]) {
			    				$export[$index]['walkers'] = $brands["walkers"];
			    			}
			    			if($brands["woolworths"]) {
			    				$export[$index]['woolworths'] = $brands["woolworths"];
			    			}
			    			if($brands["wilde_and_greene"]) {
			    			   $export[$index]['wilde_and_greene'] = $brands["wilde_and_greene"];
			    			}
			    			if($brands["wrigleys"]) {
			    				$export[$index]['wrigleys'] = $brands["wrigleys"];
			    			}
			    		}
			    	}
			    	// return $export;
	    			$sheet->fromModel($export);
		    	})->export('csv');
	    		// $excel->setCreator('OpenLitterMap')->setCompany('GeoTech Innovations Ltd.');
	    	});
    	}
   	}
}
