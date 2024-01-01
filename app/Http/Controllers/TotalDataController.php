<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Photo;
use App\Models\User\User;
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
use DateTime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Excel;

class TotalDataController extends Controller
{
    /**
     * Export some data for machine learning
     */
    public function getCSV ()
    {
        if (($user = Auth::user()) && $user->email == 'seanlynch@umail.ucc.ie')
        {
            Excel::create('cigarette_butts', function ($excel)
            {
                $excel->sheet('OLM', function ($sheet)
                {
                    $index = 0;
                    $export = [];

                    // Get the ID's where cigarette butts exist
                    // The photos before 978 were accidentally deleted
                    $butts = Smoking::select('id')
                        ->where([
                            ['id', '>', 984],
                            ['butts', '>', 0]
                        ])
                        ->get()
                        ->take(2500)
                        ->toArray();

                    // Get photos that match the smoking_id column
                    // eager load the cigarette_butt data again
                    $cig_photos = Photo::select('id', 'smoking_id', 'filename')
                    ->whereIn('smoking_id', $butts)
                    ->with([
                        'smoking' => function ($a) {
                            $a->select('id', 'butts');
                        }
                    ])
                    ->get();

                    // Add to the CSV
                    foreach ($cig_photos as $photo)
                    {
                        $index++;
                        $export[$index]['filename'] = $photo->filename;
                        $export[$index]['photo_id'] = $photo->id;
                        $export[$index]['smoking_id'] = $photo->smoking->id;
                        $export[$index]['softdrinks_id'] = 0;
                        $export[$index]['butts'] = $photo->smoking->butts;
                        $export[$index]['boxes'] = 0;
                        $export[$index]['bottles'] = 0;
                        $export[$index]['cans'] = 0;
                    }

                    // Do the same for cigarette boxes
                    $cig_boxes = Smoking::select('id')
                        ->where([
                            ['id', '>', 984],
                            ['cigaretteBox', '>', 0]
                        ])
                        ->get()
                        ->take(2500)
                        ->toArray();

                    $cig_boxes_photos = Photo::select('id', 'smoking_id', 'filename')
                        ->whereIn('smoking_id', $cig_boxes)
                        ->with([
                            'smoking' => function ($a) {
                                $a->select('id', 'cigaretteBox');
                            }
                        ])
                        ->get();

                    // Add to the CSV
                    foreach ($cig_boxes_photos as $photo)
                    {
                        $index++;
                        $export[$index]['filename'] = $photo->filename;
                        $export[$index]['photo_id'] = $photo->id;
                        $export[$index]['smoking_id'] = $photo->smoking->id;
                        $export[$index]['softdrinks_id'] = 0;
                        $export[$index]['butts'] = 0;
                        $export[$index]['boxes'] = $photo->smoking->cigaretteBox;
                        $export[$index]['bottles'] = 0;
                        $export[$index]['cans'] = 0;
                    }

                    // Get plastic water bottles.id
                    $plastic_bottle_ids = SoftDrinks::select('id')
                        ->where([
                            ['id', '>', 1462],
                            ['waterBottle', '>', 0]
                        ])
                        ->get()
                        ->take(2500)
                        ->toArray();

                    $plastic_bottle_photos = Photo::select('id', 'softdrinks_id', 'filename')
                        ->whereIn('softdrinks_id', $plastic_bottle_ids)
                        ->with([
                            'softdrinks' => function ($a) {
                                $a->select('id', 'waterBottle');
                            }
                        ])
                        ->get();

                    // Add to the CSV
                    foreach ($plastic_bottle_photos as $photo)
                    {
                        $index++;
                        $export[$index]['filename'] = $photo->filename;
                        $export[$index]['photo_id'] = $photo->id;
                        $export[$index]['smoking_id'] = 0;
                        $export[$index]['softdrinks_id'] = $photo->softdrinks->id;
                        $export[$index]['butts'] = 0;
                        $export[$index]['boxes'] = 0;
                        $export[$index]['bottles'] = $photo->softdrinks->waterBottle;
                        $export[$index]['cans'] = 0;
                    }

                    // Get aluminium cans
                    $cans_ids = SoftDrinks::select('id')
                        ->where([
                            ['id', '>', 1462],
                            ['tinCan', '>', 0]
                        ])
                        ->get()
                        ->take(2500)
                        ->toArray();

                    $tin_can_photos = Photo::select('id', 'softdrinks_id', 'filename')
                        ->whereIn('softdrinks_id', $cans_ids)
                        ->with([
                            'softdrinks' => function ($a) {
                                $a->select('id', 'tinCan');
                            }
                        ])
                        ->get();

                    // Add to the CSV
                    foreach ($tin_can_photos as $photo)
                    {
                        $index++;
                        $export[$index]['filename'] = $photo->filename;
                        $export[$index]['photo_id'] = $photo->id;
                        $export[$index]['smoking_id'] = 0;
                        $export[$index]['softdrinks_id'] = $photo->softdrinks->id;
                        $export[$index]['butts'] = 0;
                        $export[$index]['boxes'] = 0;
                        $export[$index]['bottles'] = 0;
                        $export[$index]['cans'] = $photo->softdrinks->tinCan;
                    }

                    $sheet->fromModel($export);

                })->export('csv');
            });
        }
    }


//    /**
//     * Return list of data for sean
//     */
//    public function butts ()
//    {

//            return Photo::with([
//                'smoking' => function ($a) {
//                    $a->select('id', 'butts', 'cigaretteBox');
//                },
//                'softdrinks' => function ($b) {
//                    $b->select('id', 'waterBottle', 'fizzyDrinkBottle', 'tinCan', 'energy_can');
//                }
//            ])
//            ->where([
//                ['filename', '!=', '/assets/verified.jpg'],
//                'verified' => 2,
//                ['smoking_id', '!=', NULL],
//            ])->select('id', 'filename', 'smoking_id')->get()->take(2500);
//        }
//    }

	/**
	 * Return list of data for Laurens
	 */
	public function laurens ()
	{
		$user = Auth::user();

		if ($user->email == 'seanlynch@umail.ucc.ie' || $user->email == 'bakker.laurens@gmail.com') {

			return Photo::with([
				'softdrinks' => function($a) {
					$a->select('id', 'waterBottle', 'fizzyDrinkBottle', 'tinCan');
				},
				'brands' => function ($b) {
					$b->select('id', 'coke', 'pepsi')->where('coke', '!=', null);
				}
			])
			->where([
				'verified' => 2,
				['softdrinks_id', '!=', NULL],
				['brands_id', '!=', NULL]
			])->select('id', 'filename', 'softdrinks_id', 'brands_id')->get()->take(1000);
		}

		return redirect()->to('/');
	}

}
