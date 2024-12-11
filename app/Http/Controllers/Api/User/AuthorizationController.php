<?php

namespace App\Http\Controllers\Api\User;

use Exception;
use Illuminate\Http\Request;
use App\Constants\GlobalConst;
use App\Http\Helpers\Response;
use App\Models\Admin\SetupKyc;
use Illuminate\Support\Carbon;
use App\Http\Helpers\Api\Helpers;
use App\Models\UserAuthorization;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Providers\Admin\BasicSettingsProvider;
use App\Notifications\User\Auth\SendAuthorizationCode;
use App\Traits\ControlDynamicInputFields;
use Pusher\PushNotifications\PushNotifications;

class AuthorizationController extends Controller
{
    use ControlDynamicInputFields;
    public function sendMailCode()
    {
        $user = auth()->user();
        $resend = UserAuthorization::where("user_id",$user->id)->first();
        if( $resend){
            if(Carbon::now() <= $resend->created_at->addMinutes(GlobalConst::USER_VERIFY_RESEND_TIME_MINUTE)) {

                $error = ['error'=>[__('You can resend verification code after').Carbon::now()->diffInSeconds($resend->created_at->addMinutes(GlobalConst::USER_VERIFY_RESEND_TIME_MINUTE)). ' seconds']];
                return Helpers::error($error);
            }
        }
        $data = [
            'user_id'       =>  $user->id,
            'code'          => generate_random_code(),
            'token'         => generate_unique_string("user_authorizations","token",200),
            'created_at'    => now(),
        ];
        DB::beginTransaction();
        try{
            if($resend) {
                UserAuthorization::where("user_id", $user->id)->delete();
            }
            DB::table("user_authorizations")->insert($data);
            $user->notify(new SendAuthorizationCode((object) $data));
            DB::commit();
            $message =  ['success'=>[__('Verification code send success')]];
            return Helpers::onlysuccess($message);
        }catch(Exception $e) {
            DB::rollBack();
            $error = ['error'=>[__("Something went wrong! Please try again")]];
            return Helpers::error($error);
        }
    }
    public function mailVerify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|numeric',
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $user = auth()->user();
        $code = $request->code;
        $otp_exp_sec = BasicSettingsProvider::get()->otp_exp_seconds ?? GlobalConst::DEFAULT_TOKEN_EXP_SEC;
        $auth_column = UserAuthorization::where("user_id",$user->id)->where("code",$code)->first();

        if(!$auth_column){
             $error = ['error'=>[__('Verification code does not match')]];
            return Helpers::error($error);
        }
        if($auth_column->created_at->addSeconds($otp_exp_sec) < now()) {
            $error = ['error'=>[__('Session expired. Please try again')]];
            return Helpers::error($error);
        }
        try{
            $auth_column->user->update([
                'email_verified'    => true,
            ]);
            $auth_column->delete();
        }catch(Exception $e) {
            $error = ['error'=>[__('Something went wrong! Please try again')]];
            return Helpers::error($error);
        }
        $message =  ['success'=>[__('Account successfully verified')]];
        return Helpers::onlysuccess($message);
    }
    // Get KYC Input Fields
    public function getKycInputFields() {
        $user = auth()->guard(get_auth_guard())->user();
        $user_kyc = SetupKyc::userKyc()->first();
        $kyc_data = $user_kyc->fields;
        $kyc_fields = array_reverse($kyc_data);

        $data = [
            'status_info' => '0: Unverified, 1: Verified, 2: Pending, 3: Rejected',
            'kyc_status' => $user->kyc_verified,
            'input_fields' => $kyc_fields
        ];
        $message = ['success' => [__('You are already KYC Verified User')]];
        if($user->kyc_verified == GlobalConst::VERIFIED) return Helpers::success($data,$message);
        $message = ['success' => [__('Your KYC information is submitted. Please wait for admin confirmation')]];
        if($user->kyc_verified == GlobalConst::PENDING) return Helpers::success($data,$message);
        $message = ['success' => [__('User KYC section is under maintenance')]];
        if(!$user_kyc) return Helpers::success($data,$message);
        $message = ['success' => [__('User KYC input fields fetch successfully!')]];
        return Helpers::success($data, $message);
    }
    public function KycSubmit(Request $request) {
        $user = auth()->guard(get_auth_guard())->user();
        if($user->kyc_verified == GlobalConst::VERIFIED) return Response::warning([__('You are already KYC Verified User')],[],400);

        $user_kyc_fields = SetupKyc::userKyc()->first()->fields ?? [];
        $validation_rules = $this->generateValidationRules($user_kyc_fields);

        $validated = Validator::make($request->all(),$validation_rules)->validate();
        $get_values = $this->placeValueWithFields($user_kyc_fields,$validated);

        $create = [
            'user_id'       => auth()->guard(get_auth_guard())->user()->id,
            'data'          => json_encode($get_values),
            'created_at'    => now(),
        ];

        DB::beginTransaction();
        try{
            DB::table('user_kyc_data')->updateOrInsert(["user_id" => $user->id],$create);
            $user->update([
                'kyc_verified'  => GlobalConst::PENDING,
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            $user->update([
                'kyc_verified'  => GlobalConst::DEFAULT,
            ]);
            $this->generatedFieldsFilesDelete($get_values);
            return Response::error([__('Something went wrong! Please try again')],[],500);
        }

        return Response::success([__('KYC information successfully submitted')],[],200);
    }
    //========================pusher beams registration================================
    public function pusherBeamsAuth(){
        $userID = request()->user_id ?? null;
        if(!$userID){
            $message = ['error'=>[__("Something went wrong! Please try again")]];
            return Helpers::error($message);
        }

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

        $instance_id    = $notification_config->instance_id ?? null;
        $primary_key    = $notification_config->primary_key ?? null;
        if($instance_id == null || $primary_key == null) {
            $message = ['error'=>[__("Sorry! You have to configure first to send push notification.")]];
            return Helpers::error($message);
        }
        $beamsClient = new PushNotifications(
            array(
                "instanceId" => $notification_config->instance_id,
                "secretKey" => $notification_config->primary_key,
            )
        );
        $publisherUserId =  make_user_id_for_pusher("user", $userID);

        try{
            $beamsToken = $beamsClient->generateToken($publisherUserId);
            return response()->json($beamsToken);
        }catch(Exception $e) {
            $message = ['error'=>[__("Server Error. Failed to generate beams token.")]];
            return Helpers::error($message);
        }

    }
//========================pusher beams registration================================

//========================Google 2FA ================================
    public function verify2FACode(Request $request) {
        $validator = Validator::make($request->all(), [
            'otp' => 'required',
        ]);

        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }

        $code = $request->otp;
        $user = authGuardApi()['user'];

        if(!$user->two_factor_secret) {
            $error = ['error'=>[__('Your secret key is not stored properly. Please contact with system administrator')]];
            return Helpers::error($error);
        }

        if(google_2fa_verify_api($user->two_factor_secret,$code)) {
            $user->update([
                'two_factor_verified'   => true,
            ]);
            $message = ['success'=>[ __("Two factor verified successfully")]];
            return Helpers::onlySuccess($message);
        }
        $message = ['error'=>[ __('Failed to login. Please try again')]];
        return Helpers::error($message);
    }
//========================Google 2FA ================================



}
