<?php

namespace App\Traits;

use App\Models\Image;
use App\Models\Patient;
use App\Models\PatientAppointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait UploadTrait
{

    public function uploadImage(
        Request $request,
        $disk,
        $inputName,
        $folderName,
        $imageable_id,
        $imageable_type
    ) {
        if ($request->hasFile($inputName)) {

            $photo = $request->file($inputName);
            $name = Str::slug($request->input('name'));
            $filename = $name . '.' . $photo->getClientOriginalExtension();
            $path = $request->file($inputName)->storeAs($folderName, $filename, $disk);
            Image::create([
                'fileName' => "images/$path",
                'imageable_id' => $imageable_id,
                'imageable_type' => $imageable_type,
            ]);
        }
    }//end of uploadImage

    public function uploadImageRay($varForeach, $disk, $folderName, $imageable_id, $imageable_type)
    {

        $fileName = $varForeach->getClientOriginalName();
        $path = $varForeach->storeAs($folderName, $fileName, $disk);
        Image::create([
            'fileName' => $path,
            'imageable_id' => $imageable_id,
            'imageable_type' => $imageable_type,
        ]);
    }
    public function deleteImage($disk, $id, $type)
    {
        $images = Image::where('imageable_id', $id)->where('imageable_type', $type)->get();
        foreach ($images as $image) {
            Storage::disk($disk)->delete($image->fileName);
            $image->delete();
        }
    }

}
