<?php

namespace App\Http\Controllers\User;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use WisdomDiala\Countrypkg\Models\State;
use WisdomDiala\Countrypkg\Models\Country;
use Illuminate\Support\Facades\Storage;

class fetchCountriesController extends Controller
{
    public function getAllCountries()
    {
        $countries = Country::all();
        return response($countries);
    }

    public function getStateswithCountry($id)
    {
        $states = State::where('country_id', $id)->get();
        return response($states);
    }


    public function uploadfile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            Storage::disk('profile_photo')->put($fileName, file_get_contents($file));

            // Optionally, you can generate a public URL for the uploaded file.
            $fileUrl = Storage::disk('profile_photo')->url($fileName);

            // You can store the URL or perform additional actions as needed.
            
            return redirect()->back()->with('success', 'File uploaded successfully.');
        } else {
            return redirect()->back()->with('error', 'File upload failed.');
        }
    }

}
