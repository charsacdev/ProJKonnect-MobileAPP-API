<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Validator;
use Illuminate\Support\Facades\Auth;

class Pushnotification extends Controller
{
    public function update_token(Request $request){
        try{
            $validator = Validator::make($request->all(), [
                'fcm_token' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 401);
            }

            #update fcm_token
            $saveToken=User::where('id',auth()->user()->id)->update(['fcm_token'=>$request->fcm_token]);
            if($saveToken){
                return response()->json([
                'statusCode'=>200,
                'message'=>"token updated"
              ],200); 
            }
           
        }
        catch(\Exception $e){
            report($e);
            return response()->json([
                'statusCode'=>500,
                'message'=>"token not updated"
            ],500);
        }
    }
}
