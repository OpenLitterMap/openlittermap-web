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

    public $location_type, $location_id, $team_id, $user_id;

    /**
     * Init args
     */
    public function __construct ($location_type, $location_id, $team_id = null, $user_id = null)
    {
        $this->location_type = $location_type;
        $this->location_id = $location_id;
        $this->team_id = $team_id;
        $this->user_id = $user_id;
    }

    /**
     * Define column titles
     *
     * Todo - Add Country / State / City name
     * Todo - Allow the user to determine what data they want on the frontend
     * Todo - Import these from elsewhere
     * Todo - Separate brands by country
     * Todo - Insert translated string instead of hard-coded 1-language title
     * Todo - When downloading per team, show team member, if privacy true. (We need to create the privacy option first).
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
//            'city',
//            'state',
//            'country',
            'picked up',
            'address',
            'total_litter',

            'SMOKING',

            'cigarette_butt',
            'lighter',
            'cigarette_box',
            'tobacco_pouch',
            'rolling_paper',
            'plastic_smoking_packaging',
            'filter',
            'filterbox',
            'vape_pen',
            'vape_oil',
            'smoking_other',

            'FOOD',

            'sweet_wrapper',
            'paper_cardboard_food_packaging',
            'plastic_food_packaging',
            'plastic_cutlery',
            'crisp_small',
            'crisp_large',
            'styrofoam_plate',
            'napkin',
            'sauce_packet',
            'glass_jar',
            'glass_jar_lid',
            'pizza_box',
            'aluminium_foil',
            'food_other',

            'COFFEE',

            'coffee_cup',
            'coffee_lid',
            'coffee_other',

            'ALCOHOL',

            'beer_can',
            'beer_bottle',
            'spirit_bottle',
            'wine_bottle',
            'broken_glass',
            'paper_card_alcohol_packaging',
            'plastic_alcohol_packaging',
            'beer_bottle_top',
            'six_pack_ring',
            'alcohol_plastic_cup',
            'pint_glass',
            'alcohol_other',

            'SOFTDRINKS',

            'plastic_water_bottle',
            'plastic_fizzy_drink_bottle',
            'softdrink_bottle_top',
            'bottle_label',
            'tin_can',
            'pull_ring',
            'sports_drink',
            'straw',
            'straw_packaging',
            'softdrink_plastic_cup',
            'plastic_cup_top',
            'milk_bottle',
            'milk_carton',
            'paper_cup',
            'juice_carton',
            'juice_bottle',
            'juice_packet',
            'ice_tea_bottle',
            'ice_tea_can',
            'energy_can',
            'styrofoam_cup',
            'softdrink_other',
            'broken_glass',

            'SANITARY',

            'glove',
            'facemask',
            'condom',
            'wet_wipe',
            'nappies',
            'menstral',
            'deodorant',
            'ear_swab',
            'tooth_pick',
            'tooth_brush',
            'hand_sanitiser',
            'sanitary_other',

            'OTHER',

            'dog_poo',
            'random_litter',
            'bag_of_litter',
            'dog_poo_in_a_bag',
            'automobile',
            'clothing',
            'traffic_cone',
            'life_buoy',
            'unidentified_plastic',
            'overflowing_bin',
            'tyre',
            'cable_tie',
            'balloon',
            'illegal_dumping',
            'metal_object',
            'plastic_bag',
            'election_poster',
            'for_sale_poster',
            'book',
            'magazine',
            'paper',
            'stationary',
            'washing_up',
            'hair_tie',
            'ear_plugs_music',
            'battery',
            'electric_small',
            'electric_large',
            'other_other',

            'DUMPING',

            'dumping_small',
            'dumping_medium',
            'dumping_large',

            'INDUSTRIAL',

            'oil',
            'chemical',
            'plastic',
            'bricks',
            'tape',
            'other',

            'COASTAL',

            'microplastic',
            'mediumplastic',
            'macroplastic',
            'rope_small',
            'rope_medium',
            'rope_large',
            'fishing_gear_net',
            'ghost_nets',
            'styrofoam_small',
            'styrofoam_medium',
            'styrofoam_large',
            'buoy',
            'degraded_plastic_bottle',
            'degraded_plastic_bag',
            'degraded_straw',
            'degraded_lighter',
            'coastal_balloon',
            'lego',
            'shotgun_cartridge',
            'coastal_other',

            'BRANDS',

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
            'coca_cola',
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
            'evian',

            'fosters',
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
            'stella',
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
            'wrigleys',

            'ART',

            'item',
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
            $row->verified,
            $row->model,
            $row->datetime,
            $row->lat,
            $row->lon,
//            $row->city_id, // todo -> name
//            $row->state_id, // todo -> name
//            $row->country_id, // todo -> name
            $row->remaining ? 'No' : 'Yes', // column name is "picked up"
            $row->address,
            $row->total_litter,

            NULL,

            $row->smoking ? $row->smoking->butts : null,
            $row->smoking ? $row->smoking->lighters : null,
            $row->smoking ? $row->smoking->cigaretteBox : null,
            $row->smoking ? $row->smoking->tobaccoPouch : null,
            $row->smoking ? $row->smoking->skins : null,
            $row->smoking ? $row->smoking->smoking_plastic : null,
            $row->smoking ? $row->smoking->filters : null,
            $row->smoking ? $row->smoking->filterbox : null,
            $row->smoking ? $row->smoking->vape_pen : null,
            $row->smoking ? $row->smoking->vape_oil : null,
            $row->smoking ? $row->smoking->smokingOther : null,

            NULL,

            $row->food ? $row->food->sweetWrappers : null,
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
            $row->food ? $row->food->pizza_box : null,
            $row->food ? $row->food->aluminium_foil : null,
            $row->food ? $row->food->foodOther : null,

            NULL,

            $row->coffee ? $row->coffee->coffeeCups : null,
            $row->coffee ? $row->coffee->coffeeLids : null,
            $row->coffee ? $row->coffee->coffeeOther : null,

            NULL,

            $row->alcohol ? $row->alcohol->beerCan : null,
            $row->alcohol ? $row->alcohol->beerBottle : null,
            $row->alcohol ? $row->alcohol->spiritBottle : null,
            $row->alcohol ? $row->alcohol->wineBottle : null,
            $row->alcohol ? $row->alcohol->brokenGlass : null,
            $row->alcohol ? $row->alcohol->paperCardAlcoholPackaging : null,
            $row->alcohol ? $row->alcohol->plasticAlcoholPackaging : null,
            $row->alcohol ? $row->alcohol->bottleTops : null,
            $row->alcohol ? $row->alcohol->six_pack_rings : null,
            $row->alcohol ? $row->alcohol->alcohol_plastic_cups : null,
            $row->alcohol ? $row->alcohol->pint : null,
            $row->alcohol ? $row->alcohol->alcoholOther : null,

            NULL,

            $row->softdrinks ? $row->softdrinks->waterBottle : null,
            $row->softdrinks ? $row->softdrinks->fizzyDrinkBottle : null,
            $row->softdrinks ? $row->softdrinks->bottleLid : null,
            $row->softdrinks ? $row->softdrinks->bottleLabel : null,
            $row->softdrinks ? $row->softdrinks->tinCan : null,
            $row->softdrinks ? $row->softdrinks->pullring : null,
            $row->softdrinks ? $row->softdrinks->sportsDrink : null,
            $row->softdrinks ? $row->softdrinks->straws : null,
            $row->softdrinks ? $row->softdrinks->strawpacket : null,
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
            $row->softdrinks ? $row->softdrinks->styro_cup : null,
            $row->softdrinks ? $row->softdrinks->softDrinkOther : null,
            $row->softdrinks ? $row->softdrinks->broken_glass : null,

            NULL,

            $row->sanitary ? $row->sanitary->gloves : null,
            $row->sanitary ? $row->sanitary->facemask : null,
            $row->sanitary ? $row->sanitary->condoms : null,
            $row->sanitary ? $row->sanitary->wetwipes : null,
            $row->sanitary ? $row->sanitary->nappies : null,
            $row->sanitary ? $row->sanitary->menstral : null,
            $row->sanitary ? $row->sanitary->deodorant : null,
            $row->sanitary ? $row->sanitary->ear_swabs : null,
            $row->sanitary ? $row->sanitary->tooth_pick : null,
            $row->sanitary ? $row->sanitary->tooth_brush : null,
            $row->sanitary ? $row->sanitary->hand_sanitiser : null,
            $row->sanitary ? $row->sanitary->sanitaryOther : null,

            NULL,

            $row->other ? $row->other->dogshit : null,
            $row->other ? $row->other->random_litter : null,
            $row->other ? $row->other->bags_litter : null,
            $row->other ? $row->other->pooinbag : null,
            $row->other ? $row->other->automobile : null,
            $row->other ? $row->other->clothing : null,
            $row->other ? $row->other->traffic_cone : null,
            $row->other ? $row->other->life_buoy : null,
            $row->other ? $row->other->plastic : null,
            $row->other ? $row->other->overflowing_bins : null,
            $row->other ? $row->other->tyre : null,
            $row->other ? $row->other->cable_tie : null,
            $row->other ? $row->other->balloons : null,
            $row->other ? $row->other->dump : null,
            $row->other ? $row->other->metal : null,
            $row->other ? $row->other->plastic_bags : null,
            $row->other ? $row->other->election_posters : null,
            $row->other ? $row->other->forsale_posters : null,
            $row->other ? $row->other->books : null,
            $row->other ? $row->other->magazine : null,
            $row->other ? $row->other->paper : null,
            $row->other ? $row->other->stationary : null,
            $row->other ? $row->other->washing_up : null,
            $row->other ? $row->other->hair_tie : null,
            $row->other ? $row->other->ear_plugs : null,
            $row->other ? $row->other->batteries : null,
            $row->other ? $row->other->elec_small : null,
            $row->other ? $row->other->elec_large : null,
            $row->other ? $row->other->other : null,

            NULL,

            $row->dumping ? $row->dumping->small : null,
            $row->dumping ? $row->dumping->medium : null,
            $row->dumping ? $row->dumping->large : null,

            NULL,

            $row->industrial ? $row->industrial->oil : null,
            $row->industrial ? $row->industrial->chemical : null,
            $row->industrial ? $row->industrial->industrial_plastic : null,
            $row->industrial ? $row->industrial->bricks : null,
            $row->industrial ? $row->industrial->tape : null,
            $row->industrial ? $row->industrial->industrial_other : null,

            NULL,

            $row->coastal ? $row->coastal->microplastics : null,
            $row->coastal ? $row->coastal->mediumplastics : null,
            $row->coastal ? $row->coastal->macroplastics : null,
            $row->coastal ? $row->coastal->rope_small : null,
            $row->coastal ? $row->coastal->rope_medium : null,
            $row->coastal ? $row->coastal->rope_large : null,
            $row->coastal ? $row->coastal->fishing_gear_nets : null,
            $row->coastal ? $row->coastal->ghost_nets : null,
            $row->coastal ? $row->coastal->styro_small : null,
            $row->coastal ? $row->coastal->styro_medium : null,
            $row->coastal ? $row->coastal->styro_large : null,
            $row->coastal ? $row->coastal->buoys : null,
            $row->coastal ? $row->coastal->degraded_plasticbottle : null,
            $row->coastal ? $row->coastal->degraded_plasticbag : null,
            $row->coastal ? $row->coastal->degraded_straws : null,
            $row->coastal ? $row->coastal->degraded_lighters : null,
            $row->coastal ? $row->coastal->balloons : null,
            $row->coastal ? $row->coastal->lego : null,
            $row->coastal ? $row->coastal->shotgun_cartridges : null,
            $row->coastal ? $row->coastal->coastal_other : null,

            NULL,

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
            $row->brands ? $row->brands->evian : null,

            $row->brands ? $row->brands->fosters : null,
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
            $row->brands ? $row->brands->stella : null,
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
            $row->brands ? $row->brands->wrigleys : null,

            NULL,

            $row->art ? $row->art->item : null,
        ];
    }

    /**
     * Create a query which we will loop over in the map function
     * no need to use ->get();
     */
    public function query ()
    {
        if ($this->user_id)
        {
            return Photo::with(['smoking', 'food', 'coffee', 'alcohol', 'softdrinks', 'other', 'sanitary', 'brands', 'dumping', 'industrial', 'art'])
                ->where([
                    'user_id' => $this->user_id
                ]);
        }

        else if ($this->team_id)
        {
            return Photo::with(['smoking', 'food', 'coffee', 'alcohol', 'softdrinks', 'other', 'sanitary', 'brands', 'dumping', 'industrial', 'art'])
                ->where([
                    'team_id' => $this->team_id,
                    'verified' => 2
                ]);
        }

        else
        {
            if ($this->location_type === 'city')
            {
                return Photo::with(['smoking', 'food', 'coffee', 'alcohol', 'softdrinks', 'other', 'sanitary', 'brands', 'dumping', 'industrial', 'art'])
                    ->where([
                        'city_id' => $this->location_id,
                        'verified' => 2
                    ]);
            }

            else if ($this->location_type === 'state')
            {
                return Photo::with(['smoking', 'food', 'coffee', 'alcohol', 'softdrinks', 'other', 'sanitary', 'brands', 'dumping', 'industrial', 'art'])
                    ->where([
                        'state_id' => $this->location_id,
                        'verified' => 2
                    ]);
            }

            else
            {
                return Photo::with(['smoking', 'food', 'coffee', 'alcohol', 'softdrinks', 'other', 'sanitary', 'brands', 'dumping', 'industrial', 'art'])
                    ->where([
                        'country_id' => $this->location_id,
                        'verified' => 2
                    ]);
            }
        }
    }
}
