<?php

use App\Http\Controllers\Api\AppSettingsController;
use Illuminate\Support\Str;
use App\Models\Admin\SetupPage;
use App\Http\Helpers\Api\Helpers;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Api\User\UserController;
use App\Http\Controllers\Api\User\AddMoneyController;
use App\Http\Controllers\Api\User\Auth\LoginController;
use App\Http\Controllers\Api\User\AuthorizationController;
use App\Http\Controllers\Api\User\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\User\GiftCardController;
use App\Http\Controllers\Api\User\SecurityController;
use App\Http\Controllers\Api\User\StripeVirtualController;
use App\Http\Controllers\Api\User\StrowalletVirtualCardController;
use App\Http\Controllers\Api\User\SudoVirtualCardController;
use App\Http\Controllers\Api\User\TransferMoneyController;
use App\Http\Controllers\Api\User\VirtualCardController;
use App\Http\Controllers\Api\User\WithdrawController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::get('clear-cache', function() {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    $message =  ['success'=>['Clear cache successfully']];
    return Helpers::onlysuccess($message);
});
Route::get('useful-links', function() {
    $type = Str::slug(App\Constants\GlobalConst::USEFUL_LINKS);
    $policies =SetupPage::orderBy('id',"ASC")->where('type', $type)->where('status',1)->get()->map(function($link){
        return[
            'id' => $link->id,
            'slug' => $link->slug,
            'link' =>route('useful.link',$link->slug),
        ];
    });
    $data =[
        'about' =>  route('about'),
        'contact' =>  route('contact'),
        'policy_pages' =>  $policies,
    ];
    $message =  ['success'=>['Useful Links']];
    return Helpers::success($data,$message);
});
Route::controller(AppSettingsController::class)->prefix("app-settings")->group(function(){
    Route::get('/','appSettings');
    Route::get('languages','languages')->withoutMiddleware(['system.maintenance.api']);
});
Route::controller(AddMoneyController::class)->prefix("add-money")->group(function(){
    Route::get('success/response/paypal/{gateway}','success')->name('api.payment.success');
    Route::get("cancel/response/paypal/{gateway}",'cancel')->name('api.payment.cancel');
    Route::get('stripe/payment/success/{trx}','stripePaymentSuccess')->name('api.stripe.payment.success');
    Route::get('/flutterwave/callback', 'flutterwaveCallback')->name('api.flutterwave.callback');
     //QRPay
     Route::get('qrpay/success', 'qrPaySuccess')->name('api.qrpay.success');
     Route::get('qrpay/cancel/{trx}', 'qrPayCancel')->name('api.qrpay.cancel');
     //coingate
    Route::match(['get','post'],'coingate/success/response/{gateway}','coinGateSuccess')->name('api.coingate.payment.success');
    Route::match(['get','post'],"coingate/cancel/response/{gateway}",'coinGateCancel')->name('api.coingate.payment.cancel');

    //Perfect Money
    Route::get('perfect/success/response/{gateway}','perfectSuccess')->name('api.add-money.perfect.success');
    Route::get("perfect/cancel/response/{gateway}",'perfectCancel')->name('api.add-money.perfect.cancel');
});


Route::prefix('user')->group(function(){
    Route::post('login',[LoginController::class,'login']);
    Route::post('register',[LoginController::class,'register']);
    //forget password
    Route::post('forget/password', [ForgotPasswordController::class,'sendCode']);
    Route::post('forget/verify/code', [ForgotPasswordController::class,'verifyCode']);
    Route::post('forget/reset/password', [ForgotPasswordController::class,'resetPassword']);

    Route::middleware(['auth.api'])->group(function(){
        Route::get('logout', [LoginController::class,'logout']);
        Route::get('pusher/beams-auth',[AuthorizationController::class,'pusherBeamsAuth'])->withoutMiddleware(['api','auth.api']);
        //email verifications
        Route::post('send-code', [AuthorizationController::class,'sendMailCode']);
        Route::post('email-verify', [AuthorizationController::class,'mailVerify']);
        Route::post('google-2fa/otp/verify', [AuthorizationController::class,'verify2FACode']);

        Route::middleware(['CheckStatusApiUser','user.google.two.factor.api'])->group(function () {
            Route::get('dashboard', [UserController::class,'home']);
            Route::get('profile', [UserController::class,'profile']);
            Route::post('profile/update', [UserController::class,'profileUpdate'])->middleware('app.mode.api');
            Route::post('password/update', [UserController::class,'passwordUpdate'])->middleware('app.mode.api');
            Route::post('delete/account', [UserController::class,'deleteAccount'])->middleware('app.mode.api');

            //virtual card stripe
            Route::middleware('virtual_card_method:stripe')->group(function(){
                Route::controller(StripeVirtualController::class)->prefix('my-card/stripe')->group(function(){
                    Route::get('/','index');
                    Route::get('details','cardDetails');
                    Route::post('create','cardBuy')->middleware(['api.kyc.verification.guard']);
                    Route::get('transaction','cardTransaction');
                    Route::post('inactive','cardInactive');
                    Route::post('active','cardActive');
                    Route::post('get/sensitive/data','getSensitiveData');
                    Route::post('make-remove/default','makeDefaultOrRemove');
                });
            });
             //virtual card sudo
             Route::middleware('virtual_card_method:sudo')->group(function(){
                Route::controller(SudoVirtualCardController::class)->prefix('my-card/sudo')->group(function(){
                    Route::get('/','index');
                    Route::get('charges','charges');
                    Route::get('details','cardDetails');
                    Route::post('create','cardBuy')->middleware(['api.kyc.verification.guard']);
                    Route::post('fund','cardFundConfirm')->middleware(['api.kyc.verification.guard']);
                    Route::get('details','cardDetails');
                    Route::get('transaction','cardTransaction');
                    Route::post('block','cardBlock');
                    Route::post('unblock','cardUnBlock');
                    Route::post('make-remove/default','makeDefaultOrRemove');
                });
            });
            //virtual card flutterwave
            Route::middleware('virtual_card_method:flutterwave')->group(function(){
                Route::controller(VirtualCardController::class)->prefix('my-card')->group(function(){
                    Route::get('/','index');
                    Route::get('charges','charges');
                    Route::post('create','cardBuy')->middleware('api.kyc.verification.guard');
                    Route::post('fund','cardFundConfirm')->middleware(['api.kyc.verification.guard']);
                    Route::post('withdraw','cardWithdraw');
                    Route::get('details','cardDetails');
                    Route::get('transaction','cardTransaction');
                    Route::post('block','cardBlock');
                    Route::post('unblock','cardUnBlock');
                    Route::post('make-remove/default','makeDefaultOrRemove');
                });
            });
            //strowallet virtual card
            Route::middleware('virtual_card_method:strowallet')->group(function(){
                Route::controller(StrowalletVirtualCardController::class)->prefix('strowallet-card')->group(function(){
                    Route::get('/','index');
                    Route::get('charges','charges');

                    Route::get('create/info','createPage')->middleware('api.kyc.verification.guard');
                    Route::get('update/customer/status','updateCustomerStatus')->middleware('api.kyc.verification.guard');
                    Route::post('create/customer','createCustomer')->middleware('api.kyc.verification.guard');
                    Route::post('update/customer','updateCustomer')->middleware('api.kyc.verification.guard');

                    Route::post('create','cardBuy')->middleware('api.kyc.verification.guard');
                    Route::post('fund','cardFundConfirm')->middleware('api.kyc.verification.guard');
                    Route::get('details','cardDetails');
                    Route::get('transaction','cardTransaction');
                    Route::post('block','cardBlock');
                    Route::post('unblock','cardUnBlock')->name('block');
                    Route::post('make-remove/default','makeDefaultOrRemove');
                    Route::get('webhook','webhookTransaction');
                });
            });
            //Transfer Money
             Route::controller(TransferMoneyController::class)->prefix('transfer-money')->group(function(){
                Route::get('info','transferMoneyInfo');
                Route::post('exist','checkUser');
                Route::post('confirmed','confirmedTransferMoney')->middleware(['api.kyc.verification.guard']);
            });
            //Withdraw Money
            Route::controller(WithdrawController::class)->prefix('withdraw')->group(function(){
                Route::get('info','withdrawInfo');
                Route::post('insert','withdrawInsert')->middleware(['api.kyc.verification.guard']);
                Route::post('manual/confirmed','withdrawConfirmed')->name('api.withdraw.manual.confirmed')->middleware(['api.kyc.verification.guard']);
            });

            Route::controller(AuthorizationController::class)->prefix('kyc')->group(function(){
                Route::get('input-fields','getKycInputFields');
                Route::post('submit','KycSubmit');
            });

            Route::get('transactions', [UserController::class,'transactions']);
             //add money
            Route::controller(AddMoneyController::class)->prefix("add-money")->group(function(){
                Route::get('/information','addMoneyInformation');
                Route::post('submit-data','submitData');
                //manual gateway
                Route::post('manual/payment/confirmed','manualPaymentConfirmedApi')->name('api.manual.payment.confirmed');

                Route::prefix('payment')->name('api.user.add.money.payment.')->group(function() {
                    Route::post('crypto/confirm/{trx_id}','cryptoPaymentConfirm')->name('crypto.confirm');
                });
                //redirect with Btn Pay
                Route::get('redirect/btn/checkout/{gateway}', 'redirectBtnPay')->name('api.user.add.money.payment.btn.pay')->withoutMiddleware(['auth:api','auth.api','CheckStatusApiUser','verification.guard.api','user.google.two.factor.api']);

                // Global Gateway Response Routes
                Route::get('success/response/{gateway}','successGlobal')->withoutMiddleware(['auth:api','auth.api','CheckStatusApiUser','verification.guard.api','user.google.two.factor.api'])->name("api.user.add.money.payment.global.success");
                Route::get("cancel/response/{gateway}",'cancelGlobal')->withoutMiddleware(['auth:api','auth.api','CheckStatusApiUser','verification.guard.api','user.google.two.factor.api'])->name("api.user.add.money.payment.global.cancel");

                // POST Route For Unauthenticated Request
                Route::post('success/response/{gateway}', 'postSuccess')->name('api.user.add.money.payment.global.success')->withoutMiddleware(['auth:api','auth.api','CheckStatusApiUser','verification.guard.api','user.google.two.factor.api']);
                Route::post('cancel/response/{gateway}', 'postCancel')->name('api.user.add.money.payment.global.cancel')->withoutMiddleware(['auth:api','auth.api','CheckStatusApiUser','verification.guard.api','user.google.two.factor.api']);

                Route::get('paystack/callback/response','paystackCallback')->name('api.user.add.money.payment.callback.response')->withoutMiddleware(['auth:api','auth.api','CheckStatusApiUser','verification.guard.api','user.google.two.factor.api']);
            });
            //gift card
            Route::controller(GiftCardController::class)->prefix('gift-card')->group(function(){
                Route::get('/', 'index');
                Route::get('all', 'allGiftCard');
                Route::get('search/', 'searchGiftCard');
                Route::get('details', 'giftCardDetails');
                Route::post('order', 'orderPlace')->middleware('kyc.verification.guard');
            });
            //google-2fa
            Route::controller(SecurityController::class)->prefix("security")->group(function(){
                Route::get('/google-2fa', 'google2FA');
                Route::post('/google-2fa/status/update', 'google2FAStatusUpdate')->middleware('app.mode.api');

            });

        });

    });

});
