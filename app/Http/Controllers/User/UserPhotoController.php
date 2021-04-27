<?php

namespace App\Http\Controllers\User;

use App\Models\Photo;
use App\Traits\Photos\FilterPhotos;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UserPhotoController extends Controller
{
    protected $paginate = 5;

    use FilterPhotos;

    /**
     * Return filtered array of the users photos
     *
     * @return array
     */
    public function filter ()
    {
        $query = $this->filterPhotos(request()->filters);

        $count = $query->count();
        $paginate = $query->simplePaginate($this->paginate);

        return [
            'count' => $count,
            'paginate' => $paginate
        ];
    }

    /**
     * Return non-filtered array of the users photos
     */
    public function index ()
    {
        return Photo::select('id', 'filename', 'total_litter', 'verified', 'datetime', 'created_at')
            ->where('user_id', auth()->user()->id)
            ->simplePaginate($this->paginate);
    }
}
