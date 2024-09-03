<?php

namespace App\Http\Controllers\Transaction;

use App\Custom\MailMessages;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Referal;
use App\Models\Referal_transaction;
use App\Models\RefWallet;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Settings;
use DB;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Carbon;
use Spatie\Newsletter\Facades\Newsletter as Newsletter;

class PaymentController extends Controller
{
    
    public $reference;
    
    
    #=============AUTO DISCONNECT A PROGUIDE==============#
     public function auto_subscriber(){
        try {
            
           
            #payment
            $disconnect = Payment::where(['status'=>'active'])->get();
            foreach($disconnect as $disconnection){
                
                Payment::where(['expiry_date'=>$disconnection->expiry_date])->update([
                     'status'=>'expired'
                ]);

                #mailchimp
                Newsletter::removeTags(['Basic','Platinum','Premium','On-Demand','Freemium'],$disconnection->payer_email);
                Newsletter::subscribeOrUpdate(
                    $disconnection->payer_email,
                    ['FNAME'=>'','LNAME'=>''], 
                    'subscribers',
                    ['tags' => [$disconnection->plan_option_name]]
                    );
                
            }
            
            #users details
            $user = User::where(['max_proguides'=>'0'])->get();
            foreach($user as $usering){
                
                #mailchimp
                Newsletter::removeTags(['Basic','Platinum','Premium','On-Demand','Freemium'],$usering->email);
                Newsletter::subscribeOrUpdate(
                    $usering->email,
                    ['FNAME'=>'','LNAME'=>''], 
                    'subscribers',
                    ['tags' => ['Freemium']]
              );
                
            }
    
            return response(["code"=>1,"message"=>"over them all if they wan turn goliath"]);
            
        } catch (\Throwable $th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }
    
    public function auto_disconnect_plan(){
        try {
            
            $date=Carbon::now()->format('Y-m-d g:a');
            $disconnect = Payment::where(['expiry_date'=>$date])->get();
            foreach($disconnect as $disconnection){
                
                Payment::where(['expiry_date'=>$disconnection->expiry_date])->update([
                     'status'=>'expired'
                ]);

                #mailchimp
                Newsletter::removeTags(['Basic','Platinum','Premium','On-Demand','Freemium'],$disconnection->payer_email);
                Newsletter::subscribeOrUpdate(
                    $disconnection->payer_email,
                    ['FNAME'=>'','LNAME'=>''], 
                    'subscribers',
                    ['tags' => [$disconnection->plan_option_name]]
                    );
                
            }
    
            return response(["code"=>1,"message"=>"over them all if they wan turn goliath"]);
            
        } catch (\Throwable $th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }
    
    
    #==================ACTIVE PLANS=============#
    public function get_active_plan(Request $request){
        try{
            
            $getpayment=Payment::with('description')->where(['payer_id'=>auth()->user()->id,'status'=>'active'])->latest()->get();
            return response(["code" => 1, "data" => $getpayment]);
        }
        catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
        
    }
    
    
    #================PAYMENT COMPLETED API FROM PAYSTACK==============#
    public function Create_Payment_Receiving(Request $request){
        try{
            $validator = Validator::make($request->all(), [
                "total_price" => "required",
                "plan_id"=>"required",
                "service_id"=>"required",
                "number_of_proguides"=>"required",
                "plan_option_name"=>"required",
                "amount_paid"=>"required"
                
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 401);
            }
        
            #===get the plan Id and extract duration==#
            $plan = Plan::where('id',$request->plan_id)->first();

            #==save payment details here in database and the plans infomration==#
            $Makepayment = Payment::create([
                "payer_id"=>auth()->user()->id,
                "proguide_id"=>"",
                "plan_id"=>$request->plan_id,
                "amount_paid" =>$request->amount_paid,
                "payer_email"=>auth()->user()->email,
                "payer_full_name"=>auth()->user()->full_name,
                "status"=>"active",
                "pay_status"=>"active",
                "duration"=>$plan->duration,
                "reference"=>"",
                "expiry_date"=>Carbon::now()->addDays($plan->duration)->format('Y-m-d g:a'),
                "expired_date_user"=>Carbon::now()->addDays($plan->duration),
                "service_id"=>$request->service_id,
                "number_of_proguides"=>$request->number_of_proguides,
                "plan_option_name"=>$request->plan_option_name,
                "created_at"=>Carbon::now()
            ]);
            
                #===getting the referal earning percentage===#
                $settings=Settings::where('meta_key','referral_commission')->first();
        
                
                #===update referal earnings for the first payment===#
                $checkFirstPayment=Referal_transaction::where(['user_referred'=>auth()->user()->id])->first();
                if($checkFirstPayment){
                    
                        #===checking of user have made an payment in this system===#
                        $checkeligible=Payment::where(['payer_id'=>$checkFirstPayment->referred_by])->first();
                                
                        #===getting the user basic details from referal===#
                        $userValue=User::where(['id'=>$checkFirstPayment->referred_by])->first();
                        
                        #===add referal for the first time only===#
                        if($checkeligible and $checkFirstPayment->amount_earned == 0){
                            
                                #===adding balance of commission to user==#
                                $AddReferalCommison=Referal_transaction::where(['user_referred'=>auth()->user()->id])->update([
                                    "amount_earned"=>($settings->meta_value/100)*$request->total_price,
                              ]);
                              
                              #===adding balance in referal earning users table===#
                              $AddReferalBalance=User::where(['id'=>$checkFirstPayment->referred_by])->update([
                                    "referal_earnings"=>($userValue->referal_earnings+($settings->meta_value/100)*$request->total_price),
                             ]);
                        }

                            #deducting the referal balance
                            #$paid=$request->amount_paid;
                            #$due_to_pay=$request->total_price;
                            #$referal_balance_paid=($due_to_pay-$paid);
                            
                            #getting the active user details
                            $activeUser=User::where(['id'=>auth()->user()->id])->first();
                            
                            #adding proguides and deducting from referal earnings  for the user
                            $AddProguide=User::where(['id'=>auth()->user()->id])->update([
                                    "max_proguides"=>$activeUser->max_proguides+$request->number_of_proguides,
                                    #"referal_earnings"=>($activeUser->referal_earnings-$referal_balance_paid),
                                ]);
                            
                            
                            #send payment notification
                            $this->send_payment_notification($userValue->id,$Makepayment->plan_id,$Makepayment->reference);
                            
                           
                            #mailchimp
                            Newsletter::removeTags(['Basic','Platinum','Premium','On-Demand','Freemium'],auth()->user()->email);
                            Newsletter::subscribeOrUpdate(
                                auth()->user()->email,
                                ['FNAME'=>'','LNAME'=>''], 
                                'subscribers',
                                ['tags' => [$request->plan_option_name]]
                            );
                        
                        #response
                        return response(["code" => 1, "message" =>"payment have been made successfully, subscription plan have been added"]);
                        
                    }

                    
                    #===no referals===#
                    else{
                         #==getting the active user details==#
                         $activeUser=User::where(['id'=>auth()->user()->id])->first();

                          #==adding proguides and deducting from referal earnings  for the user==#
                          $AddProguide=User::where(['id'=>auth()->user()->id])->update([
                            "max_proguides"=>$activeUser->max_proguides+$request->number_of_proguides,
                          ]);
                    
                        $userId=auth()->user()->id;
                        #send payment notification
                        $this->send_payment_notification($userId,$Makepayment->plan_id,$Makepayment->reference);
                        
                         
                            #mailchimp
                            Newsletter::removeTags(['Basic','Platinum','Premium','On-Demand','Freemium'],auth()->user()->email);
                            Newsletter::subscribeOrUpdate(
                                auth()->user()->email,
                                ['FNAME'=>'','LNAME'=>''], 
                                'subscribers',
                                ['tags' => [$request->plan_option_name]]
                            );
                            
                        
                        #response
                        return response(["code" => 1, "message" =>"payment have been made successfully, subscription plan have been added"]);
                        
                 }
                
            } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }


    #initiate payment paystack
    public function initialize_payment(Request $request)
    {

        try {

            $validator = Validator::make($request->all(), [
                "amount" => "required",
                "plan_id"=>"required",
                "number_of_proguides"=>"required",
                "service_id"=>"required",
                "plan_option_id"=>"required",
                
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 401);
            }

            $key = env('PAYSTACK_PAYMENT');

            $url = "https://api.paystack.co/transaction/initialize";

            $fields = [
                'email' => auth()->user()->email,
                'amount' => $request->amount * 100,
                'metadata' => json_encode([
                    "plan_id" => $request->plan_id,
                    "user_id" => auth()->user()->id,
                    "payer_email" => auth()->user()->email ?? null,
                    "payer_fullname" => auth()->user()->full_name ?? null,
                    "service" => $request->service_id,
                    "number_of_proguides" => $request->number_of_proguides,
                    "plan_option_id" => $request->plan_option_id,
                ]),

            ];

            $fields_string = http_build_query($fields);

            #open connection
            $ch = curl_init();

            #set the url, number of POST vars, POST data
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Authorization: Bearer {$key}",
                "Cache-Control: no-cache",
            ));

            #So that curl_exec returns the contents of the cURL; rather than echoing it
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            #execute post and get payment json data
            $result = curl_exec($ch);
            $res = json_decode($result);

            #show response
            return response(["code" => 1, "data" => $res]);
            
        

        } catch (Throwable $th) {
            return $th;
        }

    }
    
    

    public function confirm_payment(Request $request)
    {
       $reference = $this->reference;
        try {
            $key = config('paystack.paystack_secret');

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Bearer {$key}",
                    "Cache-Control: no-cache",
                ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                return response(["code" => 3, "error" => "cURL Error :" . $err]);
            }

            $result = json_decode($response);

            if ($result->data->status !== 'success') {
                throw new \Exception("Transaction failed");
            }


            #check if transaction reference already exists
            $checkRefernce = Payment::where('reference', $reference)->first();

            if ($checkRefernce->count() < 1) {
                return response(["code" => 1, "message" => "possible duplicate transaction or error in transaction"]);
            }

            #start database transaction update the payment to completed
              $verifyPay=Payment::where(['reference'=>$reference])->update([
                        "status"=>"active",
                        "pay_status"=>"completed"
                  ]);
                  
            #update proguides values in user table
            $userValue=User::where(['id'=>$checkRefernce->payer_id])->first();
            $AddProguide=User::where(['id'=>$checkRefernce->payer_id])->update([
                        "max_proguides"=>$userValue->max_proguides+$checkRefernce->number_of_proguides,
                  ]);

            //payment notification
            $this->send_payment_notification($userValue->id,$checkRefernce->plan_id,$reference);

            return response(["code" => 1, "message" => "Payment verified"]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }

    }

    // private function referal_check(int $user_id, int, float $amount_payable, int $payment_id)
    // {

    //     $check_if_referred = Referal::where('referee_id', $user_id)->first();

    //     if ($check_if_referred == null) {
    //         return false;
    //     }

    //     $ref_transactions_check = Referal_transaction::where('referred_by', $check_if_referred->referal_id)->where('user_referred', $user_id)->first();

    //     if ($ref_transactions_check != null) {
    //         return false;
    //     }
    //     #create referal_transactions

    //     $ref_transactions = Referal_transaction::create([
    //         "referred_by" => $check_if_referred->referal_id,
    //         "user_referred" => $user_id,
    //         "payment_id" => $payment_id,
    //         "amount_earned" => $amount_payable,
    //     ]);

    //     if (!$ref_transactions) {
    //         return false;
    //     }

    //     #credit ref wallet

    //     if ($ref_transactions) {
    //         #check the balance of the proguide

    //         $previous_balance = DB::select('SELECT ifnull((select available_balance from ref_wallets where user_id = ?  order by id desc limit 1), 0 ) AS prevbal', [$check_if_referred->referal_id]);

    //         #fund proguide's wallet

    //         $wallet = RefWallet::updateOrCreate(['user_id' => $proguide_id],
    //             [
    //                 "available_balance" => (int) $amount + (int) $previous_balance[0]->prevbal,
    //             ]);
    //     }

    //     return true;

    // }

    // private function percentage(int $firstNumb, int $secondNumb)
    // {
    //     return ($firstNumb / 100) * $secondNumb;
    // }

    // private static function expire($today, $day_passed)
    // {
    //     $date = date_create($today);
    //     date_add($date, date_interval_create_from_date_string($day_passed));
    //     return date_format($date, "Y-m-d");
    // }

    // private function debit_referal_wallet(int | float $referal_commision_amount, mixed $user_id)
    // {
    //     $previous_balance = DB::select('SELECT ifnull((select available_balance from ref_wallets where user_id = ?  order by id desc limit 1), 0 ) AS prevbal', [$user_id]);

    //     #debit referal commission's wallet

    //     if ($previous_balance > 0) {
    //         $wallet = RefWallet::updateOrCreate(['user_id' => $proguide_id],
    //             [
    //                 "available_balance" => (int) $previous_balance[0]->prevbal - (int) $referal_commission_amount,
    //             ]);
    //     }

    // }

    #====================USER WALLET BALANCE===============#
    public function wallet_balance(){

        try {
            $wallet_balance = Wallet::select('available_balance','pending_balance','duration')->where('user_id', auth()->user()->id)->first();

            if ($wallet_balance == null) {
                return response(["code" => 1, "data" => 0]);
            }

            return response(["code" => 1, "data" =>$wallet_balance]);
        } catch (\Throwable$th) {
            return response(["code" => 3, "error" => $th->getMessage()]);
        }
    }
  
     private function send_payment_notification($user_id,$plan_id,$reference){
        #get user email...

        $user = User::where('id', $user_id)->first();

        $plan = Plan::find($plan_id);

        MailMessages::PaymentNotificationMail($user->email, $user->full_name, $plan->duration, $plan->plan, $plan->amount, $reference);
    }

}
