<?php

namespace App\Http\Controllers;

use App\User;
use App\Photo;
use App\Suburb;
use App\City;
use App\State;
use App\Country;

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
