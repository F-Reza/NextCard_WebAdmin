<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Api\Helpers;
use Exception;
use Illuminate\Http\Request;
class SecurityController extends Controller
{
    public function google2FA(){

        $user = authGuardApi()['user'];
        $qr_code = generate_google_2fa_auth_qr();
        $qr_secrete = $user->two_factor_secret;
        $qr_status = $user->two_factor_status;

        $data = [
            'qr_code'    => $qr_code,
            'qr_secrete' => $qr_secrete,
            'qr_status'  => $qr_status,
            'alert' => __("Don't forget to add this application in your google authentication app. Otherwise you can't login in your account.",)
        ];

        $message = ['success'=>[ __('Data Fetch Successful')]];
        return Helpers::success( $data,$message);
    }


    public function google2FAStatusUpdate(Request $request){
        $user = authGuardApi()['user'];
        try{
            $user->update([
                'two_factor_status'         => $user->two_factor_status ? 0 : 1,
                'two_factor_verified'       => true,
            ]);
        }catch(Exception $e) {
           $error = ['error'=>[__('Something went wrong! Please try again')]];
           return Helpers::error($error);
        }
        $message = ['success'=>[__('Google 2FA Updated Successfully')]];
        return Helpers::onlysuccess($message);
    }
}
