<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{

	protected $fillable = [
		'id', 
		'city',
		'country_id',
		'created_at',
		'updated_at',
		'total_images',
		'total_smoking',
		'total_cigaretteButts',
		'total_food',
		'total_softDrinks',
		'total_plasticBottles',
		'total_alcohol',
		'total_coffee',
		'total_drugs',
        'total_dumping',
        'total_industrial',
		'total_needles',
		'total_sanitary',
		'total_other',
		'total_coastal',
		'total_pathways',
		'total_art',
		'manual_verify',
		'littercoin_paid',
		'created_by'
	];

	/**
     * Extra columns on our Country model
     */
    protected $appends = [
        'litter_data',
        'brands_data'
    ];

    /**
     *  @return 'litter_data' column
     */
    public function getLitterDataAttribute ()
    {
        return [
            'smoking' => $this->total_smoking,
               'food' => $this->total_food,
         'softdrinks' => $this->total_softDrinks,
            'alcohol' => $this->total_alcohol,
             'coffee' => $this->total_coffee,
           'sanitary' => $this->total_sanitary,
              'other' => $this->total_other,
            'coastal' => $this->total_coastal
        ];
    }

    /**
     * @return 'brands_data' column
     * Todo - organize this by country 
     *      - every country should have a different list of brands associated with it
     */
    public function getBrandsDataAttribute()
    {
        return [
            'adidas' => $this->total_adidas,
            'amazon' => $this->total_amazon,
             'apple' => $this->total_apple,
        'applegreen' => $this->total_applegreen,
             'avoca' => $this->total_avoca,
           'bewleys' => $this->total_bewleys,
          'brambles' => $this->total_brambles,
           'butlers' => $this->total_butlers,
         'budweiser' => $this->total_budweiser,
         'cafe_nero' => $this->total_cafe_nero,
            'centra' => $this->total_centra,
              'coke' => $this->total_coke,
           'colgate' => $this->total_colgate,
            'corona' => $this->total_corona,
             'costa' => $this->total_costa,
          'esquires' => $this->total_esquires,
  'frank_and_honest' => $this->total_frank_and_honest,
          'fritolay' => $this->total_fritolay,
          'gillette' => $this->total_gillette,
          'heineken' => $this->total_heineken,
          'insomnia' => $this->total_insomnia,
           'kellogs' => $this->total_kellogs,
              'lego' => $this->total_lego,
  'lolly_and_cookes' => $this->total_lolly_and_cookes,
            'loreal' => $this->total_loreal,
           'nescafe' => $this->total_nescafe,
            'nestle' => $this->total_nestle,
          'marlboro' => $this->total_marlboro,
         'mcdonalds' => $this->total_mcdonalds,
              'nike' => $this->total_nike,
           'obriens' => $this->total_obriens,
             'pepsi' => $this->total_pepsi,
           'redbull' => $this->total_redbull,
           'samsung' => $this->total_samsung,
            'subway' => $this->total_subway,
         'supermacs' => $this->total_supermacs,
         'starbucks' => $this->total_starbucks,
             'tayto' => $this->total_tayto,
  'wilde_and_greene' => $this->total_wilde_and_greene
        ];
    }

    /**
     * Hide the columns to avoid duplication
     */
    protected $hidden = [
        'total_smoking',
        'total_food',
        'total_alcohol',
        'total_softDrinks',
        'total_coffee',
        'total_sanitary',
        'total_other',
        'total_coastal',
        // todo - remove these 
        'total_cigaretteButts',
        'total_plasticBottles',
        'total_drugs',
        'total_needles',
        'total_pathways',

        'total_adidas',
        'total_amazon',
        'total_apple',
        'total_budweiser',
        'total_coke',
        'total_colgate',
        'total_corona',
        'total_fritolay',
        'total_gillette',
        'total_heineken',
        'total_kellogs',
        'total_lego',
        'total_loreal',
        'total_nescafe',
        'total_nestle',
        'total_marlboro',
        'total_mcdonalds',
        'total_nike',
        'total_pepsi',
        'total_redbull',
        'total_samsung',
        'total_subway',
        'total_starbucks',
        'total_tayto',
        'total_applegreen',
        'total_avoca',
        'total_bewleys',
        'total_brambles',
        'total_butlers',
        'total_cafe_nero',
        'total_centra',
        'total_costa',
        'total_esquires',
        'total_frank_and_honest',
        'total_insomnia',
        'total_obriens',
        'total_lolly_and_cookes',
        'total_supermacs',
        'total_wilde_and_greene'
    ];


		
	public function creator()
	{
		return $this->belongsTo('App\User', 'created_by');
	}

    public function country() {
    	return $this->belongsTo('App\Country');
    }

    public function state() {
    	return $this->belongsTo('App\State');
    }

    public function photos() {
    	return $this->hasMany('App\Photo');
    }

    public function users() {
    	return $this->hasMany('App\User');
    }

}
