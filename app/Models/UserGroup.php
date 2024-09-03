<?php

namespace App\Models;

use App\Models\User;
use App\Models\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserGroup extends Model
{
    use HasFactory,softDeletes;

    protected $guarded = [];

    public function group(){
        return $this->belongsTo(Group::class);
    }
    
    public function grouper(){
        return $this->belongsTo(Group::class,'id')->select('id','user_id');
    }


    public function user(){
        return $this->belongsTo(User::class)->select('id','full_name','profile_image');
    }
    

}
