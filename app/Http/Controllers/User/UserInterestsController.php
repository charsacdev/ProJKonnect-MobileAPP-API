<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserInterests;
use Illuminate\Http\Request;
use Validator;

class UserInterestsController extends Controller
{

    #================CREATE USER INTERES======================#
    public function create_user_interests(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "interest_id" => [],
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 401);
            }

            if (!$request->data) {
                return response(["code" => 3, "message" => "No request was sent"]);
            }

            $len = count($request->data);

            $data = $request->data;

            $i = 0;

            for ($i; $i < $len; $i++) {
                UserInterests::create([
                    "user_id" => auth()->user()->id,
                    "interest_id" => $data[$i]["interests"],
                ]);
            }

            return response(["code" => 1, "message" => "created successfully"]);

        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }


    #===================GET ALL USER INTEREST====================#
    public function get_all_user_interests()
    {
        try {
            $interests = UserInterests::with('interests')->where('user_id', auth()->user()->id)->get();

            if ($interests->count() == 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }

            return response(["code" => 1, "data" => $interests]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    public function delete_interests($id)
    {
        try {
            $deleteInterests = UserInterests::find($id)->delete();

            return response(["code" => 1, "message" => "interest deleted "]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }

    }



    #===================EDIT USER INTEREST=====================#
    public function edit_user_interests(Request $request)
    {
        try {

           
            #delete all interests where the id matches the user id
            $interests = auth()->user()->userinterests()->latest()->get();
            if($interests->count()>0){
                foreach ($interests as $interest) {
                    $interest->delete();
                }
    
                $len = count($request->data);
                $data = $request->data;
    
                $i = 0;
    
                for ($i; $i < $len; $i++) {
                    UserInterests::create([
                        "user_id" => auth()->user()->id,
                        "interest_id" => $data[$i]["interests"],
                    ]);
                }
         
                return response(["code" => 1, "message" => "updated successfully"]);
            }
            else{
                return response(["code" => 1, "message" => "no result not found"]);
            }
            
           

        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

}
