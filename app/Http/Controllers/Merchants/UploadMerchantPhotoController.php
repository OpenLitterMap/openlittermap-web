<?php

namespace App\Http\Controllers\Merchants;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class UploadMerchantPhotoController extends Controller
{
    public function __invoke (Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:jpg,png,jpeg,heif,heic',
            'merchantId' => 'required'
        ]);

        $merchant = Merchant::find($request->merchantId);

        if (!$merchant)
        {
            return [
                'success' => false,
                'msg' => 'merchant not found'
            ];
        }

        try
        {
            $uploadedFile = $request->file('file');
            $filename = time() . '.' . $uploadedFile->getClientOriginalExtension();

            if (app()->environment('local')) {
                // In local environment, save to the local disk
                Storage::disk('local')->put($filename, file_get_contents($uploadedFile));

                $filepath = 'todo';
            }
            else
            {
                // In production environment, save to AWS S3
                $path = now()->year . '/' . now()->month . '/' . now()->day . '/' . $filename;

                $filesystem = Storage::disk('s3');

                $filesystem->put($path, file_get_contents($uploadedFile), 'public');

                $filepath = $filesystem->url($path);
            }

            // Create a photo record
            $merchantPhoto = MerchantPhoto::create([
                'uploaded_by' => Auth::user()->id,
                'filepath' => $filepath,
                'merchant_id' => $merchant->id
            ]);
        }
        catch (\Exception $exception)
        {
            \Log::info(['UploadMerchantPhotoController', $exception->getMessage()]);

            return [
                'success' => false,
                'msg' => 'problem'
            ];
        }

        return response()->json([
            'success' => true,
            'filename' => $filename,
            'photo' => $merchantPhoto
        ]);
    }
}
