<?php

namespace App\Http\Controllers\Auth;

use App\Custom\MailMessages;
use App\Http\Controllers\Controller;
use App\Models\OTPToken;
use App\Models\Payment;
use App\Models\ProguideRating;
use App\Models\Rating;
use App\Models\Referal_transaction;
use App\Models\Referal;
use App\Models\Socials;
use App\Models\StudentsProguide;
use App\Models\User;
use App\Models\UserInterests;
use App\Models\UserQualification;
use App\Models\Qualifications;
use App\Models\UserSpecialization;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Image;
use Str;
use Validator;
use Illuminate\Support\Facades\Storage;
use WisdomDiala\Countrypkg\Models\Country;
use Spatie\Newsletter\Facades\Newsletter as Newsletter;

class UserAuthController extends Controller
{
    #==============find user by id====================#
    public function get_user_details($id){
        
        $findUser=User::where(['id'=>$id])->get();
        if($findUser->count() > 0){
             return response(['code' => 1,'message' => 'user found',"user"=>$findUser]);
        }
        else{
            return response(['code' => 1,'message' => 'user not found']);
        }
    }
    
    #=============NEW USER==================#

    public function create_user(Request $request)
    {
        try {
             $validator = Validator::make($request->all(), [
                'full_name' => 'required|max:255',
                'username' => 'nullable',
                'email' => 'required|email|unique:users',
                'country_id' => 'nullable',
                'qualification' => [],
                'interest' => [],
                'specialization' => [],
                'university' => 'required',
                'user_type' => 'required',
                'password' => 'required',
                'referal_code' => 'nullable',
            ]);

            if ($validator->fails()) {
                return response()->json(['code' => 2,'error' => $validator->errors()], 401);
            }

            #handles the profile image
            if ($request->hasFile('profile_image')) {
                $validate = Validator::make($request->all(), ['profile_image' => 'image|mimes:jpeg,png,jpg,gif,svg']);
                if ($validate->fails()) {
                    return response()->json(["code" => 3, 'error' => $validate->errors()->first()]);
                }

                #upload file AWS
                $file = $request->file('profile_image');
                $fileName = time() . '_' . $file->getClientOriginalName();
                Storage::disk('profile_photo')->put($fileName, file_get_contents($file));

                #$fileUrl = Storage::disk('profile_photo')->url($fileName);
                $fileUrl = "https://myprojkonnect-s3bucket.s3.amazonaws.com/profile_images/".$fileName;
                $fileUrl2 = "profile_images/".$fileName;

            }

            $userCreate = User::create([
                'full_name' => $request->full_name,
                'email' => $request->email,
                'country_id' => $request->country,
                'email_verified_at'=>Carbon::now(),
                'user_type' => strtolower($request->user_type),
                'password' => Hash::make($request->password) ?? null,
                'profile_image' => $fileUrl ?? null,
                'referal_code' => "prf" . Str::random(6),
                'referal_earnings'=>'0',
                'university' => $request->university,
                'fcm_token'=>''

            ]);

            #create qualification

            if ($request->qualification != null) {
                $qualification = json_decode($request->qualification);

                foreach ($qualification as $key => $value) {
                    UserQualification::create([
                        "user_id" => $userCreate->id,
                        "qualification_id" => $value,
                    ]);
                }

            }

            #create interest
            if ($request->interest != null) {

                $interest = json_decode($request->interest);

                foreach ($interest as $key => $value) {
                    UserInterests::create([
                        "user_id" => $userCreate->id,
                        "interest_id" => $value,
                    ]);
                }

            }

            #create specialization

            if ($request->specialization != null) {
                $specilization = json_decode($request->specialization);

                foreach ($specilization as $key => $value) {
                    UserSpecialization::create([
                        "user_id" => $userCreate->id,
                        "specialization_id" => $value,
                    ]);
                }

            }

            #referal code if any

            if (!empty($request->referal_code)) {
                $user = User::where('referal_code', $request->referal_code)->first();

                if ($user == null) {
                    goto create_token;
                }

                Referal_transaction::create([
                    "referred_by" => $user->id,//owner of referal code
                    "user_referred" => $userCreate->id,//the person who was refered
                    "payment_id"=>'0',
                    "amount_earned"=>"0",
                    "status"=>"active"
                ]);

            }
            #create otp and send mail

            create_token:
            $otp_token = $this->generateRandom(4);

            OTPToken::create([
                "token" => $otp_token,
                "user_id" => $userCreate->id,
            ]);

            MailMessages::UserVerificationMail($otp_token, $request->email);
            #mailchimp
            
            Newsletter::removeTags(['Basic','Platinum','Premium','On-Demand','Freemium'],$request->email);
            Newsletter::subscribeOrUpdate(
              $request->email,
              ['FNAME'=>'','LNAME'=>''], 
              'subscribers',
              ['tags' => ['Freemium']]
            );

            return response(['code' => 1, "user_id" => $userCreate->id, 'message' => 'Account  successfully created']);

        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }


    #==============CREATE USER PASSWORD=======================#

    public function create_user_password(Request $request)
    {

        try {
            $validator = Validator::make($request->all(), [

                'password' => 'required_with:password_confirmation|same:password_confirmation|min:6',
                'password_confirmation' => 'min:6',
                'user_id' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(["code" => 3, 'error' => $validator->errors()], 401);
            }
            $user = User::find($request->user_id);

            $user->password = Hash::make($request->password);
            $user->save();

            return response(["message" => "Password has been updated", "code" => 1]);

        } catch (\Throwable$th) {
            return response(['code' => 3, "message" => $th->getMessage()]);
        }

    }


    #================VERIFY USER EMAIL==============#
    public function verify_user(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'verify_token' => ['required', 'string', 'max:200'],

            ]);

            if ($validator->fails()) {
                return response()->json(["code" => 3, 'error' => $validator->errors()], 401);
            }

            $token = OTPToken::where('token', $request->verify_token)->first();

            if ($token != null) {
                $user = User::find($token->user_id);
                $user->email_verified_at = Carbon::now();
                if ($user->save()) {
                    $token->delete();
                    return response()->json([
                        'code' => 1,
                        'user_id' => $token->user_id,
                        'message' => "email verified",
                    ]);
                }
            } else {
                return response()->json([
                    'code' => 3,
                    'message' => 'token not found',
                ]);
            }

        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    #===============LOGIN USER=======================#
    public function login_user(Request $request)
    {

        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required',
                'password' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(["code" => 3, 'error' => $validator->errors()], 401);
            }

            if (!auth()->attempt($request->only(['email', 'password']))) {
                return response()->json(["code" => 3, "error" => "Invalid email or passsword"], 401);
            }

            #for blocked users

            $blockedUsers = User::where('email', $request['email'])->where('status', 'blocked')->first();

            if ($blockedUsers) {
                return response(["code" => 3, "message" => "Your account has been temporarily blocked by the admin. Contact support for help"]);
            }

            # for active users
            $user = User::where('email', $request['email'])->where('status', 'active')->first();

            //   return   $user->created_at->isPast();

            if ($user) {
                if ($user) {
                    $status = 200;

                    #check if payment has exceeded the time duration

                    $payment = Payment::where('payer_id', auth()->user()->id)->latest()->first();
                    $checkExpiry = true;

                    if ($payment) {
                        $checkExpiry = Carbon::now()->addDays($payment->duration)->format('Y-m-d g:a');
                    }

                    #get users country
                    $country = Country::where('id', auth()->user()->country_id)->first();

                    #active plans
                    $getpayment=Payment::with('description')->where(['payer_id'=>auth()->user()->id,'status'=>'active'])->get();
                    //return response(["code" => 1, "data" => $getpayment]);
                    $response = [
                        'type' => 'user',
                        #'user_auth_type' => ($user->password != null) ? 'main' : 'google',
                        'user' => auth()->user(),
                        'country' => $country,
                        'token' => auth()->user()->createToken('auth_token')->plainTextToken,
                        'access' => ($checkExpiry) ? "Access denied " : "Access granted",
                        'active_plans'=>$getpayment

                    ];
                    
                    return response()->json($response, $status);
                } else {
                    return response()->json(["code" => 3, 'message' => "No user with that email"], 401);
                }
            }

        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }

    }


    #================USER CHANGE PASSWORD===============#
    public function user_change_password(Request $request)
    {
        # validate user inputs
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'password' => ['required', 'string', 'min:4'],
        ]);

        # check if validation fails
        if ($validator->fails()) {
            # return validation error
            return response()->json(["code" => 3, 'error' => $validator->errors()], 401);
        }
        # check if the user is authenticated
        if (auth()->user()) {
            try {
                # checking if the password matches with current password in use
                if (password_verify(request('current_password'), auth()->user()->password)) {
                    # update the new password
                    auth()->user()->update(['password' => Hash::make(request('password'))]);
                    # return success message after updating
                    return response()->json([
                        'code' => 1,
                        'data' => [
                            'message' => 'password changed.',
                        ],
                    ]);
                } else {
                    return response()->json([
                        'code' => 3,
                        'message' => 'password mismatch',
                    ]);
                }
            } catch (\Throwable$e) {
                return response()->json([
                    'code' => 3,
                    'error ' => $e->getMessage(),
                ], 500);
            }
        } else {
            return response()->json([
                'code' => 3,
                'message' => 'unauthenticated user',
            ], 401);
        }
    }


    #=================USER FORGOT PASSWORD===============#
    public function user_forget_password(Request $request)
    {
        $token = $this->generateRandom(4);
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['code' => 3, 'error' => $validator->errors()], 401);
        }
        //insert into password reset db
        DB::table('password_resets')->insert([
            'email' => $request->email,
            'token' => $token,
            'created_at' => Carbon::now(),
        ]);

        MailMessages::UserResetPasswordMail($token, $request->email);

        return response()->json(['message' => 'Email has been sent']);
    }


    #==================USER RESET PASSWORD==================#
    public function user_reset_password(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['code' => 3, 'error' => $validator->errors()], 401);
        }

        $updatePassword = DB::table('password_resets')
            ->where([
                'email' => $request->email,
                'token' => $request->token,
            ])
            ->get();

        if ($updatePassword->count() > 0) {
            $user = User::where('email', $request->email)->update(['password' => Hash::make($request->password)]);
            DB::table('password_resets')->where(['email' => $request->email])->delete();

            return response()->json(["code" => 1, 'message' => "Password has been updated"]);
        } else {
            return response(["code" => 3, "error" => "Invalid token"]);
        }

    }
    

    #================edit credentials=======================#
    public function editUserCredentials(Request $request)
    {
        try {
          
                $validator = Validator::make($request->all(), [
                    'full_name'=>'required',
                    'email'=>'required|email',
                    'phone_number'=>'required|numeric',
                    'qualifications'=>[],
                ]);
                
                if ($validator->fails()) {
                    return response()->json(['code' => 3, 'error' => $validator->errors()->first()], 401);
                }
               

            $user = User::find(Auth::user()->id);
            
            #check for email and phone number
            #$checkdetails=User::where("email",$request->email)->Orwhere("phone_number",$request->phone_number)->first();
            
            #if($checkdetails){
                #return response(["code" => 1, "message" => "Email or phone number already exists"]);
            #}

            #proceed with submitting the request
            $user->full_name = $request->full_name ?? $user->full_name;
            $user->email =$request->email ?? $user->email;
            $user->phone_number = $request->phone_number ?? $user->phone_number;
            $user->university = $request->university_id ?? $user->university;
            $user->country_id = $request->country_id ?? $user->country_id;
            $user->save();

            if ($request->qualifications) {
                #count the qualification from qualification table
               return $this->edit_user_qualification(auth()->user()->id,$request->qualifications);
           }

             #response on updated profile
            return response(["code" => 1, "message" => "Credentials updated"]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }

    }


    #========================update profile image====================#
    public function update_profile_image(Request $request){
        try{
                $validator = Validator::make($request->all(), [
                    'profile_image' => 'required|image|mimes:jpeg,webp,png,jpg,gif,svg',
                ]);

                if ($validator->fails()) {
                    return response()->json(['code' => 3, 'error' => $validator->errors()->first()], 401);
                }

                if ($request->hasFile('profile_image')) {
                    #upload file AWS
                    $file = $request->file('profile_image');
                    $fileName = Str::uuid();
                    Storage::disk('profile_photo')->put($fileName, file_get_contents($file));

                    #$fileUrl = Storage::disk('profile_photo')->url($fileName);
                    $fileUrl = "https://myprojkonnect-s3bucket.s3.amazonaws.com/profile_images/".$fileName;
                    $fileUrl2 = "profile_images/".$fileName;

                    $user = User::find(Auth::user()->id);
                    $user->profile_image = $fileUrl;
                    $user->save();

                    return response(["message" => "Profile image updated" , "code" => 1,"image_url"=>$fileUrl]);
                } 
                else {
                    return response(["message" => "Profile image has not been updated", "code" => 3]);
                }
            }
            catch(\Throwable $th){
                return response(["code" => 3, "error" => $th->getMessage()]);
            }

    }


    #==============Create Referal Code=================#
    public function create_referal_code(Request $request)
    {
        $user = auth()->user();

        $user->referal_code = $request->referal_code;

        $user->save();

        return response(["code" => 1, "message" => "created referal code successfull"]);

    }

    #================edit bio===============#
    public function edit_get_bio(Request $request)
    {
        try {
            $validator1 = Validator::make($request->all(), [
                    'edit_bio'=>'required',
                ]);
                
                if ($validator1->fails()) {
                    return response()->json(['code' => 3, 'error' => $validator1->errors()], 401);
                }
                
                #get edit 
                $useredit = User::where('id',auth()->user()->id)->update([
                   'bio'=>$request->edit_bio
                ]);
                
            return response(["code" => 1, "data" =>"bio updated"]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }
    
    #================get bio=====================#
    public function get_bio()
    {
        try {
            return response(["code" => 1, "data" => auth()->user()->bio ?? "Bio empty"]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    private function generateRandom(int $length)
    {
        return substr(str_shuffle(str_repeat($x = '0123456789', ceil($length / strlen($x)))), 1, $length);
    }


    #============edit proguide qualification============#
    private function edit_user_qualification($id,$qualifications_array)
    {
        try {
            
            
            $qualifications = UserQualification::where(['user_id'=>$id])->get();

            #delete all interests where the id matches the user id
            foreach ($qualifications as $qualification) {
                $qualification->delete();
            }

            $len = count($qualifications_array);

            #select all qualifications and decode in json
            $data = json_decode(Qualifications::all());
            

            $i = 0;

            for ($i; $i < $len; $i++) {
                UserQualification::create([
                    "user_id" => auth()->user()->id,
                    "qualification_id" => $data[$i]->id,
                ]);
            }

            return response(["code" => 1, "message" =>"update successfully"]);

        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    #============user details==========#
    // public function user_details()
    // {
    //     try {

    //         $user = DB::table('countries')->join('users', 'country_id', '=', 'users.country_id')->select('countries.name', 'countries.short_name', 'users.*')->where('users.id', auth()->user()->id)->first();
    //         return response(["code" => 1, "data" => $user]);
    //     } catch (\Throwable$th) {
    //         return response(["code" => 3, "error" => $th->getMessage()]);
    //     }
    // }

    #==========adding social account=========#
    public function add_socials(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [

                "social" => "required",
                "link" => "required",
            ]);

            if ($validator->fails()) {
                return response()->json(["code" => 3, 'error' => $validator->errors()], 401);
            }

            $socials = Socials::create([
                "user_id" => auth()->user()->id,
                "social" => $request->social,
                "link" => $request->link,
            ]);

            return response(["code" => 1, "message" => "socials created successfully"]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    #==============select all social account==========#
    public function get_socials()
    {
        try {
            $socials = auth()->user()->socials()->latest()->get();

            if ($socials->count() == 0) {
                return response(["code" => 1, "message" => "No record found"]);
            }

            return response(["code" => 1, "data" => $socials]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    #===============edit social profile===============#
    public function edit_socials(Request $request, $id)
    {
        try {
            $socials = Socials::find($id);

            $socials->link = $request->link ?? $socials->link;
            $socials->social = $request->social ?? $socials->social;

            $socials->save();
            return response(["code" => 1, "message" => "socials updated successfully"]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }


    #=============delete social account==========#
    public function delete_socials($id)
    {
        try {
            $socials = Socials::find($id)->delete();

            return response(["code" => 1, "message" => "Socials deleted successfully"]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }

    }


    #================get all student==================#
    public function get_all_students()
    {
        try {
            $id = auth()->user()->id;
            $students = $user = DB::table('users')
                ->select('users.*', 'countries.name as country_name', 'countries.short_name as country_short_name')
                ->join('countries', 'users.country_id', '=', 'countries.id')
                ->where([
                    ['user_type', '=', 'student'],
                    ['users.id', '!=', $id],
                ])->latest()->get();

            if (count($students) == 0) {
                return response(["code" => 3, "message" => "No students found"]);
            }

            return response(["code" => 1, "data" => $students]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }
    

    #=================get all student pagination===========#
    public function get_all_students_paginate()
    {
        try {
            $id = auth()->user()->id;
            $students = $user = DB::table('users')
                ->select('users.*', 'countries.name as country_name', 'countries.short_name as country_short_name')
                ->join('countries', 'users.country_id', '=', 'countries.id')
                ->where([
                    ['user_type', '=', 'student'],
                    ['users.id', '!=', $id],
                ])->latest()->take(15)->get();

            if (count($students) == 0) {
                return response(["code" => 3, "message" => "No students found"]);
            }

            return response(["code" => 1, "data" => $students]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    #===============get all proguide================#
    public function get_all_proguides()
    {
        try {
            $id = auth()->user()->id;
            
            // get user's proguides
            $sProguides = StudentsProguide::where('user_id', $id)->get();
            $userProguideIds = [];
            foreach ($sProguides as $sp) {
              $userProguideIds[] = $sp->id;
            }

            $proguides = DB::table('users')
                ->select('users.*', 'countries.name as country_name', 'countries.short_name as country_short_name', 'specializations.specialization as specialization_name', 'qualifications.qualification as qualification_name')
                ->join('user_specializations', 'user_specializations.user_id', '=', 'users.id')
                ->join('user_qualifications', 'user_qualifications.user_id', '=', 'users.id')
                ->leftJoin('qualifications', 'qualifications.id', '=', 'user_qualifications.qualification_id')
                ->leftJoin('specializations', 'specializations.id', '=', 'user_specializations.specialization_id')
                ->leftJoin('countries', 'users.country_id', '=', 'countries.id')
                ->where([
                    ['user_type', '=', 'proguide'],
                    ['users.id', '!=', $id],

                ])
                ->groupBy('users.id')
                ->latest()
                ->get()
                ->map(function ($proguide) use ($userProguideIds) {
                  $proguide->specialization_name = [$proguide->specialization_name];
                  $proguide->qualification_name = [$proguide->qualification_name];
                  $proguide->is_linked_to_user = in_array($proguide->id, $userProguideIds);
                  return $proguide;
                });

            if ($proguides->count() == 0) {
                return response(["code" => 3, "message" => "No proguides found"]);
            }
            return response(["code" => 1, "data" => $proguides]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }
    
    
    #==============get all proguides pagination=============#
    public function get_all_proguides_paginate()
    {
        try {
            $id = auth()->user()->id;
            
            // get user's proguides
            $sProguides = StudentsProguide::where('user_id', $id)->get();
            $userProguideIds = [];
            foreach ($sProguides as $sp) {
              $userProguideIds[] = $sp->id;
            }

            $proguides = DB::table('users')
                ->select('users.*', 'countries.name as country_name', 'countries.short_name as country_short_name', 'specializations.specialization as specialization_name', 'qualifications.qualification as qualification_name')
                ->join('user_specializations', 'user_specializations.user_id', '=', 'users.id')
                ->join('user_qualifications', 'user_qualifications.user_id', '=', 'users.id')
                ->leftJoin('qualifications', 'qualifications.id', '=', 'user_qualifications.qualification_id')
                ->leftJoin('specializations', 'specializations.id', '=', 'user_specializations.specialization_id')
                ->leftJoin('countries', 'users.country_id', '=', 'countries.id')
                ->where([
                    ['user_type', '=', 'proguide'],
                    ['users.id', '!=', $id],

                ])
                ->groupBy('users.id')
                ->latest()
                ->take(15)
                ->get()
                ->map(function ($proguide) use ($userProguideIds) {
                  $proguide->specialization_name = [$proguide->specialization_name];
                  $proguide->qualification_name = [$proguide->qualification_name];
                  $proguide->is_linked_to_user = in_array($proguide->id, $userProguideIds);
                  return $proguide;
                });

            if ($proguides->count() == 0) {
                return response(["code" => 3, "message" => "No proguides found"]);
            }
            return response(["code" => 1, "data" => $proguides]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }
    
    
    
     #==================filter proguide my specialization===================#
    public function filter_userspecialization_by_specialization_id($id)
    {
        try {
            $userspecialization = UserSpecialization::with(['user' => function ($query) {
                $query->where('user_type', 'proguide');
            }])->where('specialization_id', $id)->latest()->get();
            if ($userspecialization->count() == 0) {
                return response(["code" => 3, "message" => "No proguides found"]);
            }
            return response(["code" => 1, "data" => $userspecialization->filter(function ($uq) {
                return !is_null($uq->user);
            })->values(),
            ]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    #=================filter proguide by interest===============#
    public function filter_userinterests_by_interests_id($id)
    {
        try {
            $userinterest = UserInterests::with(['user' => function ($query) {
                $query->where('user_type', 'proguide');
            }])->where('interest_id', $id)->latest()->get();
            if ($userinterest->count() == 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }
            return response(["code" => 1, "data" => $userinterest->filter(function ($uq) {
                return !is_null($uq->user);
            })->values(),
            ]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }


    #================filter proguide by qualification======================#
    public function filter_userqualification_by_qualification_id($id)
    {
        try {
            $userqualification = UserQualification::with(['user' => function ($query) {
                $query->where('user_type', 'proguide');
            }])->where('qualification_id', $id)->latest()->get();
            if ($userqualification->count() == 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }
            return response(["code" => 1, "data" => $userqualification->filter(function ($uq) {
                return !is_null($uq->user);
            })->values(),
            ]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    #=================filter student by country=================#
    public function filter_students_by_country($id)
    {
        try {
            $students = User::where('country_id', $id)->where('user_type', 'student')->latest()->get();

            if ($students->count() == 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }
            return response(["code" => 1, "data" => $students]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    #==============filter student by qualification===========================#
    public function filter_student_userqualification_by_qualification_id($id)
    {
        try {
            $userqualification = UserQualification::with(['user' => function ($query) {
                $query->where('user_type', 'student');
            }])->where('qualification_id', $id)->latest()->get();
            if ($userqualification->count() == 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }
            return response(["code" => 1, "data" => $userqualification->filter(function ($uq) {
                return !is_null($uq->user);
            })->values(),
            ]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }
    
    #=======================filter proguide==================#
   public function filter_proguide(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'interest' => [],
                'specialization' => [],
                'qualification' => [],
                'university' => 'required',
                'country' => 'nullable',
        
            ]);

            if ($validator->fails()) {
                return response()->json(['code' => 2,'error' => $validator->errors()], 401);
            }
            
            $interest = $request->interest;
            $specialization = $request->specialization;
            $qualification = $request->qualification;
            $review = $request->rating;
            $university = $request->universtiy;
            $country = $request->country;

            $proguide = User::where('user_type', 'proguide')
                ->when($interest, function ($query) use ($interest) {
                    $query->whereHas('userinterests', function ($subQuery) use ($interest) {
                        $subQuery->whereHas('interests', function ($subSubQuery) use ($interest) {
                            $subSubQuery->where('interests', $interest);
                        });
                    });
                })
                ->when($specialization, function ($query) use ($specialization) {
                    $query->whereHas('userspecialization', function ($subQuery) use ($specialization) {
                        $subQuery->whereHas('specialization', function ($subSubQuery) use ($specialization) {
                            $subSubQuery->where('specialization', $specialization);
                        });
                    });
                })
                ->when($qualification, function ($query) use ($qualification) {
                    $query->whereHas('userqualification', function ($subQuery) use ($qualification) {
                        $subQuery->whereHas('qualifications', function ($subSubQuery) use ($qualification) {
                            $subSubQuery->where('qualification', $qualification);
                        });
                    });
                })
                // ->when($review, function ($query) use ($review) {
                //     $query->whereHas('review', function ($subQuery) use ($review) {
                //         $subQuery->with('user_rated')->where('rating', $review);
                //     });
                // })
                ->when($country, function ($query) use ($country) {
                    $query->where('country_id', $country);
                })

                //->with(['userqualification.qualifications', 'userspecialization.specialization', 'userinterests.interests', 'review'])
                ->get();

            if ($proguide->count() == 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }

            return response(["code" => 1, "data" => $proguide]);
        } catch (\Throwable$th) {
            return response(["code" =>13, "error" => $th->getMessage()]);
        }

    }
    
    #===================filter student working===========================#
    public function filter_students(Request $request)
    {
        try {
            
            $student = User::where('user_type', 'student')
                ->when($request->university, function ($query) use ($request) {
                    $query->where('university', 'like', '%'.$request->university.'%');
                })
                ->when($request->country, function ($query) use ($request) {
                    $query->where('country_id', $request->country);
                })
                ->get();

            if ($student->count() == 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }
            return response(["code" => 1, "data" => $student]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    public function filter_students_test(Request $request)
    {
        try {
            $student = User::where('user_type', 'student')
                ->when($request->university, function ($query) use ($request) {
                    $query->where('university', $request->university);
                })
                ->when($request->country, function ($query) use ($request) {
                    $query->where('country_id', $request->country);
                })
                ->get();

            if ($student->count() == 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }
            return response(["code" => 1, "data" => $student]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }


    #===============filter student by univeristy=============#
    public function get_students_by_university($university)
    {
        try {
            $student = User::where('university', $university)->where('user_type', 'student')->latest()->get();
            if ($student->count() == 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }
            return response(["code" => 1, "data" => $student]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    #===============filter student by country==================#
    public function get_students_by_country($country_id)
    {
        try {
            $student = User::where('country_id', $country_id)->where('user_type', 'student')->latest()->get();
            if ($student->count() == 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }
            return response(["code" => 1, "data" => $student]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }


    #=============filter student my rating============#
    public function filter_by_rating(Request $request)
    {
        try {
            $rating = ProguideRating::with(['user_rated'])
                ->select('proguide_id', DB::raw('AVG(rating) as average_rating'))
                ->when($request->rating, function ($query) use ($request) {
                    $query->where('rating', $request->rating)->select('proguide_id', DB::raw('AVG(rating) as average_rating'));
                })
                ->get();

            if ($rating->isEmpty()) {
                return response()->json(["code" => 3, "message" => "No record found"]);
            }

            return response()->json(["code" => 1, "data" => $rating]);
        } catch (\Throwable$th) {
            return response()->json(["code" => 3, "error" => $th->getMessage()]);
        }
    }


    #================get all proguide alphabetically===========#
    public function get_proguides_alphabetically()
    {
        try {
            $users = User::where('user_type', 'proguide')->orderBy('full_name')->get();

            if ($users->isEmpty()) {
                return response()->json(["code" => 3, "message" => "No record found"]);
            }

            return response()->json(["code" => 1, "data" => $users]);

        } catch (\Throwable$th) {
            return response()->json(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    private function compressImage($image, $path){

        $imageName = Str::uuid() . ".webp";
        $imageToSave = $path . '/' . $imageName;

        #check if directory exists
        if (!File::isDirectory($path)) {
            # create directory if it doesn't exists
            File::makeDirectory($path, 0777, true, true);
        }
        $imageResize = Image::make($image);
        $imageResize->orientate()
            ->fit(600, 360, function ($constraint) {
                $constraint->upsize();
            })->save($imageToSave);
        
        return $imageName;
    }

}
