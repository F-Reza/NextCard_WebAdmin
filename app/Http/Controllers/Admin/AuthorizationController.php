<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthorizationController extends Controller
{
    public function showGoogle2FAForm()
    {
        $page_title =  __("admin.page.google.2fa.verification");
        return view('admin.auth.authorize.verify-google-2fa',compact('page_title'));
    }


    public function google2FASubmit(Request $request) {

        $request->validate([
            'code'    => "required|string",
        ]);
        $code = $request->code;

        $user = auth()->user();

        if(!$user->two_factor_secret) {
            return back()->with(['warning' => [__("Your secret key is not stored properly. Please contact with system administrator")]]);
        }

        if(google_2fa_verify($user->two_factor_secret,$code)) {
            $user->update([
                'two_factor_verified'   => true,
            ]);
            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->with(['error' => [__("Invalid code. Please try again")]]);
    }
}
