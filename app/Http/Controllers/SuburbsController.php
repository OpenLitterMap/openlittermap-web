<?php

namespace App\Http\Controllers;

use App\Models\User\User;
use App\Models\Photo;
use App\Suburb;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\Location\Country;

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

use Illuminate\Http\Request;

class SuburbsController extends Controller
{
    public function getSuburb() {

		$countries = Country::where('id', '!=', '9999')->get();
		$states = State::all();
		$cities = City::all();
		$suburbs = Suburb::all();

    	return view('reports.suburbs', compact('countries', 'states', 'cities', 'suburbs'));
    }
}
