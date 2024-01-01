<?php

namespace App\Http\Controllers\User;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Actions\Photos\GetPreviousCustomTagsAction;
use App\Jobs\Photos\AddTagsToPhoto;
use App\Models\Photo;
use App\Traits\Photos\FilterPhotos;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UserPhotoController extends Controller
{
    protected $paginate = 300;

    use FilterPhotos;

    /**
     * Add Many Tags to Many Photos
     *
     * @return array
     */
    public function bulkTag (Request $request)
    {
        foreach ($request->photos as $photoId => $data) {
             dispatch (new AddTagsToPhoto(
                 $photoId,
                 $data['picked_up'] ?? false,
                 $data['tags'] ?? [],
                 $data['custom_tags'] ?? []
             ));
        }

        return ['success' => true];
    }

    /**
     * Todo - test this on production
     *
     * @return array
     */
    public function destroy (Request $request)
    {
        $user = Auth::user();
        $s3 = Storage::disk('s3');

        $ids = ($request->selectAll) ? $request->exclIds : $request->inclIds;

        $photos = $this->filterPhotos(json_encode($request->filters), $request->selectAll, $ids)->get();

        foreach ($photos as $photo)
        {
            try
            {
                if ($user->id === $photo->user_id)
                {
                    if (app()->environment('production'))
                    {
                        $path = substr((string) $photo->filename, 42);
                        $s3->delete($path);
                    }

                    $photo->delete();
                }
            } catch (Exception $e) {
                // could not be deleted
                Log::info(["Photo could not be deleted", $e->getMessage()]);
            }
        }

        return ['success' => true];
    }

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
     *
     * @return array
     */
    public function index ()
    {
        $query = Photo::select('id', 'filename', 'total_litter', 'verified', 'datetime', 'created_at')
            ->where([
                'user_id' => auth()->user()->id,
                'verified' => 0,
                'verification' => 0
            ]);

        return [
            'paginate' => $query->simplePaginate($this->paginate),
            'count' => $query->count()
        ];
    }

    /**
     * List of the user's previously added custom tags
     */
    public function previousCustomTags (GetPreviousCustomTagsAction $previousTagsAction)
    {
        return $previousTagsAction->run(request()->user());
    }
}
