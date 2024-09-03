<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserQualification;
use Illuminate\Http\Request;
use Validator;

class UserQualificationController extends Controller
{
    
    #=============CREATE USER QUALIFICATION==================#
    public function create_user_qualification(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "qualification_id" => [],
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
                UserQualification::create([
                    "user_id" => auth()->user()->id,
                    "qualification_id" => $data[$i]["qualification"],
                ]);
            }

            return response(["code" => 1, "message" => "created successfully"]);

        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }

    }


    #================GET ALL USER QUALIFICATION==============#
    public function get_all_user_qualifications()
    {
        try {
            $qualifications = UserQualification::with('qualifications')->where('user_id', auth()->user()->id)->latest()->get();

            if ($qualifications->count() == 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }

            return response(["code" => 1, "data" => $qualifications]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }


    #============DELETE USER QUALIFICATION====================#
    public function delete_user_qualification($id)
    {
        try {
            $deleteInterests = UserQualification::find($id)->delete();

            return response(["code" => 1, "message" => "interest deleted "]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }

    }

    #===================EDIT USER QUALIFICATION=======================#
    public function edit_user_qualification(Request $request)
    {
        try {
            #delete all interests where the id matches the user id

            $qualifications = auth()->user()->userqualification()->latest()->get();
            if($qualifications->count()>0){
                    foreach ($qualifications as $qualification) {
                        $qualification->delete();
                    }

                    $len = count($request->data);

                    $data = $request->data;

                    $i = 0;

                    for ($i; $i < $len; $i++) {
                        UserQualification::create([
                            "user_id" => auth()->user()->id,
                            "qualification_id" => $data[$i]["qualification"],
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
