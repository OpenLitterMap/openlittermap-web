<?php

namespace App\Exports;

use App\Models\Photo;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CreateCSVExport implements FromQuery, WithMapping, WithHeadings
{
    use Exportable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $location_type, $location_id;

    /**
     * Init args
     */
    public function __construct ($location_type, $location_id)
    {
        $this->location_type = $location_type;
        $this->location_id = $location_id;
    }

    /**
     * Define column titles
     *
     * Todo - Allow the user to determine what data they want on the frontend
     * Todo - Import these from elsewhere
     * Todo - Separate brands by country
     */
    public function headings (): array
    {
        return [
            'id',
            'verification',
            'phone',
            'datetime',
            'lat',
            'lon',
            'city',
            'state',
            'country',
            'remaining_beta',
            'address',
            'total_litter',

            // Smoking
            'cigaretteButts',
            'lighters',
            'cigaretteBox',
            'tobaccoPouch',
            'papers_filters',
            'plastic_smoking_pk',
            'filters',
            'filterbox',
            'smokingOther',

            // Food
            'sweetWrappers',
            'cardboardFoodPackaging',
            'paperFoodPackaging',
            'plasticFoodPackaging',
            'plasticCutlery',
            'crisp_small',
            'crisp_large',
            'styrofoam_plate',
            'napkins',
            'sauce_packet',
            'glass_jar',
            'glass_jar_lid',
            'foodOther',

            // Coffee
            'coffeeCups',
            'coffeeLids',
            'coffeeOther',

            // Alcohol
            'beerCan',
            'beerBottle',
            'spiritBottle',
            'wineBottle',
            'brokenGlass',
            'paperCardAlcoholPackaging',
            'plasticAlcoholPackaging',
            'bottleTops',
            'alcoholOther',

            // SoftDrinks
            'plasticWaterBottle',
            'fizzyDrinkBottle',
            'bottleLid',
            'bottleLabel',
            'tinCan',
            'sportsDrink',
            'straws',
            'plastic_cups',
            'plastic_cup_tops',
            'milk_bottle',
            'milk_carton',
            'paper_cups',
            'juice_cartons',
            'juice_bottles',
            'juice_packet',
            'ice_tea_bottles',
            'ice_tea_can',
            'energy_can',
            'softDrinkOther',

            // Sanitary
            'gloves',
            'facemasks',
            'condoms',
            'mental',
            'deodorant',
            'ear_swabs',
            'tooth_pick',
            'tooth_brush',
            'sanitaryOther',

            // Other
            'dogshit',
            'Random_dump',
            'No_id_plastic',
            'Metal_object',
            'plastic_bags',
            'election_posters',
            'forsale_posters',
            'books',
            'magazines',
            'paper',
            'stationary',
            'washing_up',
            'hair_tie',
            'ear_plugs',
            'batteries',
            'elec_small',
            'elec_large',
            'Other_Unknown',

            // Coastal
            'microplastics',
            'mediumplastics',
            'macroplastics',
            'rope_small',
            'rope_medium',
            'rope_large',
            'fishing_gear_nets',
            'buoys',
            'degraded_plasticbottle',
            'degraded_plasticbag',
            'degraded_straws',
            'degraded_lighters',
            'baloons',
            'lego',
            'shotgun_cartridges',
            'coastal_other',

            'adidas',
            'amazon',
            'aldi',
            'apple',
            'applegreen',
            'asahi',
            'avoca',

            'ballygowan',
            'bewleys',
            'brambles',
            'budweiser',
            'bulmers',
            'burgerking',
            'butlers',

            'cadburys',
            'cafe_nero',
            'camel',
            'carlsberg',
            'centra',
            'coke',
            'circlek',
            'coles',
            'colgate',
            'corona',
            'costa',

            'doritos',
            'drpepper',
            'dunnes',
            'duracell',
            'durex',

            'esquires',

            'frank_and_honest',
            'fritolay',

            'gatorade',
            'gillette',
            'guinness',

            'haribo',
            'heineken',

            'insomnia',

            'kellogs',
            'kfc',

            'lego',
            'lidl',
            'lindenvillage',
            'lolly_and_cookes',
            'loreal',
            'lucozade',

            'nero',
            'nescafe',
            'nestle',

            'marlboro',
            'mars',
            'mcdonalds',

            'nike',

            'obriens',

            'pepsi',
            'powerade',

            'redbull',
            'ribena',

            'samsung',
            'sainsburys',
            'spar',
            'subway',
            'supermacs',
            'supervalu',
            'starbucks',

            'tayto',
            'tesco',
            'thins',

            'volvic',

            'waitrose',
            'walkers',
            'woolworths',
            'wilde_and_greene',
            'wrigleys'
        ];
    }

    /**
     * Map over query response
     * This will insert the each row under each heading
     */
    public function map ($row): array
    {
        return [
            $row->id,
            $row->verification,
            $row->phone,
            $row->datetime,
            $row->lat,
            $row->lon,
            $row->city_id, // todo -> name
            $row->state_id, // todo -> name
            $row->country_id, // todo -> name
            $row->remaining_beta,
            $row->address,
            $row->total_litter,

            // Smoking
            $row->smoking ? $row->smoking->butts : null,
            $row->smoking ? $row->smoking->lighters : null,
            $row->smoking ? $row->smoking->cigaretteBox : null,
            $row->smoking ? $row->smoking->tobaccoPouch : null,
            $row->smoking ? $row->smoking->skins : null,
            $row->smoking ? $row->smoking->plastic_smoking_pk : null,
            $row->smoking ? $row->smoking->filters : null,
            $row->smoking ? $row->smoking->filterbox : null,
            $row->smoking ? $row->smoking->smokingOther : null,

            // Food
            $row->food ? $row->food->sweetWrappers : null,
            $row->food ? $row->food->cardboardFoodPackaging : null,
            $row->food ? $row->food->paperFoodPackaging : null,
            $row->food ? $row->food->plasticFoodPackaging : null,
            $row->food ? $row->food->plasticCutlery : null,
            $row->food ? $row->food->crisp_small : null,
            $row->food ? $row->food->crisp_large : null,
            $row->food ? $row->food->styrofoam_plate : null,
            $row->food ? $row->food->napkins : null,
            $row->food ? $row->food->sauce_packet : null,
            $row->food ? $row->food->glass_jar : null,
            $row->food ? $row->food->glass_jar_lid : null,
            $row->food ? $row->food->foodOther : null,

            // Coffee
            $row->coffee ? $row->coffee->coffeeCups : null,
            $row->coffee ? $row->coffee->coffeeLids : null,
            $row->coffee ? $row->coffee->coffeeOther : null,

            // Alcohol
            $row->alcohol ? $row->alcohol->beerCan : null,
            $row->alcohol ? $row->alcohol->beerBottle : null,
            $row->alcohol ? $row->alcohol->spiritBottle : null,
            $row->alcohol ? $row->alcohol->wineBottle : null,
            $row->alcohol ? $row->alcohol->brokenGlass : null,
            $row->alcohol ? $row->alcohol->paperCardAlcoholPackaging : null,
            $row->alcohol ? $row->alcohol->plasticAlcoholPackaging : null,
            $row->alcohol ? $row->alcohol->bottleTops : null,
            $row->alcohol ? $row->alcohol->alcoholOther : null,

            // SoftDrinks
            $row->softdrinks ? $row->softdrinks->plasticWaterBottle : null,
            $row->softdrinks ? $row->softdrinks->fizzyDrinkBottle : null,
            $row->softdrinks ? $row->softdrinks->bottleLid : null,
            $row->softdrinks ? $row->softdrinks->bottleLabel : null,
            $row->softdrinks ? $row->softdrinks->tinCan : null,
            $row->softdrinks ? $row->softdrinks->sportsDrink : null,
            $row->softdrinks ? $row->softdrinks->straws : null,
            $row->softdrinks ? $row->softdrinks->plastic_cups : null,
            $row->softdrinks ? $row->softdrinks->plastic_cup_tops : null,
            $row->softdrinks ? $row->softdrinks->milk_bottle : null,
            $row->softdrinks ? $row->softdrinks->milk_carton : null,
            $row->softdrinks ? $row->softdrinks->paper_cups : null,
            $row->softdrinks ? $row->softdrinks->juice_cartons : null,
            $row->softdrinks ? $row->softdrinks->juice_bottles : null,
            $row->softdrinks ? $row->softdrinks->juice_packet : null,
            $row->softdrinks ? $row->softdrinks->ice_tea_bottles : null,
            $row->softdrinks ? $row->softdrinks->ice_tea_can : null,
            $row->softdrinks ? $row->softdrinks->energy_can : null,
            $row->softdrinks ? $row->softdrinks->softDrinkOther : null,

            // Sanitary
            $row->sanitary ? $row->sanitary->gloves : null,
            $row->sanitary ? $row->sanitary->facemasks : null,
            $row->sanitary ? $row->sanitary->condoms : null,
            $row->sanitary ? $row->sanitary->mental : null,
            $row->sanitary ? $row->sanitary->deodorant : null,
            $row->sanitary ? $row->sanitary->ear_swabs : null,
            $row->sanitary ? $row->sanitary->tooth_pick : null,
            $row->sanitary ? $row->sanitary->tooth_brush : null,
            $row->sanitary ? $row->sanitary->sanitaryOther : null,

            // Other
            $row->other ? $row->other->dogshit : null,
            $row->other ? $row->other->Random_dump : null,
            $row->other ? $row->other->No_id_plastic : null,
            $row->other ? $row->other->Metal_object : null,
            $row->other ? $row->other->plastic_bags : null,
            $row->other ? $row->other->election_posters : null,
            $row->other ? $row->other->forsale_posters : null,
            $row->other ? $row->other->books : null,
            $row->other ? $row->other->magazines : null,
            $row->other ? $row->other->paper : null,
            $row->other ? $row->other->stationary : null,
            $row->other ? $row->other->washing_up : null,
            $row->other ? $row->other->hair_tie : null,
            $row->other ? $row->other->ear_plugs : null,
            $row->other ? $row->other->batteries : null,
            $row->other ? $row->other->elec_small : null,
            $row->other ? $row->other->elec_large : null,
            $row->other ? $row->other->Other_Unknown : null,

            // Coastal
            $row->coastal ? $row->coastal->microplastics : null,
            $row->coastal ? $row->coastal->mediumplastics : null,
            $row->coastal ? $row->coastal->macroplastics : null,
            $row->coastal ? $row->coastal->rope_small : null,
            $row->coastal ? $row->coastal->rope_medium : null,
            $row->coastal ? $row->coastal->rope_large : null,
            $row->coastal ? $row->coastal->fishing_gear_nets : null,
            $row->coastal ? $row->coastal->buoys : null,
            $row->coastal ? $row->coastal->degraded_plasticbottle : null,
            $row->coastal ? $row->coastal->degraded_plasticbag : null,
            $row->coastal ? $row->coastal->degraded_straws : null,
            $row->coastal ? $row->coastal->degraded_lighters : null,
            $row->coastal ? $row->coastal->baloons : null,
            $row->coastal ? $row->coastal->lego : null,
            $row->coastal ? $row->coastal->shotgun_cartridges : null,
            $row->coastal ? $row->coastal->coastal_other : null,

            $row->brands ? $row->brands->adidas : null,
            $row->brands ? $row->brands->amazon : null,
            $row->brands ? $row->brands->aldi : null,
            $row->brands ? $row->brands->apple : null,
            $row->brands ? $row->brands->applegreen : null,
            $row->brands ? $row->brands->asahi : null,
            $row->brands ? $row->brands->avoca : null,

            $row->brands ? $row->brands->ballygowan : null,
            $row->brands ? $row->brands->bewleys : null,
            $row->brands ? $row->brands->brambles : null,
            $row->brands ? $row->brands->budweiser : null,
            $row->brands ? $row->brands->bulmers : null,
            $row->brands ? $row->brands->burgerking : null,
            $row->brands ? $row->brands->butlers : null,

            $row->brands ? $row->brands->cadburys : null,
            $row->brands ? $row->brands->cafe_nero : null,
            $row->brands ? $row->brands->camel : null,
            $row->brands ? $row->brands->carlsberg : null,
            $row->brands ? $row->brands->centra : null,
            $row->brands ? $row->brands->coke : null,
            $row->brands ? $row->brands->circlek : null,
            $row->brands ? $row->brands->coles : null,
            $row->brands ? $row->brands->colgate : null,
            $row->brands ? $row->brands->corona : null,
            $row->brands ? $row->brands->costa : null,

            $row->brands ? $row->brands->doritos : null,
            $row->brands ? $row->brands->drpepper : null,
            $row->brands ? $row->brands->dunnes : null,
            $row->brands ? $row->brands->duracell : null,
            $row->brands ? $row->brands->durex : null,

            $row->brands ? $row->brands->esquires : null,

            $row->brands ? $row->brands->frank_and_honest : null,
            $row->brands ? $row->brands->fritolay : null,

            $row->brands ? $row->brands->gatorade : null,
            $row->brands ? $row->brands->gillette : null,
            $row->brands ? $row->brands->guinness : null,

            $row->brands ? $row->brands->haribo : null,
            $row->brands ? $row->brands->heineken : null,

            $row->brands ? $row->brands->insomnia : null,

            $row->brands ? $row->brands->kellogs : null,
            $row->brands ? $row->brands->kfc : null,

            $row->brands ? $row->brands->lego : null,
            $row->brands ? $row->brands->lidl : null,
            $row->brands ? $row->brands->lindenvillage : null,
            $row->brands ? $row->brands->lolly_and_cookes : null,
            $row->brands ? $row->brands->loreal : null,
            $row->brands ? $row->brands->lucozade : null,

            $row->brands ? $row->brands->nero : null,
            $row->brands ? $row->brands->nescafe : null,
            $row->brands ? $row->brands->nestle : null,

            $row->brands ? $row->brands->marlboro : null,
            $row->brands ? $row->brands->mars : null,
            $row->brands ? $row->brands->mcdonalds : null,

            $row->brands ? $row->brands->nike : null,

            $row->brands ? $row->brands->obriens : null,

            $row->brands ? $row->brands->pepsi : null,
            $row->brands ? $row->brands->powerade : null,

            $row->brands ? $row->brands->redbull : null,
            $row->brands ? $row->brands->ribena : null,

            $row->brands ? $row->brands->samsung : null,
            $row->brands ? $row->brands->sainsburys : null,
            $row->brands ? $row->brands->spar : null,
            $row->brands ? $row->brands->subway : null,
            $row->brands ? $row->brands->supermacs : null,
            $row->brands ? $row->brands->supervalu : null,
            $row->brands ? $row->brands->starbucks : null,

            $row->brands ? $row->brands->tayto : null,
            $row->brands ? $row->brands->tesco : null,
            $row->brands ? $row->brands->thins : null,

            $row->brands ? $row->brands->volvic : null,

            $row->brands ? $row->brands->waitrose : null,
            $row->brands ? $row->brands->walkers : null,
            $row->brands ? $row->brands->woolworths : null,
            $row->brands ? $row->brands->wilde_and_greene : null,
            $row->brands ? $row->brands->wrigleys : null
        ];
    }

    /**
     * Create a query which we will loop over in the map function
     * no need to use ->get();
     *
     * Todo - Dumping, Industrial
     */
    public function query ()
    {
        if ($this->location_type === 'city')
        {
            return Photo::with(['smoking', 'food', 'coffee', 'alcohol', 'softdrinks', 'other', 'sanitary', 'brands'])
                ->where([
                    'city_id' => $this->location_id,
                    'verified' => 2
                ]);
        }

        else if ($this->location_type === 'state')
        {
            return Photo::with(['smoking', 'food', 'coffee', 'alcohol', 'softdrinks', 'other', 'sanitary', 'brands'])
                ->where([
                    'state_id' => $this->location_id,
                    'verified' => 2
                ]);
        }

        else
        {
            \Log::info(['location.id', $this->location_id]);
            $data = Photo::with(['smoking', 'food', 'coffee', 'alcohol', 'softdrinks', 'other', 'sanitary', 'brands'])
                ->where([
                    'country_id' => $this->location_id,
                    'verified' => 2
                ]);

            $size = $data->count();

            \Log::info(['sizeof.data', $size]);

            return $data;
        }
    }
}
