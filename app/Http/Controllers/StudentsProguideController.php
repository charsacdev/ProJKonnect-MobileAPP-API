<?php

namespace App\Http\Controllers;

use App\Custom\MailMessages;
use App\Models\StudentsProguide;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Plan;
use App\Models\PlanOption;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Validator;

class StudentsProguideController extends Controller
{
    #===================CONNECT TO PROGUIDE==================#
    public function create_students_proguide(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "proguide_id" => "required",
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 401);
            }

            #======check if student has connected with proguide=====#
            $student_connect = StudentsProguide::where('user_id', auth()->user()->id)->where('proguide_id', $request->proguide_id)->where('status','connected')->get();

            if ($student_connect->count()===1) {
                return \response(["code" => 3, "message" => "You have already connected to this proguide"]);
            }

            #====check if student has exceeded the max number of proguides====#
            $student_max = User::where('id', auth()->user()->id)->first();

            if ($student_max->max_proguides < 1) {
                return response(["code" => 3, "message" => "Max number of proguides exceeded. Kindly upgrade plan "]);
            }
            
            #======getting the expiry from the payment table based on active plans====#
            $getExpiryDate=Payment::where(['payer_id'=>auth()->user()->id,'status'=>'active'])->first();

            $studentProguide = StudentsProguide::create([
                "user_id" => auth()->user()->id,
                "proguide_id" => $request->proguide_id,
                "status"=> "connected",
                'expiry_date'=>Carbon::now()->addDays($getExpiryDate->duration)->format('Y-m-d g:a'),
            ]);
            
            #====check if proguide id exists in wallet table====#
            $ProguideEarnings=Wallet::where('user_id', $request->proguide_id)->first();

            #====getting the plan percentage===#
            $PlanDetails=PlanOption::where('id',$getExpiryDate->service_id)->first();
            
            if($ProguideEarnings==null){
                #===insert proguide earnings into wallet===#
                $wallet=Wallet::insert([
                       "user_id"=>$request->proguide_id,
                       "available_balance"=>($PlanDetails->earning_percent/100)*$PlanDetails->amount,
                       "pending_balance"=>'0',
                       'status'=>'active'
                    ]);
            }
            
            #===update proguide if wallet balance===#
            if(!$ProguideEarnings==null){
                
                #=====uodate proguide earnings into wallet===#
                $wallet=Wallet::where(['user_id'=>$request->proguide_id])->update([
                       'available_balance'=>($ProguideEarnings->available_balance)+(($PlanDetails->earning_percent/100)*$PlanDetails->amount),
                ]);
            }

            #==subtract from the student proguide===#
            $student = User::where('id', auth()->user()->id)->first();
            $student->max_proguides--;
            $student->save();

            $this->send_notification_mail($request->proguide_id, auth()->user()->full_name);

            return response(["code" => 1, "message" => "created successfully"]);
        } 
        catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }
    
    
    
    
    //function to get all the proguide a student is connected to
    public function get_all_students_proguides()
    {
        try {
            $studentProguide = StudentsProguide::with('proguide')->where('status','connected')->where('user_id', auth()->user()->id)->get();

            if ($studentProguide->count() < 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }

            return response(["code" => 1, "data" => $studentProguide]);
            
            
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }
    
    
    //function to know when a student is connected to a proguide
    public function Know_active_connected_proguide($id){
        try {
            
             //get all proguides list
              $phone = auth()->user();
              $connected=$phone->students_proguides()->where(['proguide_id'=>$id,'status'=>'connected'])->first();
             #$connectedProguides=User::with('users')->where(['user_type'=>'proguide','id'=>$id])->first();
             if($connected){
                 return response(["code" => 1, "data" =>$connected->status]);
             }
             else{
                 return response(["code" => 2, "message" =>"not connected"]);
             }
             
            
                 
             //}
           
        
             
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    public function get_all_proguides_students()
    {
        try {
            $studentProguide = StudentsProguide::with('student')->where('status','connected')->where('proguide_id', auth()->user()->id)->get();

            if ($studentProguide->count() < 1) {
                return response(["code" => 3, "message" => "No record found"]);
            }

            return response(["code" => 1, "data" => $studentProguide]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }
    
    //function to know when a student is connected to a proguide
    public function Know_active_connected_student(){
        try {
             #get all proguides list
             $connectedStudent =  User::with('students_proguides')->where('user_type','student')->get();
        
            return response(["code" => 1, "data" => $connectedStudent]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    public function delete_student_proguide($id)
    {
        try {
            $student = StudentsProguide::find($id)->delete();
            return response(["code" => 1, "message" => "deleted successfully"]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }


    #========DISCONNECT STUDENT FROM PROGUIDE========#
    public function disconnect_student_from_proguide($id){
        try {
            $student = StudentsProguide::where(["id"=>$id,"user_id"=>auth()->user()->id])->update([
                 "status"=>"disconnected" ?? $student->status
            ]);
            if($student){
                return response(["code"=>1,"message"=>"successfully disconnected from proguide"]);
            }
            else{
                return response(["code"=>1,"message"=>"your are not connected to this proguide"]);
            }

           
        } catch (\Throwable $th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }
    
    
    
    #function to auto disconnect a proguide
    public function auto_disconnect_student_from_proguide(){
        try {
            
            $date=Carbon::now()->format('Y-m-d g:a');
            $disconnect = StudentsProguide::where(['expiry_date'=>$date])->get();
            foreach($disconnect as $disconnection){
                
                StudentsProguide::where(['expiry_date'=>$disconnection->expiry_date])->update([
                     'status'=>'disconnected'
                ]);
            }
    
            return response(["code"=>1,"message"=>"over them all if they wan turn goliath"]);
            
        } catch (\Throwable $th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }
    
    
    // #get all proguide connected to a student
    // public function get_proguide_connected_student(){
    //     try{
    //       $getallconnected_proguides=StudentsProguide::where(['user_id'=>auth()->user()->id,'status'=>'connected'])->get(); 
    //       return response(["code" => 1, "data" => $getallconnected_proguides]);
    //     }
    //      catch (\Throwable$th) {
    //         return response(["code" => 3, "error" => $th->getMessage()]);
    //     }
        
    // }

    private function send_notification_mail($proguide_id, $student)
    {
        $proguide_email = User::where('id', $proguide_id)->first();

        MailMessages::SendNotificationMailToProguide($student, $proguide_email->email);

    }
}
