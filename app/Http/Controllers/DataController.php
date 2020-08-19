<?php

namespace App\Http\Controllers;

use Response;
use App\Photo;
use App\Http\Requests;
use Illuminate\Http\Request;
use Acme\Transformers\PhotoTransformer;

class DataController extends ApiController
{

    // Acme\Transformers\photoTransformer
    protected $photoTransformer;

    function __construct(PhotoTransformer $photoTransformer) {
        $this->photoTransformer = $photoTransformer;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // return Photo::all();
        // $photos = Photo::all();

        // set limit or default to 3
        $limit = request()->limit ?: 3;
        $photos = Photo::paginate($limit); 
        // dd(get_class_methods($photos)); // array = 42
        // $transformedCollection = $this->photoTransformer->transformCollection($photos->getCollection());
        // $photos->setCollection($transformedCollection);

        return $this->respondWithPagination($photos, [
            'data' => $this->photoTransformer->transformCollection($photos->all()),
            ]);
    }



    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store() //Request $request
    {
        // validation
        if(!Input::get('title') or ! Input::get('body')) {
            // return some kind of response ..
            // what status code ??
            // 400: bad request
            // 403: forbidden
            // 420: Not yet permissable
            // 422: unprocessible entity
            return $this->setStatusCode(422)
                        ->respondWithError('Parameters failed validation for post.');
        }
        Photo::create(Input::all());

        return $this->respondCreated('Data successfully created.');
    }



    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $photo = Photo::find($id);
        if(!$photo) {
            return $this->respondNotFound();
        }
        return $this->respond([
            'data' => $this->photoTransformer->transform($photo)
        ]);
    }

    // because we are using resourceful routing, this with auto trigger when someone posts to data collection
    // public function store() {
    //     dd('store');
    // }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
