<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Referal;
use App\Models\Referal_transaction;
use App\Models\RefWallet;
use App\Models\User;

class ReferalController extends Controller
{

    #==============REFERALS FOR USER==========#
    public function get_referals_for_a_user(){
        try {
            $referals = Referal::where('referal_id', auth()->user()->id)->latest()->get();

            if ($referals->count() == 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }

            return response(["code" => 1, "data" => $referals]);

        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }

    #==============GET REFERAL COMMISSION=========#
    public function get_referal_commission(){

        try {
            $referal_transactions = Referal_transaction::with('user_referal')->where('referred_by', auth()->user()->id)->latest()->get();
            if ($referal_transactions->count() < 0) {
                return response(["code" => 3, "message" => "No record found"]);
            }
            return response(["code" => 1, "data" => $referal_transactions]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }



    #============REFERAL WALLET BALANCE==========#
    public function get_referal_wallet_ballance() {
        try {
            $wallet_balance = User::where('id', auth()->user()->id)->first();

            if ($wallet_balance) {
                
                return response(["code" => 1, "data" =>$wallet_balance->referal_earnings]);
            }


        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }
    
    #==============get referal wallet balance from usertable=============#
    public function referal_earnings(){
        try{
            
            $referal=User::where('id',auth()->user()->id)->first();
            return response(["code" => 1, "data" => $referal->referal_earnings]);
            
        }catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }
}
