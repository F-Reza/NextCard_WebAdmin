<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Constants\GlobalConst;
use App\Http\Helpers\Api\Helpers;
use App\Providers\Admin\BasicSettingsProvider;

class ApiKycVerificationGuard
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $basic_settings = BasicSettingsProvider::get();
        $kyc_verification_status = $basic_settings->kyc_verification;

        if($kyc_verification_status) {
            $user = auth()->user();
            if($user->kyc_verified === GlobalConst::DEFAULT) {
                $error = ['error'=>[__('Please submit kyc information!')]];
                return Helpers::error($error);
            }else if($user->kyc_verified == GlobalConst::PENDING) {
                $error = ['error'=>[__('Please wait before admin approved your kyc information')]];
                return Helpers::error($error);
            }elseif($user->kyc_verified == GlobalConst::REJECTED){
                $error = ['error'=>[__('Admin rejected your kyc information, Please re-submit again')]];
                return Helpers::error($error);
            }
        }
        return $next($request);
    }
}
