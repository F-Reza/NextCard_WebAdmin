<?php
namespace App\Constants;

use App\Models\UserWallet;
use Illuminate\Support\Str;

class PaymentGatewayConst {

    const AUTOMATIC = "AUTOMATIC";
    const MANUAL    = "MANUAL";
    const ADDMONEY  = "Add Money";
    const MONEYOUT  = "Money Out";
    const ACTIVE    =  true;

    const ENV_SANDBOX       = "SANDBOX";
    const ENV_PRODUCTION    = "PRODUCTION";

    const FIAT                      = "FIAT";
    const CRYPTO                    = "CRYPTO";
    const CRYPTO_NATIVE             = "CRYPTO_NATIVE";
    const ASSET_TYPE_WALLET         = "WALLET";
    const CALLBACK_HANDLE_INTERNAL  = "CALLBACK_HANDLE_INTERNAL";

    const NOT_USED  = "NOT-USED";
    const USED      = "USED";
    const SENT      = "SENT";

    const TYPEADDMONEY      = "ADD-MONEY";
    const WITHDRAWMONEY     = "WITHDRAW-MONEY";
    const TYPEMONEYOUT      = "MONEY-OUT";
    const TYPECOMMISSION    = "COMMISSION";
    const TYPEBONUS         = "BONUS";
    const TYPETRANSFERMONEY = "TRANSFER-MONEY";
    const TYPEMONEYEXCHANGE = "MONEY-EXCHANGE";
    const BILLPAY = "BILL-PAY";
    const MOBILETOPUP = "MOBILE-TOPUP";
    const VIRTUALCARD = "VIRTUAL-CARD";
    const CARDBUY = "CARD-BUY";
    const CARDFUND = "CARD-FUND";
    const CARDWITHDRAW = "CARD-WITHDRAW";
    const GIFTCARD          = "GIFT-CARD";
    const TYPEADDSUBTRACTBALANCE = "ADD-SUBTRACT-BALANCE";

    const STATUSSUCCESS     = 1;
    const STATUSPENDING     = 2;
    const STATUSHOLD        = 3;
    const STATUSREJECTED    = 4;
    const STATUSWAITING     = 5;
    const STATUSFAILD       = 6;
    const STATUSPROCESSING  = 7;

    const PAYPAL                    = 'paypal';
    const FLUTTER_WAVE              = 'flutterwave';
    const RAZORPAY                  = 'razorpay';
    const SSLCOMMERZ                = 'sslcommerz';
    const COINGATE                  = 'coingate';
    const QRPAY                     = 'qrpay';
    const STRIPE                    = 'stripe';
    const MANUA_GATEWAY             = 'manual';
    const TATUM                     = 'tatum';
    const PERFECT_MONEY             = 'perfect-money';
    const PAGADITO                  = 'pagadito';
    const PAYSTACK                  = "paystack";


    const SEND = "SEND";
    const RECEIVED = "RECEIVED";

    public static function add_money_slug() {
        return Str::slug(self::ADDMONEY);
    }

    public static function money_out_slug() {
        return Str::slug(self::MONEYOUT);
    }

    const REDIRECT_USING_HTML_FORM = "REDIRECT_USING_HTML_FORM";

    public static function register($alias = null) {
        $gateway_alias  = [
            self::PAYPAL        => "paypalInit",
            self::STRIPE        => "stripeInit",
            self::FLUTTER_WAVE  => 'flutterwaveInit',
            self::RAZORPAY      => 'razorInit',
            self::MANUA_GATEWAY => "manualInit",
            self::SSLCOMMERZ    => 'sslcommerzInit',
            self::QRPAY         => 'qrpayInit',
            self::COINGATE  => 'coingateInit',
            self::TATUM         => 'tatumInit',
            self::PERFECT_MONEY => 'perfectMoneyInit',
            self::PAGADITO      => 'pagaditoInit',
            self::PAYSTACK      => 'paystackInit'
        ];

        if($alias == null) {
            return $gateway_alias;
        }

        if(array_key_exists($alias,$gateway_alias)) {
            return $gateway_alias[$alias];
        }
        return "init";
    }
    const APP       = "APP";
    public static function apiAuthenticateGuard() {
            return [
                'api'   => 'web',
            ];
    }
    public static function registerWallet() {
        return [
            'web'       => UserWallet::class,
            'api'       => UserWallet::class,
        ];
    }
    public static function registerGatewayRecognization() {
        return [
            'isCoinGate'        => self::COINGATE,
            'isTatum'           => self::TATUM,
            'isPerfectMoney'    => self::PERFECT_MONEY,
            'isRazorpay'        => self::RAZORPAY,
            'isPagadito'        => self::PAGADITO,
            'isPayStack'        => self::PAYSTACK,
        ];
    }

    public static function registerRedirection() {
        return [
            'web'       => [
                'return_url'    => 'user.add.money.payment.global.success',
                'cancel_url'    => 'user.add.money.payment.global.cancel',
                'callback_url'  => 'user.add.money.payment.callback',
                'redirect_form' => 'user.add.money.payment.redirect.form',
                'btn_pay'       => 'user.add.money.payment.btn.pay',
            ],
            'api'       => [
                'return_url'    => 'api.user.add.money.payment.global.success',
                'cancel_url'    => 'api.user.add.money.payment.global.cancel',
                'callback_url'  => 'user.add.money.payment.callback',
                'redirect_form' => 'user.add.money.payment.redirect.form',
                'btn_pay'       => 'api.user.add.money.payment.btn.pay',
            ],
        ];
    }

}
