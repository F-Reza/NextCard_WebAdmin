<?php

namespace App\Providers;

use App\Constants\GlobalConst;
use App\Constants\LanguageConst;
use Exception;
use App\Models\User;
use App\Models\Admin\Currency;
use App\Models\Admin\Language;
use App\Models\VirtualCardApi;
use App\Models\Admin\Extension;
use App\Models\UserSupportTicket;
use App\Models\Admin\BasicSettings;
use App\Models\Admin\ModuleSetting;
use App\Models\Admin\SystemMaintenance;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use App\Models\Admin\TransactionSetting;
use App\Providers\Admin\CurrencyProvider;
use App\Providers\Admin\BasicSettingsProvider;

class CustomServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->startingPoint();
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        try{
            $default_language = Language::where('status',GlobalConst::ACTIVE)->first();
            $default_language_code = $default_language->code ?? LanguageConst::NOT_REMOVABLE;

            $view_share = [];
            $view_share['basic_settings']               = BasicSettings::first();
            $view_share['card_details']                 = VirtualCardApi::first();
            $view_share['default_currency']             = Currency::default();
            $view_share['__languages']                  = Language::get();
            $view_share['all_user_count']               = User::count();
            $view_share['email_verified_user_count']    = User::where('email_verified', 1)->count();
            $view_share['kyc_verified_user_count']      = User::where('kyc_verified', 1)->count();
            $view_share['default_currency']             = Currency::default();
            $view_share['__extensions']                 = Extension::get();
            $view_share['pending_ticket_count']         = UserSupportTicket::pending()->get()->count();
            $view_share['cardCharge']                   = TransactionSetting::where('slug','virtual_card')->where('status',1)->first();
            $view_share['cardReloadCharge']             = TransactionSetting::where('slug','reload_card')->where('status',1)->first();
            $view_share['card_limit']                   = VirtualCardApi::first()->card_limit;
            $view_share['card_api']                     = VirtualCardApi::first();
            $view_share['module']                       = ModuleSetting::get();
            $view_share['system_maintenance']           = SystemMaintenance::first();
            $view_share['default_language_code']        = $default_language_code;

            view()->share($view_share);

            $this->app->bind(BasicSettingsProvider::class, function () use ($view_share) {
                return new BasicSettingsProvider($view_share['basic_settings']);
            });
            $this->app->bind(CurrencyProvider::class, function () use ($view_share) {
                return new CurrencyProvider($view_share['default_currency']);
            });
        }catch(Exception $e) {
            //
        }
    }

    public function startingPoint() {
        if(env('PURCHASE_CODE','') == null) {
            Config::set('starting-point.status',true);
            Config::set('starting-point.point','/project/install/welcome');
        }
    }
}
