<?php

namespace App\Http\Controllers;

use App\Http\Resources\MessageResource;
use App\Models\BadWords;
use App\Models\Chats;
use App\Models\User;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Facades\Auth;
use Notification;
use App\Notifications\SendPushNotification;
use Illuminate\Support\Facades\Storage;
use Str;

class ChatsController extends Controller
{
   
    #=============SEND MESSAGE=========#
    public function store(Request $request)
    {

        try {
            $validator = Validator::make($request->all(), [
                'receiver_id' => 'required',
                'message' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 401);
            }

            $filteredMessage = $this->filter($request->message, auth()->user()->id);

            #=====check if message have file=====#
            if ($request->hasFile('chat_file')) {
                $validate = Validator::make($request->all(), [
                    'file' => 'mimes:jpeg,png,jpg,gif,svg,pdf,docx,webp|max:10000',
                ]);
                if ($validate->fails()) {
                    return response()->json(["code" => 3, 'error' => $validate->errors()->first()]);
                }
                #$files = $request->chat_file->store('chat_files', 'public');
                $ext = $request->file('chat_file')->extension();
                $sizeFile = $request->file('chat_file')->getSize();

                #upload file AWS
                $file = $request->file('chat_file');
                $fileName = Str::uuid().".".$request->file('chat_file')->extension();
                Storage::disk('chat_files')->put($fileName, file_get_contents($file));

                #$fileUrl = Storage::disk('profile_photo')->url($fileName);
                $fileUrl = "https://myprojkonnect-s3bucket.s3.amazonaws.com/chat_files/".$fileName;
                $fileUrl2 = "chat_files/".$fileName;

            }

            if (auth()->user()->id > $request->receiver_id) {
                $code = auth()->user()->id . "" . $request->receiver_id;
            } 
            else {
                $code = $request->receiver_id . "" . auth()->user()->id;

            }

            $message = Chats::create([
                'sender_id' => auth()->user()->id,
                'receiver_id' => $request->receiver_id,
                'message' => $filteredMessage,
                'files' => $fileUrl ?? null,
                'chat_code' => $code,
                'file_type' => $ext ?? null,
                'file_size'=> $sizeFile ?? null,
            ]);

            #send push notification
            $title="New Message Notifcation";
            $message=$filteredMessage;
            $fcmTokens=User::where("id",$request->receiver_id)->first()->fcm_token;
            if(!empty($fcmTokens)){
                Notification::send(null,new SendPushNotification($title,$message,$fcmTokens));
            }

            return response()->json(['code' => 1, 'success' => "message sent"], 200);
        } catch (\Throwable$th) {

            return response(["code" => 3, "error" => $th->getMessage()]);
        }

    }

    #=================MESSAGE BETWEEN TWO USER=================#
    public function show($id)
    {
        try {

            $messages = Chats::with(['sender', 'receiver'])
                ->where(['sender_id'=>auth()->user()->id,'receiver_id'=>$id])
                ->orWhere(['receiver_id'=>auth()->user()->id,'sender_id'=>$id])
                ->where('receiver_id',$id)
                ->orWhere(function ($query) use ($id) {
                    $query->where('sender_id', $id);
                    $query->where('receiver_id',auth()->user()->id);
                    #
                })
                ->orderBy('id', 'asc')
                ->get();

            if ($messages->count() == 0) {
                return response(['code' => 3, 'message' => 'No record found']);
            }

            return response()->json(['code' => 1, 'data' => $messages]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);

        }

    }

    #===================EDIT MESSAGE==================#
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'message' => 'max:500',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 401);
            }

            $message = Chats::find($id);
            if (!$message) {
                return response(['code' => 3, 'message' => "No record found"]);
            }

            $message->update([
                'message' => $request->message ?? $message->message,
                'receiver_id' => $request->receiver_id ?? $message->receiver_id,

            ]);

            return response()->json(['code' => 1, 'success' => 'Messages updated successfully'], 200);
        } catch (\Throwable$th) {
            return response()->json(['code' => 3, 'error' => 'Something went wrong'], 500);
        }
    }

    #=================GET THE LAST MESSAGE FOR A USER=================#
    public function getMessages()
    {

        try {
            # this method fetches list of chatted users conversations
            $message = Chats::with(['sender', 'receiver',
            ])->where('sender_id', auth()->user()->id)
                ->orWhere('receiver_id', auth()->user()->id)
                #->groupBy('chat_code','desc')
                ->orderBy('created_at', 'desc')
                ->get()->unique('chat_code');

            if ($message->count() == null) {
                return response(['code' => 3, 'message' => "No record found"]);
            }

            $toArray = new MessageResource($message);

            return response(['code' => 1, 'data' => $toArray]);

        } catch (\Throwable$th) {
            return response()->json(['code' => 3, 'error' => 'Something went wrong'], 500);
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
    
    #=============DELETE MESSAGE==========#
    public function destroy($id){
        try{
            $messagedelete = Chats::where(['id'=>$id,'sender_id'=>auth()->user()->id])->delete();
            if($messagedelete){
                return response(['code' => 2, 'message' => "message deleted"]);
            }else{
                return response(['code' => 2, 'message' => "message not deleted a message u did not send"]);
            }
        }
        catch (\Throwable$th) {
            return response()->json(['code' => 3, 'error' => "something went wrong"], 500);
        }
        
    }
}
