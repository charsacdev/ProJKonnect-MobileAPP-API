<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\Request;
use Validator;
use Notification;
use Illuminate\Support\Facades\Storage;
use Str;
use App\Notifications\SendPushNotification;

class GroupController extends Controller
{

    #==================CREATE NEW GROUP=================#
    public function create_group(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                "group_name" => "required",
                "group_description" =>"required",
                "file"=>"required",
                "group_members" => [],
            ]);

            if ($validator->fails()) {
                return response()->json(["code" => 3, 'error' => $validator->errors()], 401);
            }

            if ($request->hasFile('file')) {
                $validate = Validator::make($request->all(), [
                    'file' => 'mimes:jpeg,png,jpg,gif,svg,pdf,docx|max:10000',
                ]);
                if ($validate->fails()) {
                    return response()->json(["code" => 3, 'error' => $validate->errors()->first()]);
                }
                
                $files = $request->file->store('group_files', 'public');
                
                #upload file AWS
                $file = $request->file('file');
                $fileName = Str::uuid().".".$request->file('file')->extension();
                Storage::disk('group_file')->put($fileName, file_get_contents($file));

                #$fileUrl = Storage::disk('profile_photo')->url($fileName);
                $fileUrl = "https://myprojkonnect-s3bucket.s3.amazonaws.com/groupmessage-files/".$fileName;
                $fileUrl2 = "groupmessage-files/".$fileName;

            }

            $group = Group::create([
                'group_name' => $request->group_name,
                'group_description' => $request->group_description,
                'user_id' => auth()->user()->id,
                'group_image' => $fileUrl ?? null,

            ]);
            
            #add the creator to the group
            UserGroup::insert([
                        "user_id" => auth()->user()->id,
                        "group_id" => $group->id,
                    ]);

            #add some members to group

            if ($request->group_members != null) {
                $members = json_decode($request->group_members);
                foreach ($members as $key => $value) {
                    UserGroup::create([
                        "user_id" => $value,
                        "group_id" => $group->id,
                    ]);

                    #update the group member count

                    $group = Group::find($group->id);

                    $group->number_of_participants++;

                    $group->save();
                }
            }

            return response(["code" => 1, "message" => "group created successfully","details"=>$group]);

        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }


    #=================GET USER WITH SIMILAR INTEREST====================#
    public function users_with_similar_interests(Request $request)
    {
        try {

            $user = auth()->user();

            $guides = User::where('user_type', 'student')
                ->join('user_interests', 'users.id', '=', 'user_interests.user_id')
                ->whereIn('user_interests.interest_id', $user->userinterests()->pluck('interest_id'))
                ->select('users.id', 'users.full_name', 'users.username', 'users.email', 'users.bio', 'users.profile_image', 'users.status', 'users.country_id', 'users.phone_number')
                ->when($request->search_user, function ($query) use ($request) {
                    $query->where("users.username", "like", "%" . $request->search_user . "%");
                })
                ->get();

            if (count($guides) == 0) {
                return response(["code" => 3, "message" => "No student  with similar interest or username found"]);
            }

            return response(["code" => 1, "data" => $guides]);

        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }


    #===================ADD USER WITH SIMILAR INTEREST==================#
    public function add_users(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                "group_id" => "required",
                "user_id" => "required",

            ]);

            if ($validator->fails()) {
                return response()->json(["code" => 3, 'error' => $validator->errors()], 401);
            }

            #check if user exist in group previously
            $checkUserGroup=UserGroup::where(["group_id" => $request->group_id,"user_id"=>$request->user_id])->get();
            if($checkUserGroup->count()>0){
                return response(["code" => 1, "message" => "user already added previously"]);
            }
            
            #add user if not avaliable
            $add_users = UserGroup::create([
                "group_id" => $request->group_id,
                "user_id" => $request->user_id,
            ]);

            $groupName=Group::find($request->group_id);

            #send notification on adding members to group
            $title="New Group Notification";
            $message="You have been added to".$groupName->group_name." group";
            $fcmTokens=User::where("id",$request->user_id)->first()->fcm_token;
            if(!empty($fcmTokens)){
                Notification::send(null,new SendPushNotification($title,$message,$fcmTokens));
            }
    
            #update the group member count
            $group = Group::find($request->group_id);

            $group->number_of_participants++;

            $group->save();

            return response(["code" => 1, "message" => "user add to group successfully"]);

        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    public function get_all_groups_created_by_a_particular_user()
    {

        try {
            $group = Group::with('user')->where('status', 'active')->where('user_id', auth()->user()->id)->latest()->get();

            if ($group->count() == 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }

            return response(["code" => 1, "data" => $group]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }

    }

  
    public function get_a_particular_group_for_a_user($id)
    {
        try {
            $group = Group::find($id)->where('user_id', auth()->user()->id)->first();
            if ($group == null) {
                return response(["code" => 3, "message" => "No record found"]);
            }

            return response(["code" => 1, "data" => $group]);

        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }


    #================DELETE USER GROUP================#
    public function delete_group($id)
    {
        try {
            $group = Group::where(["id"=>$id,"user_id"=>auth()->user()->id])->delete();
            if($group){
                return response(["code" => 1, "message" => "group deleted successfully"]);
            }
            else{
                return response(["code" => 1, "message" => "you can not delete a group you did not created."]);
            }
            
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }


    #==============EDIT GROUP DETAILS=================#
    public function edit_group(Request $request, $id){

         try {
                $validator = Validator::make($request->all(), [
                   'group_name' => 'nullable',
                   'group_description' => 'nullable',
                   'file'=>'nullable',
               ]);
   
               if ($validator->fails()) {
                   return response()->json(['code' => 2,'error' => $validator->errors()], 401);
               }
   
                $group = Group::find($id);
                $group->group_name = $request->group_name ?? $group->group_name;
                $group->group_description = $request->group_description ?? $group->group_description;
                
                #check if request have filesize
                if ($request->hasFile('file')){

                    $validate = Validator::make($request->all(), [
                        'file' => 'mimes:jpeg,png,jpg,gif,svg,pdf,docx|max:10000',
                    ]);
                    if ($validate->fails()) {
                        return response()->json(["code" => 3, 'error' => $validate->errors()->first()]);
                    }
            
                    $ext = $request->file('file')->extension();
                    $size = $request->file('file')->getSize();
                    
                    #upload file AWS
                    $file = $request->file('file');
                    $fileName = Str::uuid().".".$request->file('file')->extension();
                    Storage::disk('group_file')->put($fileName, file_get_contents($file));

                    #$fileUrl = Storage::disk('profile_photo')->url($fileName);
                    $fileUrl = "https://myprojkonnect-s3bucket.s3.amazonaws.com/groupmessage-files/".$fileName;
                    $fileUrl2 = "groupmessage-files/".$fileName;

                    $group->group_image = $fileUrl ?? $group->group_image;
                
            }

            $group->save();

            return response(["code" => 1, "message" => "update successfully"]);

        } 
        catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    #===============DELETE USER FROM GROUP===================#
    public function delete_users_from_group($user_id, $group_id)
    {
        try {
            $usergroup = UserGroup::where('user_id', $user_id)->where('group_id', $group_id)->delete();

            $group = Group::find($group_id);
            
            #check if the user leaving is admin
            if($group->user_id=auth()->user()->id){
                
                  $allUser=UserGroup::all();
                  
                  #update new admin
                  $group->user_id=$allUser[0]->id;
                  
            }

            $group->number_of_participants--;

            $group->save();
            return response(["code" => 1, "message" => "user deleted from group successfully"]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }


    #===================CHANGE GROUP STATUS=======================#
    public function change_group_status($id)
    {
        try {
            $group = Group::find($id);

            ($group->status == 'active') ? $group->status = 'inactive' : $group->status = 'inactive';

            $group->save();

            return response(["code" => 1, "message" => "status changed successfully"]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    #===================GET GROUP WITH USERS============#
    public function get_groups_with_users()
    {
        try {
            $group = Group::with(['user_group' => function ($query) {
                $query->with('user');
            }])->where('user_id', auth()->user()->id)->get();

            if ($group->count() == 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }

            return response(["code" => 1, "data" => $group]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }
    
    #l=================ist of group memebers======================#
     public function get_groups_with_users_list($id)
    {
        try {
            $group = UserGroup::with(['user','grouper'])->where(['group_id'=>$id])->select('id', 'user_id','group_id')->get();

            if ($group->count() == 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }

            return response(["data" => $group]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    public function get_user_groups()
    {

        try {
            $userGroups = UserGroup::with(['group'])->where('user_id', auth()->user()->id)->latest()->get();
            
            //$userGroups = UserGroup::where('user_id', auth()->user()->id)->get();
            //  $userGroups = UserGroup::with(['group' => function ($query) {
            //     $query->with('user');
            // }])->where('user_id', auth()->user()->id)->latest()->get();

            if ($userGroups->count() == 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }

            return response(["code" => 1, "data" => $userGroups]);

        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    #===========Exit group==================#
    public function exit_group_by_participant($group_id){
        try {
            $usergroup = UserGroup::where('user_id', auth()->user()->id)->where('group_id', $group_id)->delete();

            $group = Group::find($group_id);

            $group->number_of_participants--;

            $group->save();
            return response(["code" => 1, "message" => "user deleted from group successfully"]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }
}
