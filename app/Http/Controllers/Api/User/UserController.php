<?php

namespace App\Http\Controllers\Api\User;

use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Api\Helpers;
use App\Http\Resources\User\AddMoneyLogs;
use App\Http\Resources\User\AddSubBalanceLogs;
use App\Http\Resources\User\GiftCardLogs;
use App\Http\Resources\User\TransferMoneyLogs;
use App\Http\Resources\User\VirtualCardLogs;
use App\Http\Resources\User\WithdrawMoneyLog;
use App\Models\Admin\TransactionSetting;
use App\Models\Transaction;
use App\Models\UserWallet;
use App\Models\VirtualCardApi;
use App\Providers\Admin\BasicSettingsProvider;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
use App\Traits\AdminNotifications\AuthNotifications;


class UserController extends Controller
{
    use AuthNotifications;
    protected $api;
    public function __construct()
    {
        $cardApi = VirtualCardApi::first();
        $this->api =  $cardApi;
    }
    public function home(){

        $user = auth()->user();
        $totalAddMoney = Transaction::auth()->addMoney()->where('status',1)->sum('request_amount');
        $virtualCards = activeCardData()['active_cards'];
        $userWallet = UserWallet::where('user_id',$user->id)->get()->map(function($data){
        return[
            'balance' => getAmount($data->balance,2),
            'currency' => get_default_currency_code(),
        ];
        })->first();
        $transactions = Transaction::auth()->latest()->take(5)->get()->map(function($item){
            if($item->type == payment_gateway_const()::TYPEADDMONEY){
                return[
                    'id' => $item->id,
                    'type' =>$item->attribute,
                    'trx' => $item->trx_id,
                    'transaction_type' => $item->type,
                    'request_amount' => getAmount($item->request_amount,2).' '.get_default_currency_code() ,
                    'payable' => getAmount($item->payable,2).' '.$item->currency->currency_code,
                    'status' => $item->stringStatus->value ,
                    'remark' => $item->remark??"",
                    'date_time' => $item->created_at ,

                ];
            }elseif($item->type == payment_gateway_const()::TYPETRANSFERMONEY){
                if($item->attribute == payment_gateway_const()::SEND){
                    return[
                        'id' => @$item->id,
                        'type' =>$item->attribute,
                        'trx' => @$item->trx_id,
                        'transaction_type' => $item->type,
                        'request_amount' => getAmount(@$item->request_amount,2).' '.get_default_currency_code() ,
                        'payable' => getAmount(@$item->payable,2).' '.get_default_currency_code(),
                        'remark' => $item->remark??"",
                        'status' => @$item->stringStatus->value ,
                        'date_time' => @$item->created_at ,
                    ];
                }elseif($item->attribute == payment_gateway_const()::RECEIVED){
                    return[
                        'id' => @$item->id,
                        'type' =>$item->attribute,
                        'trx' => @$item->trx_id,
                        'transaction_type' => $item->type,
                        'status' => @$item->stringStatus->value ,
                        'request_amount' => getAmount(@$item->request_amount,2).' '.get_default_currency_code() ,
                        'payable' => getAmount(@$item->payable,2).' '.get_default_currency_code(),
                        'remark' => $item->remark??"",
                        'date_time' => @$item->created_at ,
                    ];

                }

            }elseif($item->type == payment_gateway_const()::VIRTUALCARD){
                return[
                    'id' => $item->id,
                    'type' =>$item->attribute,
                    'trx' => $item->trx_id,
                    'transaction_type' => "Virtual Card".'('. @$item->remark.')',
                    'request_amount' => getAmount($item->request_amount,2).' '.get_default_currency_code() ,
                    'payable' => getAmount($item->payable,2).' '.get_default_currency_code(),
                    'status' => $item->stringStatus->value ,
                    'remark' => $item->remark??"",
                    'date_time' => $item->created_at ,

                ];

            }elseif($item->type == payment_gateway_const()::TYPEADDSUBTRACTBALANCE){
                return[
                    'id' => $item->id,
                    'type' =>$item->attribute,
                    'trx' => $item->trx_id,
                    'transaction_type' => $item->type,
                    'request_amount' => getAmount($item->request_amount,2).' '.get_default_currency_code() ,
                    'payable' => getAmount(@$item->payable,2).' '.get_default_currency_code(),
                    'remark' => $item->remark??"",
                    'status' => $item->stringStatus->value ,
                    'date_time' => $item->created_at ,

                ];

            }elseif($item->type == payment_gateway_const()::WITHDRAWMONEY){
                return[
                    'id' => $item->id,
                    'type' =>$item->attribute,
                    'trx' => $item->trx_id,
                    'transaction_type' => $item->type,
                    'request_amount' => getAmount($item->request_amount,2).' '.get_default_currency_code() ,
                    'payable' => getAmount(@$item->payable,2).' '.get_default_currency_code(),
                    'remark' => $item->remark??"",
                    'status' => $item->stringStatus->value ,
                    'date_time' => $item->created_at ,

                ];

            }elseif($item->type == payment_gateway_const()::GIFTCARD){
                return[
                    'id' => $item->id,
                    'type' =>$item->attribute,
                    'trx' => $item->trx_id,
                    'transaction_type' => $item->type,
                    'request_amount' => getAmount($item->request_amount,2).' '.get_default_currency_code() ,
                    'payable' => getAmount(@$item->payable,2).' '.get_default_currency_code(),
                    'remark' => $item->remark??"",
                    'status' => $item->stringStatus->value ,
                    'date_time' => $item->created_at ,

                ];

            }

        });
        $cardCreateCharge = TransactionSetting::where('slug','virtual_card')->where('status',1)->get()->map(function($data){
            return [
                'id' => $data->id,
                'slug' => $data->slug,
                'title' => $data->title,
                'fixed_charge' => getAmount($data->fixed_charge,2),
                'percent_charge' => getAmount($data->percent_charge,2),
                'min_limit' => getAmount($data->min_limit,2),
                'max_limit' => getAmount($data->max_limit,2),
            ];
        })->first();
        $cardReloadCharge = TransactionSetting::where('slug','reload_card')->where('status',1)->get()->map(function($data){
            return [
                'id' => $data->id,
                'slug' => $data->slug,
                'title' => $data->title,
                'fixed_charge' => getAmount($data->fixed_charge,2),
                'percent_charge' => getAmount($data->percent_charge,2),
                'min_limit' => getAmount($data->min_limit,2),
                'max_limit' => getAmount($data->max_limit,2),
            ];
        })->first();

        //module access permissions
        $module_access =[
            'add_money'         => module_access_api('add-money'),
            'transfer_money'    => module_access_api('transfer-money'),
            'withdraw_money'    => module_access_api('withdraw-money'),
            'virtual_card'      => module_access_api('virtual-card'),
            'gift_cards'        => module_access_api('gift-cards')
        ];
        $basic_settings = BasicSettingsProvider::get();
        if(!$basic_settings) {
            $message = ['error'=>[__("Basic setting not found!")]];
            return Helpers::error($message);
        }
        $notification_config = $basic_settings->push_notification_config;

        if(!$notification_config) {
            $message = ['error'=>[__("Notification configuration not found!")]];
            return Helpers::error($message);
        }

        $pusher_credentials = [
            "instanceId" => $notification_config->instance_id ?? '',
            "secretKey" => $notification_config->primary_key ?? '',
        ];

        $data =[
            'default_image'         => "public/backend/images/default/profile-default.webp",
            "image_path"            => "public/frontend/user",
            'pusher_credentials'    => (object)$pusher_credentials,
            'module_access'         => (object)$module_access,
            'user'                  =>  $user,
            'base_curr'             => get_default_currency_code(),
            'userWallet'            => (object)$userWallet,
            'active_virtual_system' => activeCardSystem(),
            'card_create_charge'    => $cardCreateCharge,
            'card_reload_charge'    => $cardReloadCharge,
            'totalAddMoney'         => getAmount($totalAddMoney,2).' '.get_default_currency_code(),
            'active_cards'          => $virtualCards,
            'transactions'          => $transactions,
        ];
        $message =  ['success'=>[__('User Dashboard')]];
        return Helpers::success($data,$message);
    }
    public function profile(){
        $user = auth()->user();
        $data =[
            'default_image'    => "public/backend/images/default/profile-default.webp",
            "image_path"  =>  "public/frontend/user",
            'user'         =>   $user,
            'countries' =>get_all_countries()
        ];
        $message =  ['success'=>[__("User Profile")]];
        return Helpers::success($data,$message);
    }
    public function profileUpdate(Request $request){
        $user = auth()->user();
        $validator = Validator::make($request->all(), [
            'firstname'     => "required|string|max:60",
            'lastname'      => "required|string|max:60",
            'country'       => "nullable|string|max:50",
            'phone_code'    => "nullable|string|max:6",
            'phone'         => "nullable|string|max:11|unique:users,mobile,".$user->id,
            'state'         => "nullable|string|max:50",
            'city'          => "nullable|string|max:50",
            'zip_code'      => "nullable|numeric",
            'address'       => "nullable|string|max:250",
            'image'         => "nullable|image|mimes:jpg,png,svg,webp|max:10240",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $data = $request->all();
        $mobileCode = remove_speacial_char($data['phone_code']);
        $mobile = remove_speacial_char($data['phone']);

        $validated['firstname']      =$data['firstname'];
        $validated['lastname']      =$data['lastname'];
        $validated['mobile']        = $mobile;
        $validated['mobile_code']   = $mobileCode;
        $complete_phone             = $mobileCode.$mobile;

        $validated['full_mobile']   = $complete_phone;

        $validated['address']       = [
            'country'   =>$data['country']??"",
            'state'     => $data['state'] ?? "",
            'city'      => $data['city'] ?? "",
            'zip'       => $data['zip_code'] ?? "",
            'address'   => $data['address'] ?? "",
        ];
        if($request->hasFile("image")) {
            if($user->image == 'default.png'){
                $oldImage = null;
            }else{
                $oldImage = $user->image;
            }
            $image = upload_file($data['image'],'user-profile', $oldImage);
            $upload_image = upload_files_from_path_dynamic([$image['dev_path']],'user-profile');
            delete_file($image['dev_path']);
            $validated['image']     = $upload_image;
        }

        try{
            $user->update($validated);
        }catch(Exception $e) {
            $error = ['error'=>[__("Something Went Wrong! Please Try Again")]];
            return Helpers::error($error);
        }
        $message =  ['success'=>[__("Profile successfully updated!")]];
        return Helpers::onlysuccess($message);
    }
    public function passwordUpdate(Request $request) {

        $basic_settings = BasicSettingsProvider::get();
        $passowrd_rule = "required|string|min:6|confirmed";
        if($basic_settings->secure_password) {
            $passowrd_rule = ["required",Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised(),"confirmed"];
        }
        $validator = Validator::make($request->all(), [
            'current_password'      => "required|string",
            'password'              => $passowrd_rule,
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        if(!Hash::check($request->current_password,auth()->user()->password)) {
            $error = ['error'=>[__('Current password didn\'t match')]];
            return Helpers::error($error);
        }

        try{
            auth()->user()->update([
                'password'  => Hash::make($request->password),
            ]);
        }catch(Exception $e) {
            $error = ['error'=>[__("Something Went Wrong! Please Try Again")]];
            return Helpers::error($error);
        }
        $message =  ['success'=>[__("Password successfully updated!")]];
        return Helpers::onlysuccess($message);

    }
    public function deleteAccount(Request $request) {
        $user = auth()->user();
        $user->status = false;
        $user->email_verified = false;
        $user->sms_verified = false;
        $user->kyc_verified = false;
        $user->deleted_at = now();
        $user->save();
        try{
            //admin notification
            $this->deleteUserNotificationToAdmin($user,"USER",'api');
            $user->token()->revoke();
            $message =  ['success'=>[__('User deleted successfully')]];
            return Helpers::onlysuccess($message);
        }catch(Exception $e) {
            $error = ['error'=>[__("Something Went Wrong! Please Try Again")]];
            return Helpers::error($error);
        }
    }
    public function transactions(){
        // start transaction now
        $addMoney           = Transaction::auth()->addMoney()->orderByDesc("id")->latest()->get();
        $transferMoney      = Transaction::auth()->transferMoney()->orderByDesc("id")->get();
        $virtualCard        = Transaction::auth()->virtualCard()->orderByDesc("id")->get();
        $addSubBalance      = Transaction::auth()->addSubBalance()->orderByDesc("id")->get();
        $withdrawMoney      = Transaction::auth()->withdrawMoney()->orderByDesc("id")->get();
        $giftCards          = Transaction::auth()->giftCards()->orderByDesc("id")->get();


        $transactions = [
            'add_money'         => AddMoneyLogs::collection($addMoney),
            'send_money'        => TransferMoneyLogs::collection($transferMoney),
            'virtual_card'      => VirtualCardLogs::collection($virtualCard),
            'add_sub_balance'   => AddSubBalanceLogs::collection($addSubBalance),
            'withdraw_money'    => WithdrawMoneyLog::collection($withdrawMoney),
            'gift_cards'        => GiftCardLogs::collection($giftCards),
        ];
        $transactions = (object)$transactions;

        $transaction_types = [
            'add_money'         => PaymentGatewayConst::TYPEADDMONEY,
            'transfer_money'    => PaymentGatewayConst::TYPETRANSFERMONEY,
            'virtual_card'      => PaymentGatewayConst::VIRTUALCARD,
            'add_sub_balance'   => PaymentGatewayConst::TYPEADDSUBTRACTBALANCE,
            'withdraw_money'    => PaymentGatewayConst::WITHDRAWMONEY,
            'gift_cards'        => PaymentGatewayConst::GIFTCARD,

        ];
        $transaction_types = (object)$transaction_types;
        $data =[
            'transaction_types' => $transaction_types,
            'transactions'=> $transactions,
        ];
        $message =  ['success'=>[__("All Transactions")]];
        return Helpers::success($data,$message);
    }
}
