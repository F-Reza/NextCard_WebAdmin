<?php

namespace App\Http\Controllers;

use App\Models\Admin\Currency;
use App\Models\Admin\ExchangeRate;
use App\Models\UserWallet;
use Illuminate\Http\Request;
use Jenssegers\Agent\Facades\Agent;
use Carbon\Carbon;


class GlobalController extends Controller
{

    /**
     * Funtion for get state under a country
     * @param country_id
     * @return json $state list
     */
    public function getStates(Request $request) {
        $request->validate([
            'country_id' => 'required|integer',
        ]);
        $country_id = $request->country_id;
        // Get All States From Country
        $country_states = get_country_states($country_id);
        return response()->json($country_states,200);
    }


    public function getCities(Request $request) {
        $request->validate([
            'state_id' => 'required|integer',
        ]);

        $state_id = $request->state_id;
        $state_cities = get_state_cities($state_id);

        return response()->json($state_cities,200);
        // return $state_id;
    }


    public function getCountries(Request $request) {
        $countries = get_all_countries();

        return response()->json($countries,200);
    }


    public function getTimezones(Request $request) {
        $timeZones = get_all_timezones();

        return response()->json($timeZones,200);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function setCookie(Request $request){
        $userAgent = $request->header('User-Agent');
        $cookie_status = $request->type;
        if($cookie_status == 'allow'){
            $response_message = __("Cookie Allowed Success");
            $expirationTime = 2147483647; //Maximum Unix timestamp.
        }else{
            $response_message = __("Cookie Declined");
            $expirationTime = Carbon::now()->addHours(24)->timestamp;// Set the expiration time to 24 hours from now.
        }
        $browser = Agent::browser();
        $platform = Agent::platform();
        $ipAddress = $request->ip();
        // Set the expiration time to a very distant future
        return response($response_message)->cookie('approval_status', $cookie_status,$expirationTime)
                                            ->cookie('user_agent', $userAgent,$expirationTime)
                                            ->cookie('ip_address', $ipAddress,$expirationTime)
                                            ->cookie('browser', $browser,$expirationTime)
                                            ->cookie('platform', $platform,$expirationTime);

    }

    // ajax call for get user available balance by currency
    public function userWalletBalance(Request $request){
        $user_wallets = UserWallet::where(['user_id' => auth()->user()->id, 'currency_id' => $request->id])->first();
        return $user_wallets->balance;
    }
    public function receiverWallet(Request $request){
        $receiver_currency = ExchangeRate::where(['currency_code' => $request->code])->first();
        return $receiver_currency;
    }
}
