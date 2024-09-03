<?php

namespace App\Http\Controllers;

use App\Models\VideoTutorialUpload;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Facades\Storage;
use Str;

class VideoTutorialUploadController extends Controller
{

    #====================Create Video Tutorials=======================#
    public function create_tutorial(Request $request)
    {

        try {
            $validator = Validator::make($request->all(), [
                "course_title" => "required",
                "Course_category"=>"required",

            ]);
            if ($validator->fails()) {
                return response()->json(["code" => 3, 'error' => $validator->errors()], 401);
            }

            if ($request->hasFile('video_file')) {

                $validator = Validator::make($request->all(), [
                    "video_file" => "mimes:mp4,mkv,flv,avi|max:1000000|required",
                ]);

                if ($validator->fails()) {
                    return response()->json(["code" => 3, 'error' => $validator->errors()], 401);
                }

                #$files = $request->video_file->store('video_tutorials', 'public');

                $ext = $request->file('video_file')->extension();
                $size = $request->file('video_file')->getSize();
                
                #upload file AWS
                $file = $request->file('video_file');
                $fileName = Str::uuid().".".$request->file('video_file')->extension();
                Storage::disk('tutorials')->put($fileName, file_get_contents($file));

                #$fileUrl = Storage::disk('profile_photo')->url($fileName);
                $fileUrl = "https://myprojkonnect-s3bucket.s3.amazonaws.com/video_tutorials/".$fileName;
                $fileUrl2 = "video_tutorials/".$fileName;

            }

            #save video to database
            $video_tutorial = VideoTutorialUpload::create([
                "course_title" => $request->course_title,
                'course_category'=>$request->Course_category,
                "file_uploaded" => $fileUrl ?? null,
                "proguide_id" => auth()->user()->id,
            ]);

            return response(["code" => 1, "message"=>"tutorial created successfully"],200);

        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }


    #=================GET ALL TUTORIALS=================#
    public function get_all_tutorials()
    {
        try {
            $video_tutorial = VideoTutorialUpload::with('proguide')->latest()->get();

            if ($video_tutorial->count() == 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }
            return response(["code" => 1, "data" => $video_tutorial]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }


    #========GET TUTORIAL BASED ON PROGUIDE ID=========#
    public function get_all_tutorials_for_a_particular_proguide($id)
    {
        try {
            $video_tutorial = VideoTutorialUpload::with('proguide')->where('proguide_id', $id)->latest()->get();

            if ($video_tutorial->count() == 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }
            return response(["code" => 1, "data" => $video_tutorial]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }


    #===========EDIT TUTORIAL==========#
    public function editing_tutorial(Request $request,$id)
    {
        try {
             
                $validator = Validator::make($request->all(), [
                    "course_title" => "required",
                    "Course_category"=>"required",
                ]);

                if ($validator->fails()) {
                    return response()->json(["code" => 3, 'error' => $validator->errors()], 401);
                }
    
                if ($request->hasFile('video_file')) {
    
                    $validator = Validator::make($request->all(), [
                        "video_file" => "mimes:mp4,mkv,flv,avi|max:1000000|required",
                    ]);

                    if ($validator->fails()) {
                        return response()->json(["code" => 3, 'error' => $validator->errors()], 401);
                    }
    
                    #$files = $request->video_file->store('video_tutorials', 'public');
    
                    $ext = $request->file('video_file')->extension();
                    $size = $request->file('video_file')->getSize();
                    
                    #upload file AWS
                    $file = $request->file('video_file');
                    $fileName = Str::uuid().".".$request->file('video_file')->extension();
                    Storage::disk('tutorials')->put($fileName, file_get_contents($file));
    
                    #$fileUrl = Storage::disk('profile_photo')->url($fileName);
                    $fileUrl = "https://myprojkonnect-s3bucket.s3.amazonaws.com/video_tutorials/".$fileName;
                    $fileUrl2 = "video_tutorials/".$fileName;
    
                }

                #save video to database
                $video=VideoTutorialUpload::find($id);
                $video_tutorial = VideoTutorialUpload::where('id',$id)->update([
                    "course_title" => $request->course_title,
                    'course_category'=>$request->Course_category,
                    "file_uploaded" => $fileUrl ?? $video->file_uploaded,
                    "proguide_id" => auth()->user()->id,
                ]);
    
                return response(["code" => 1, "message"=>"tutorial updated successfully"]);
    
            } 
            catch (\Throwable$th) {
                return response(["code" => 3, "error" => $th->getMessage()]);
            }
    }


    #============DETELE VIDEO============#
    public function delete_tutorial($id)
    {
        try {
            $video_tutorial = VideoTutorialUpload::find($id)->delete();
            return response(["code" => 1, "message" => "delete successful"]);

        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }
    
    
     #=================GET ALL TUTORIALS=================#
     public function get_users_caterory()
     {
         try {
             $video_category = VideoTutorialUpload::select('course_category')->where('proguide_id',auth()->user()->id)->get();
 
             if ($video_category->count() == 0) {
                 return response(["code" => 3, "message" => "No record found"]);
             }
             return response(["code" => 1, "data" => $video_category]);
         } catch (\Throwable$th) {
             return response(["code" => 3, "error" => $th->getMessage()]);
         }
     }


    #=============GET SINGLE TUTORIAL=======#
    public function get_single_tutorial($id){
        try {
            $video_tutorial = VideoTutorialUpload::with('proguide')->where('id', $id)->latest()->get();

            if ($video_tutorial->count() == 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }
            return response(["code" => 1, "data" => $video_tutorial]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }

    }


    #===========GET PROGUIDE ID===#
    public function get_proguide_tutorial()
    {
        try {
            $video_tutorial = VideoTutorialUpload::with('proguide')->where('proguide_id', auth()->user()->id)->latest()->get();

            if ($video_tutorial->count() == 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }
            return response(["code" => 1, "data" => $video_tutorial]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }
}
