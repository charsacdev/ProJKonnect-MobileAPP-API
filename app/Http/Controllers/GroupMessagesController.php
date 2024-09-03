<?php

namespace App\Http\Controllers;

use App\Http\Resources\GroupChatResource;
use App\Models\BadWords;
use App\Models\GroupMessage;
use App\Models\UserGroup;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Facades\Storage;
use Str;
use Notification;
use App\Notifications\SendPushNotification;

class GroupMessagesController extends Controller
{
    #=============SEND GROUP MESSAGE===============#
    public function create_group_messages(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "group_id" => "required",
                "message" => "required",

            ]);

            if ($validator->fails()) {
                return response()->json(["code" => 3, 'error' => $validator->errors()], 401);
            }

            #check if request have filesize
            if ($request->hasFile('group_file')){

                $validate = Validator::make($request->all(), [
                    'file' => 'mimes:jpeg,png,jpg,gif,svg,pdf,docx|max:10000',
                ]);
                if ($validate->fails()) {
                    return response()->json(["code" => 3, 'error' => $validate->errors()->first()]);
                }
                // $files = $request->group_file->store('group_messages_files', 'public');
                $ext = $request->file('group_file')->extension();
                $size = $request->file('group_file')->getSize();
                
                #upload file AWS
                $file = $request->file('group_file');
                $fileName = Str::uuid().".".$request->file('group_file')->extension();
                Storage::disk('group_file')->put($fileName, file_get_contents($file));

                #$fileUrl = Storage::disk('profile_photo')->url($fileName);
                $fileUrl = "https://myprojkonnect-s3bucket.s3.amazonaws.com/groupmessage-files/".$fileName;
                $fileUrl2 = "groupmessage-files/".$fileName;
                
                #filter message
                $filteredText = $this->filter($request->message, $request->user_id);
                $createMessage = GroupMessage::create([
                    "user_id" => auth()->user()->id,
                    "group_id" => $request->group_id,
                    "message" => $filteredText,
                    "files" => $fileUrl ?? null,
                    "chat_code" => auth()->user()->id . "" . $request->group_id,
                    "file_type" => $ext ?? null,
                    'file_size'=> $size ?? null,
                 ]);
            }
            
            #filter message and send without files
            $filteredText = $this->filter($request->message, $request->user_id);
            $createMessage = GroupMessage::create([
                "user_id" => auth()->user()->id,
                "group_id" => $request->group_id,
                "message" => $filteredText,
                "files" => $fileUrl ?? null,
                "chat_code" => auth()->user()->id . "" . $request->group_id,
                "file_type" => $ext ?? null,
                'file_size'=> $size ?? null,
             ]);
             
            #send push notification for group message
            $groupName = Group::where(['id' => $request->group_id])->first();
            
            $title=$groupName->group_name."Group Notification Message";
            $message=$filteredText;
            
            #get all group users
            $allgroupMembers=UserGroup::where(['id'=>$request->group_id])->get();
            foreach ($allgroupMembers as $user) {
                $fcmTokens=User::where("id",$user->user_id)->first()->fcm_token;
                if(!empty($fcmTokens)){
                    Notification::send(null,new SendPushNotification($title,$message,$fcmTokens));
                }
            }

            return response(["code" => 1, "message" => "message sent"]);
        } 
        catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    public function get_group_messages($id)
    {
        try {
            $groupMessages = GroupMessage::with('user', 'group')
                ->where('group_id', $id)
                #->where('user_id', auth()->user()->id)
                ->orderBy('id', 'asc')
                ->get();

            if ($groupMessages->count() == 0) {
                return response(['code' => 3, 'message' => "No record found"]);
            }
            return response(["code" => 1, "data" => $groupMessages]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    public function get_last_messages_in_a_group()
    {
        try {
            $message = GroupMessage::with('user', 'group')
                ->where('user_id', auth()->user()->id)
                ->orderBy('created_at', 'desc')
                ->get()->unique('chat_code');

            if ($message->count() == null) {
                return response(['code' => 3, 'message' => "No record found"]);
            }

            $toArray = new GroupChatResource($message);

            return response(["code" => 1, "data" => $toArray]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }
    
    
    #delete chat for users
    public function destroy($id){
        try{
            $messagedelete = GroupMessage::where(['id'=>$id,'user_id'=>auth()->user()->id])->delete();
            if($messagedelete){
                return response(['code' => 2, 'data' => "message deleted"]);
            }
            else{
                return response(['code' => 3, 'data' => "cant a message u did not send"]);
            }
        }
        catch (\Throwable$th) {
            return response()->json(['code' => 4, 'error' => "Something went wrong"], 500);
        }
        
    }

    private function filter($text, $senderId)
    {

        $badWords = BadWords::get();

        $list = [];
        #loop through the array extract
        #get the words and map them to an array
        foreach ($badWords as $key) {
            $list[] = $key['word'];
        }

        #filter the words

        $filteredText = $text;
        foreach ($list as $badWord) {
            $filteredText = str_ireplace($badWord, \str_repeat('*', strlen($badWord)), $filteredText);

            if (stripos($text, $badWord) !== false) {
                #check if user has been flagged for more than 3 times

                $user = User::find($senderId);

                if ($user->bad_word_count != 3) {
                    $user->bad_word_count++;

                    $user->save();
                }

                #if they have been flagged for more than 3x
                #update their status to blocked

                if ($user->bad_word_count === 3) {
                    $user->status = "blocked";

                    $user->save();

                    auth()->user()->currentAccessToken()->delete();

                }
            }

        }

        return $filteredText;

    }
}
