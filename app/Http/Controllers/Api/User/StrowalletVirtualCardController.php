<?php

namespace App\Http\Controllers\Api\User;

use App\Constants\GlobalConst;
use Exception;
use App\Models\UserWallet;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\Admin\Currency;
use App\Models\VirtualCardApi;
use App\Models\UserNotification;
use App\Http\Helpers\Api\Helpers;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use App\Constants\NotificationConst;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\StrowalletVirtualCard;
use App\Constants\PaymentGatewayConst;
use App\Http\Helpers\NotificationHelper;
use App\Http\Helpers\PushNotificationHelper;
use App\Models\Admin\AdminNotification;
use App\Models\Admin\TransactionSetting;
use App\Models\StrowalletCustomerKyc;
use App\Models\StrowalletWebhookData;
use App\Notifications\Admin\ActivityNotification;
use App\Notifications\User\VirtualCard\CreateMail;
use App\Notifications\User\VirtualCard\Fund;
use App\Providers\Admin\BasicSettingsProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class  StrowalletVirtualCardController extends Controller
{
    protected $api;
    protected $card_limit;
    protected $basic_settings;
    public function __construct()
    {
        $cardApi = VirtualCardApi::first();
        $this->api =  $cardApi;
        $this->card_limit =  $cardApi->card_limit;
        $this->basic_settings = BasicSettingsProvider::get();
    }
    public function index()
    {
        $user = auth()->user();
        $basic_settings = BasicSettings::first();
        $card_basic_info = [
            'card_create_limit' => @$this->api->card_limit,
            'card_back_details' => @$this->api->card_details,
            'card_bg'           => get_image(@$this->api->image,'card-api'),
            'site_title'        => @$basic_settings->site_name,
            'site_logo'         => get_logo(@$basic_settings,'dark'),
            'site_fav'          => get_fav($basic_settings,'dark'),
        ];
        $myCards = StrowalletVirtualCard::where('user_id',$user->id)->latest()->limit($this->card_limit)->get()->map(function($data){
            $live_card_data = card_details($data->card_id,$this->api->config->strowallet_public_key,$this->api->config->strowallet_url);
            $basic_settings = BasicSettings::first();
            $statusInfo = [
                "block" =>      0,
                "unblock" =>     1,
                ];
            return[
                'id'                => $data->id,
                'name'              => $data->name_on_card,
                'card_number'       => $data->card_number ?? '',
                'card_id'           => $data->card_id,
                'expiry'            => $data->expiry ?? '',
                'cvv'               => $data->cvv ?? '',
                'balance'           => getAmount(updateStroWalletCardBalance(auth()->user(),$data->card_id,$live_card_data),2),
                'card_status'       => $data->card_status,
                'card_back_details' => @$this->api->card_details,
                'site_title'        => @$basic_settings->site_name,
                'site_logo'         => get_logo(@$basic_settings,'dark'),
                'site_fav'          => get_fav($basic_settings,'dark'),
                'status'            => $data->is_active,
                'is_default'        => $data->is_default,
                'status_info'       => (object)$statusInfo,
            ];
        });
        $cardCharge = TransactionSetting::where('slug','virtual_card')->where('status',1)->get()->map(function($data){

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
        $transactions = Transaction::auth()->virtualCard()->latest()->get()->map(function($item){
            $statusInfo = [
                "success" =>      1,
                "pending" =>      2,
                "rejected" =>     3,
                ];

            return[
                'id' => $item->id,
                'trx' => $item->trx_id,
                'transactin_type' => "Virtual Card".'('. @$item->remark.')',
                'request_amount' => getAmount($item->request_amount,2).' '.get_default_currency_code() ,
                'payable' => getAmount($item->payable,2).' '.get_default_currency_code(),
                'total_charge' => getAmount($item->charge->total_charge,2).' '.get_default_currency_code(),
                'card_amount' => getAmount(@$item->details->card_info->balance,2).' '.get_default_currency_code(),
                'card_number' => $item->details->card_info->card_pan??$item->details->card_info->maskedPan??$item->details->card_info->card_number??'',
                'current_balance' => getAmount($item->available_balance,2).' '.get_default_currency_code(),
                'status' => $item->stringStatus->value ,
                'date_time' => $item->created_at ,
                'status_info' =>(object)$statusInfo ,

            ];
        });
        $userWallet = UserWallet::where('user_id',$user->id)->get()->map(function($data){
            return[
                'balance' => getAmount($data->balance,2),
                'currency' => get_default_currency_code(),
            ];
        })->first();
        $customer_email = $user->strowallet_customer->customerEmail??false;
        if($customer_email === false){
            $customer_card  = 0;
        }else{
            $customer_card  = StrowalletVirtualCard::where('customer_email',$customer_email)->count();
        }
        $data =[
            'base_curr' => get_default_currency_code(),
            'card_create_action' => $customer_card <  $this->card_limit ? true : false,
            'strowallet_customer_info' =>$user->strowallet_customer === null ? true : false,
            'card_basic_info' =>(object) $card_basic_info,
            'myCards'=> $myCards,
            'user'=>   $user,
            'userWallet'=>  (object)$userWallet,
            'cardCharge'=>(object)$cardCharge,
            'transactions'   => $transactions,
        ];
        $message =  ['success'=>[__('Virtual Card')]];
        return Helpers::success($data,$message);
    }
    //charge
    public function charges(){
        $cardCharge = TransactionSetting::where('slug','virtual_card')->where('status',1)->get()->map(function($data){
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

        $data =[
            'base_curr' => get_default_currency_code(),
            'cardCharge'=>(object)$cardCharge
            ];
            $message =  ['success'=>[__('Fess & Charges')]];
            return Helpers::success($data,$message);

    }
    //card details
    public function cardDetails(){
        $validator = Validator::make(request()->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $card_id = request()->card_id;
        $user = auth()->user();
        $myCard = StrowalletVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        if(!$myCard){
            $error = ['error'=>[__("Something is wrong in your card")]];
            return Helpers::error($error);
        }


        if($myCard->card_status == 'pending'){
            $card_details   = card_details($card_id,$this->api->config->strowallet_public_key,$this->api->config->strowallet_url);

            if($card_details['status'] == false){
                $error = ['error'=>[__("Your Card Is Pending! Please Contact With Admin")]];
                return Helpers::error($error);
            }

            $myCard->user_id                   = Auth::user()->id;
            $myCard->card_status               = $card_details['data']['card_detail']['card_status'];
            $myCard->card_number               = $card_details['data']['card_detail']['card_number'];
            $myCard->last4                     = $card_details['data']['card_detail']['last4'];
            $myCard->cvv                       = $card_details['data']['card_detail']['cvv'];
            $myCard->expiry                    = $card_details['data']['card_detail']['expiry'];
            $myCard->save();
        }

        $myCards = StrowalletVirtualCard::where('card_id',$card_id)->where('user_id',$user->id)->get()->map(function($data){
            $basic_settings = BasicSettings::first();
            return[
                'id'                => $data->id,
                'name'              => $data->name_on_card,
                'card_id'           => $data->card_id,
                'card_brand'        => $data->card_brand,
                'card_user_id'      => $data->card_user_id,
                'expiry'            => $data->expiry,
                'cvv'               => $data->cvv,
                'card_type'         => ucwords($data->card_type),
                'city'              => $data->user->strowallet_customer->city??"",
                'state'             => $data->user->strowallet_customer->state??"",
                'zip_code'          => $data->user->strowallet_customer->zipCode??"",
                'amount'            => getAmount($data->balance,2),
                'card_back_details' => @$this->api->card_details,
                'card_bg'           => get_image(@$this->api->image,'card-api'),
                'site_title'        => @$basic_settings->site_name,
                'site_logo'         => get_logo(@$basic_settings,'dark'),
                'status'            => $data->is_active,
                'is_default'        => $data->is_default,
            ];
        })->first();

        $data =[
            'base_curr' => get_default_currency_code(),
            'myCards'=> $myCards,
            ];
            $message =  ['success'=>[__('card Details')]];
            return Helpers::success($data,$message);
    }

    public function makeDefaultOrRemove(Request $request) {
        $validator = Validator::make($request->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $validated = $validator->validate();
        $user = auth()->user();
        $targetCard =  StrowalletVirtualCard::where('card_id',$validated['card_id'])->where('user_id',$user->id)->first();
        if(!$targetCard){
            $error = ['error'=>[__("Something is wrong in your card")]];
            return Helpers::error($error);
        };
        $withOutTargetCards =  StrowalletVirtualCard::where('id','!=',$targetCard->id)->where('user_id',$user->id)->get();
        try{
            $targetCard->update([
                'is_default'         => $targetCard->is_default ? 0 : 1,
            ]);
            if(isset(  $withOutTargetCards)){
                foreach(  $withOutTargetCards as $card){
                    $card->is_default = false;
                    $card->save();
                }
            }
            $message =  ['success'=>[__('Status Updated Successfully')]];
            return Helpers::onlysuccess($message);

        }catch(Exception $e) {
            $error = ['error'=>[__("Something went wrong! Please try again.")]];
            return Helpers::error($error);
        }
    }
    // card transactions
    public function cardTransaction() {
        $validator = Validator::make(request()->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $card_id = request()->card_id;
        $user = auth()->user();
        $card = StrowalletVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        if(!$card){
            $error = ['error'=>[__("Something is wrong in your card")]];
            return Helpers::error($error);
        }

        $curl = curl_init();
        $public_key     = $this->api->config->strowallet_public_key;
        $base_url       = $this->api->config->strowallet_url;

        curl_setopt_array($curl, [
        CURLOPT_URL => $base_url . "card-transactions/",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode([
            'public_key' => $public_key,
            'card_id' => $card->card_id,
        ]),
        CURLOPT_HTTPHEADER => [
            "accept: application/json",
            "content-type: application/json"
        ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);
        $result  = json_decode($response, true);
        if(isset($result['success']) && $result['success'] === true){
            $data = $result['response'];
        }else{
            $data = [];
        }

        $message = ['success' => [__('Virtual Card Transaction')]];
        return Helpers::success($data,$message);
    }
    //card block
    public function cardBlock(Request $request){
        $validator = Validator::make($request->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $card_id = $request->card_id;
        $user = auth()->user();
        $status = 'freeze';
        $card = StrowalletVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        if(!$card){
            $error = ['error'=>[__("Something is wrong in your card")]];
            return Helpers::error($error);
        }
        if($card->is_active == false){
            $error = ['error'=>[__('Sorry,This Card Is Already Freeze')]];
            return Helpers::error($error);
        }

        $client = new \GuzzleHttp\Client();
        $public_key     = $this->api->config->strowallet_public_key;
        $base_url       = $this->api->config->strowallet_url;

        $response = $client->request('POST', $base_url.'action/status/?action='.$status.'&card_id='.$card->card_id.'&public_key='.$public_key, [
        'headers' => [
            'accept' => 'application/json',
        ],
        ]);

        $result = $response->getBody();
        $data  = json_decode($result, true);

        if (isset($data)) {
            if ($data['status'] == 'true') {
                $card->is_active = 0;
                $card->save();
                $message =  ['success'=>[__('Card Freeze successfully')]];
                return Helpers::onlysuccess($message);
            }
        }

    }
    //unblock card
    public function cardUnBlock(Request $request){
        $validator  = Validator::make($request->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            $error  =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $card_id    = $request->card_id;
        $user       = auth()->user();
        $status     = 'unfreeze';
        $card       = StrowalletVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        if(!$card){
            $error  = ['error'=>[__("Something is wrong in your card")]];
            return Helpers::error($error);
        }
        if($card->is_active == true){
            $error = ['error'=>[__('Sorry,This Card Is Already Unfreeze')]];
            return Helpers::error($error);
        }
        $client         = new \GuzzleHttp\Client();
        $public_key     = $this->api->config->strowallet_public_key;
        $base_url       = $this->api->config->strowallet_url;

        $response = $client->request('POST', $base_url.'action/status/?action='.$status.'&card_id='.$card->card_id.'&public_key='.$public_key, [
        'headers' => [
            'accept' => 'application/json',
        ],
        ]);

        $result = $response->getBody();
        $data  = json_decode($result, true);

        if (isset($data['status'])) {
            $card->is_active = 1;
            $card->save();
            $message =  ['success'=>[__('Card UnFreeze successfully')]];
            return Helpers::onlysuccess($message);
        }else{
            $error = ['error' => $data['message']];
            return Helpers::error(['error' => [$data['message']]]);
        }

    }

    public function createPage(){
        $user       = authGuardApi()['user'];
        $customer_exist_status  = $user->strowallet_customer != null ? true : false;
        $customer_create_fields =  [
            [
                'id'    => 1,
                'field_name' => "first_name",
                'label_name' => __("first Name"),
                'site_label' => __("Should match with your ID"),
                'type'       => 'text'
            ],
            [
                'id'    => 2,
                'field_name' => "last_name",
                'label_name' => __("last Name"),
                'site_label' => __("Should match with your ID"),
                'type'       => 'text'
            ],

            [
                'id'    => 3,
                'field_name' => "phone_code",
                'label_name' => __("Phone Code"),
                'site_label' => "",
                'type'       => 'number'
            ],
            [
                'id'    => 4,
                'field_name' => "phone",
                'label_name' => __("phone"),
                'site_label' => "",
                'type'       => 'number'
            ],
            [
                'id'    => 5,
                'field_name' => "customer_email",
                'label_name' => __("Email"),
                'site_label' => "",
                'type'       => 'email'
            ],

            [
                'id'    => 6,
                'field_name' => "date_of_birth",
                'label_name' => __("Date Of Birth"),
                'site_label' =>__("Should match with your ID"),
                'type'       => 'date'
            ],
            [
                'id'    => 7,
                'field_name' => "house_number",
                'label_name' => __("House Number"),
                'site_label' => "",
                'type'       => 'text'
            ],
            [
                'id'    => 8,
                'field_name' => "address",
                'label_name' => __("address"),
                'site_label' => "",
                'type'       => 'text'
            ],
            [
                'id'    => 9,
                'field_name' => "zip_code",
                'label_name' => __("Zip Code"),
                'site_label' => "",
                'type'       => 'text'
            ],
            [
                'id'    => 10,
                'field_name' => "id_image_font",
                'label_name' => __("ID Card Image (Font Side)"),
                'site_label' => __("NID/Passport"),
                'type'       => 'file'
            ],
            [
                'id'    => 11,
                'field_name' => "user_image",
                'label_name' => __("Your Photo"),
                'site_label' => __("Should show your face and must be match with your ID"),
                'type'       => 'file'
            ],
        ];
        if($user->strowallet_customer){
            $customer_exist =  $user->strowallet_customer;
        }else{
            $customer_exist =[
                "customerEmail"     => "",
                "firstName"         => "",
                "lastName"          => "",
                "phoneNumber"       => "",
                "city"              => "",
                "state"             => "",
                "country"           => "",
                "line1"             => "",
                "zipCode"           => "",
                "houseNumber"       => "",
                "idNumber"          => "",
                "idType"            => "",
                "idImage"           => "",
                "userPhoto"         => "",
                "customerId"        => "",
                "dateOfBirth"       => "",
                "status"            => ""
            ];
            $customer_exist = (object) $customer_exist;
        }

        $customer_kyc_status_can_be    = [
            'low kyc',
            'unreview kyc',
            'high kyc',
        ];
        $customer_kyc_status    = $customer_exist->status ?? "";
        $customer_low_kyc_text  = __("Thank you for submitting your KYC information. Your details are currently under review. We will notify you once the verification is complete. Please note that the creation of your virtual card will proceed after your KYC is approved.");
        $card_create_fields =  [
            [
                'id'    => 1,
                'field_name' => "name_on_card",
                'label_name' => __("Card Holder's Name"),
                'site_label' => "",
                'type'       => 'text'
            ],
            [
                'id'    => 2,
                'field_name' => "card_amount",
                'label_name' =>__("Amount"),
                'site_label' => "",
                'type'       => 'number'
            ],
            [
                'id'    => 3,
                'field_name' => "currency",
                'label_name' =>__("Select Currency"),
                'site_label' => "",
                'type'       => 'select'
            ],
        ];
        $data =[
            'customer_exist_status'     => $customer_exist_status,
            'customer_create_fields'    => (array)$customer_create_fields,
            'customer_exist'            => $customer_exist,
            'customer_kyc_status_can_be'=> $customer_kyc_status_can_be,
            'customer_kyc_status'       => $customer_kyc_status,
            'customer_low_kyc_text'     => $customer_low_kyc_text,
            'card_create_fields'        => $card_create_fields,
        ];
        $message =  ['success'=>[__('Data Fetch Successful')]];
        return Helpers::success($data,$message);

    }
    public function updateCustomerStatus(){
        $user       = authGuardApi()['user'];
        if($user->strowallet_customer != null){
            //get customer api response
            $customer = $user->strowallet_customer;
            $customerEmail = $customer->customerEmail??"";
            $customerId = $customer->customerId??"";

            $getCustomerInfo = get_customer($this->api->config->strowallet_public_key,$this->api->config->strowallet_url,$customerId,$customerEmail);
            if( $getCustomerInfo['status'] == false){
                $error  = ['error'=>[$getCustomerInfo['message'] ?? __("Something went wrong! Please try again.")]];
                return Helpers::error($error);
            }
            $customer               = (array) $customer;
            $customer_status_info   =  $getCustomerInfo['data'];

            foreach ($customer_status_info as $key => $value) {
                $customer[$key] = $value;
            }
            $user->strowallet_customer = (object) $customer;
            $user->save();
        }

        $message =  ['success'=>[__('Customer Status Updated Successfully.')]];
        return Helpers::onlysuccess($message);

    }
    public function createCustomer(Request $request){
        $validator = Validator::make($request->all(), [
            'first_name'        => ['required', 'string', 'regex:/^[^0-9\W]+$/'], // First name validation
            'last_name'         => ['required', 'string', 'regex:/^[^0-9\W]+$/'],  // Last name validation
            'customer_email'    => 'required|email',
            'date_of_birth'     => 'required|string',
            'house_number'      => 'required|string',
            'address'           => 'required|string',
            'zip_code'          => 'required|string',
            'id_image_font'     => "required|image|mimes:jpg,png,svg,webp",
            'user_image'        => "required|image|mimes:jpg,png,svg,webp",
        ], [
            'first_name.regex'  => __('The First Name field should only contain letters and cannot start with a number or special character.'),
            'last_name.regex'   => __('The Last Name field should only contain letters and cannot start with a number or special character.'),
        ]);

        if ($validator->fails()) {
            $error  =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $validated = $validator->validate();
        $user       = authGuardApi()['user'];
        $validated['phone'] = $user->full_mobile;
        try{
            if($user->strowallet_customer == null){

                if($request->hasFile("id_image_font")) {
                    $image = upload_file($validated['id_image_font'],'card-kyc-images');
                    $upload_image = upload_files_from_path_dynamic([$image['dev_path']],'card-kyc-images');
                    delete_file($image['dev_path']);
                    $validated['id_image_font']     = $upload_image;
                }

                //user image
                if($request->hasFile("user_image")) {
                    $image = upload_file($validated['user_image'],'card-kyc-images');
                    $upload_image = upload_files_from_path_dynamic([$image['dev_path']],'card-kyc-images');
                    delete_file($image['dev_path']);
                    $validated['user_image']     = $upload_image;
                }
                $exist_kyc = StrowalletCustomerKyc::where('user_id',$user->id)->first();
                if($exist_kyc){
                    $exist_kyc->update([
                        'user_id'         =>  $user->id,
                        'face_image'      =>  $validated['user_image'],
                        'id_image'        =>  $validated['id_image_font']
                    ]);
                    $kyc_info = StrowalletCustomerKyc::where('user_id',$user->id)->first();
                }else{
                    //store kyc images
                    $kyc_info = StrowalletCustomerKyc::create([
                        'user_id'         =>  $user->id,
                        'face_image'      =>  $validated['user_image'],
                        'id_image'        =>  $validated['id_image_font']
                    ]);
                }

                $idImage = $kyc_info->idImageData;
                $userPhoto = $kyc_info->faceImageData;

                $validated = Arr::except($validated,['id_image_font','user_image']);
                $createCustomer     = stro_wallet_create_user($validated,$this->api->config->strowallet_public_key,$this->api->config->strowallet_url,$idImage,$userPhoto);
                if( $createCustomer['status'] == false){
                    $kyc_info->delete();
                    return $this->apiErrorHandle($createCustomer["message"]);
                }
                $user->strowallet_customer =   (object)$createCustomer['data'];
                $user->save();
            }

            $message =  ['success'=>[__('Customer has been created successfully.')]];
            return Helpers::onlysuccess($message);
        }catch(Exception $e){
            $error  = ['error'=>[__("Something went wrong! Please try again.")]];
            return Helpers::error($error);
        }


    }
    public function updateCustomer(Request $request){
        $validator = Validator::make($request->all(), [
            'first_name'        => ['required', 'string', 'regex:/^[^0-9\W]+$/'], // First name validation
            'last_name'         => ['required', 'string', 'regex:/^[^0-9\W]+$/'],  // Last name validation
            'id_image_font'     => "nullable|image|mimes:jpg,png,svg,webp",
            'user_image'        => "nullable|image|mimes:jpg,png,svg,webp",
        ], [
            'first_name.regex'  => __('The First Name field should only contain letters and cannot start with a number or special character.'),
            'last_name.regex'   => __('The Last Name field should only contain letters and cannot start with a number or special character.'),
        ]);

        if ($validator->fails()) {
            $error  =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $validated = $validator->validate();
        $user      = authGuardApi()['user'];
        try{
            if($user->strowallet_customer != null){
                $customer_kyc = StrowalletCustomerKyc::where('user_id',$user->id)->first();
                if($request->hasFile("id_image_font")) {
                    $id_image = upload_file($validated['id_image_font'],'card-kyc-images',);
                    $upload_image = upload_files_from_path_dynamic([$id_image['dev_path']],'card-kyc-images',$customer_kyc->id_image??null);
                    // delete_file($id_image['dev_path']);
                    $validated['id_image_font']     = $upload_image;
                }

                //user image
                if($request->hasFile("user_image")) {
                    $user_image = upload_file($validated['user_image'],'card-kyc-images',$customer_kyc->face_image??null);
                    $upload_image = upload_files_from_path_dynamic([$user_image['dev_path']],'card-kyc-images');
                    // delete_file($user_image['dev_path']);
                    $validated['user_image']     = $upload_image;
                }

                 //store kyc images
                 if( $customer_kyc){
                    $customer_kyc->update([
                        'user_id'         =>  $user->id,
                        'id_image'        =>  $validated['id_image_font'] ?? $customer_kyc->id_image,
                        'face_image'      =>  $validated['user_image'] ??$customer_kyc->face_image
                    ]);
                }else{
                    $customer_kyc = StrowalletCustomerKyc::create([
                        'user_id'         =>  $user->id,
                        'id_image'        =>  $validated['id_image_font'],
                        'face_image'      =>  $validated['user_image']
                    ]);
                }

                $idImage = $customer_kyc->idImageData;
                $userPhoto = $customer_kyc->faceImageData;

                $validated = Arr::except($validated,['id_image_font','user_image']);
                $updateCustomer     = update_customer($validated,$this->api->config->strowallet_public_key,$this->api->config->strowallet_url,$idImage,$userPhoto,$user->strowallet_customer);
                if ($updateCustomer['status'] == false) {
                    $customer_kyc->delete();
                    return $this->apiErrorHandle($updateCustomer["message"]);

                }

                 //get customer api response
                $customer = $user->strowallet_customer;
                $getCustomerInfo = get_customer($this->api->config->strowallet_public_key,$this->api->config->strowallet_url,$updateCustomer['data']['customerId']??"",$updateCustomer['data']['customerEmail']??"");
                if( $getCustomerInfo['status'] == false){
                    $customer_kyc->delete();
                    $error  = ['error'=>[$getCustomerInfo['message'] ?? __("Something went wrong! Please try again.")]];
                    return Helpers::error($error);
                }
                $customer               = (array) $customer;
                $customer_status_info   =  $getCustomerInfo['data'];

                foreach ($customer_status_info as $key => $value) {
                    $customer[$key] = $value;
                }
                $user->strowallet_customer = (object) $customer;
                $user->save();

            }else{
                $error  = ['error'=>[__("Something went wrong! Please try again.")]];
                return Helpers::error($error);
            }
            $message =  ['success'=>[__('Customer has been updated successfully.')]];
            return Helpers::onlysuccess($message);

        }catch(Exception $e){
            $error  = ['error'=>[__("Something went wrong! Please try again.")]];
            return Helpers::error($error);
        }

    }

    //card buy
    public function cardBuy(Request $request){
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'card_amount'       => 'required|numeric|gt:0',
            'name_on_card'      => 'required|string|min:4|max:50',
        ]);

        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $formData   = $request->all();


        $amount = $request->card_amount;
        $basic_setting = BasicSettings::first();
        $wallet = UserWallet::where('user_id',$user->id)->first();
        if(!$wallet){
            $error = ['error'=>[__('User wallet not found')]];
            return Helpers::error($error);
        }
        $cardCharge = TransactionSetting::where('slug','virtual_card')->where('status',1)->first();
        $baseCurrency = Currency::default();
        $rate = $baseCurrency->rate;
        if(!$baseCurrency){
            $error = ['error'=>[__('Default currency not found')]];
            return Helpers::error($error);
        }
        $minLimit =  $cardCharge->min_limit *  $rate;
        $maxLimit =  $cardCharge->max_limit *  $rate;
        if($amount < $minLimit || $amount > $maxLimit) {
            $error = ['error'=>[__("Please follow the transaction limit")]];
            return Helpers::error($error);
        }
        //charge calculations
        $fixedCharge = $cardCharge->fixed_charge *  $rate;
        $percent_charge = ($amount / 100) * $cardCharge->percent_charge;
        $total_charge = $fixedCharge + $percent_charge;
        $payable = $total_charge + $amount;
        if($payable > $wallet->balance ){
            $error = ['error'=>[__('Sorry, insufficient balance')]];
            return Helpers::error($error);
        }


        $customer = $user->strowallet_customer;
        if(!$customer){
            $error = ['error'=>[__("Something went wrong! Please try again.")]];
            return Helpers::error($error);
        }
        if($customer->status != GlobalConst::CARD_HIGH_KYC_STATUS){
            $error = ['error'=>[__("Your virtual card will proceed after your KYC is approved")]];
            return Helpers::error($error);
        }

        $customer_email = $user->strowallet_customer->customerEmail??false;
        if($customer_email === false){
            $customer_card  = 0;
        }else{
            $customer_card  = StrowalletVirtualCard::where('customer_email',$customer_email)->count();
        }

        if($customer_card >= $this->card_limit){
            $error = ['error'=>[__("Sorry! You can not create more than")." ".$this->card_limit ." ".__("card using the same email address.")]];
            return Helpers::error($error);
        }


        // for live code
        $created_card = create_strowallet_virtual_card($user,$request->card_amount,$customer,$this->api->config->strowallet_public_key,$this->api->config->strowallet_url,$formData);
        if($created_card['status'] == false){
            $error = ['error'=>[$created_card['message'] .' ,'.__('Please Contact With Administration.')]];
            return Helpers::error($error);
        }


        $strowallet_card                            = new StrowalletVirtualCard();
        $strowallet_card->user_id                   = $user->id;
        $strowallet_card->name_on_card              = $created_card['data']['name_on_card'];
        $strowallet_card->card_id                   = $created_card['data']['card_id'];
        $strowallet_card->card_created_date         = $created_card['data']['card_created_date'];
        $strowallet_card->card_type                 = $created_card['data']['card_type'];
        $strowallet_card->card_brand                = "visa";
        $strowallet_card->card_user_id              = $created_card['data']['card_user_id'];
        $strowallet_card->reference                 = $created_card['data']['reference'];
        $strowallet_card->card_status               = $created_card['data']['card_status'];
        $strowallet_card->customer_id               = $created_card['data']['customer_id'];
        $strowallet_card->customer_email            = $customer->customerEmail;
        $strowallet_card->balance                   = $amount;
        $strowallet_card->save();


        $trx_id =  'CB'.getTrxNum();
        try{
            $sender = $this->insertCardBuy( $trx_id,$user,$wallet,$amount, $strowallet_card ,$payable);
            $this->insertBuyCardCharge( $fixedCharge,$percent_charge, $total_charge,$user,$sender,$strowallet_card->card_number);
            if($basic_setting->email_notification == true){
                $notifyDataSender = [
                    'trx_id'  => $trx_id,
                    'title'  => "Virtual Card (Buy Card)",
                    'request_amount'  => getAmount($amount,4).' '.get_default_currency_code(),
                    'payable'   =>  getAmount($payable,4).' ' .get_default_currency_code(),
                    'charges'   => getAmount( $total_charge, 2).' ' .get_default_currency_code(),
                    'card_amount'  => getAmount($amount, 2).' ' .get_default_currency_code(),
                    'card_pan'  =>  "---- ----- ---- ----",
                    'status'  =>  $strowallet_card->card_status??"",
                    ];
                try{
                    $user->notify(new CreateMail($user,(object)$notifyDataSender));
                }catch(Exception $e){}
            }
             //admin notification
             $this->adminNotification($trx_id,$total_charge,$amount,$payable,$user,$strowallet_card);
            $message =  ['success'=>[__('Virtual Card Buy Successfully')]];
            return Helpers::onlysuccess($message);
        }catch(Exception $e){

            $error =  ['error'=>[__("Something went wrong! Please try again.")]];
            return Helpers::error($error);
        }

    }
    public function insertCardBuy( $trx_id,$user,$wallet,$amount, $strowallet_card ,$payable) {
        $trx_id = $trx_id;
        $authWallet = $wallet;
        $afterCharge = ($authWallet->balance - $payable);
        $details =[
            'card_info' =>   $strowallet_card??''
        ];
        DB::beginTransaction();
        try{
            $id = DB::table("transactions")->insertGetId([
                'user_id'                       => $user->id,
                'user_wallet_id'                => $authWallet->id,
                'payment_gateway_currency_id'   => null,
                'type'                          => PaymentGatewayConst::VIRTUALCARD,
                'trx_id'                        => $trx_id,
                'request_amount'                => $amount,
                'payable'                       => $payable,
                'available_balance'             => $afterCharge,
                'remark'                        => ucwords(remove_speacial_char(PaymentGatewayConst::CARDBUY," ")),
                'details'                       => json_encode($details),
                'attribute'                      =>PaymentGatewayConst::RECEIVED,
                'status'                        => true,
                'created_at'                    => now(),
            ]);
            $this->updateSenderWalletBalance($authWallet,$afterCharge);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__("Something went wrong! Please try again."));
        }
        return $id;
    }
    public function insertBuyCardCharge($fixedCharge,$percent_charge, $total_charge,$user,$id,$card_number) {
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    => $percent_charge,
                'fixed_charge'      => $fixedCharge,
                'total_charge'      => $total_charge,
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
            $notification_content = [
                'title'         =>__('buy Card'),
                'message'       => __('Buy card successful')." ".$card_number,
                'image'           => files_asset_path('profile-default'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::CARD_BUY,
                'user_id'   => $user->id,
                'message'   => $notification_content,
            ]);

            //Push Notifications
            if( $this->basic_settings->push_notification == true){
                try{
                    (new PushNotificationHelper())->prepare([$user->id],[
                        'title' => $notification_content['title'],
                        'desc'  => $notification_content['message'],
                        'user_type' => 'user',
                    ])->send();
                }catch(Exception $e) {}
            }
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__("Something went wrong! Please try again."));
        }
    }
    //update user balance
    public function updateSenderWalletBalance($authWallet,$afterCharge) {
        $authWallet->update([
            'balance'   => $afterCharge,
        ]);
    }
    /**
     * Card Fund
     */
    public function cardFundConfirm(Request $request){
        $validator = Validator::make($request->all(), [
            'card_id' => 'required',
            'fund_amount' => 'required|numeric|gt:0',
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $user = auth()->user();
        $myCard =  StrowalletVirtualCard::where('user_id',$user->id)->where('card_id',$request->card_id)->first();

        if(!$myCard){
            $error = ['error'=>[__("Something is wrong in your card")]];
            return Helpers::error($error);
        }

        $amount = $request->fund_amount;
        $wallet = UserWallet::where('user_id',$user->id)->first();
        if(!$wallet){
            $error = ['error'=>[__('User wallet not found')]];
            return Helpers::error($error);
        }
        $cardCharge = TransactionSetting::where('slug','reload_card')->where('status',1)->first();
        $baseCurrency = Currency::default();
        $rate = $baseCurrency->rate;
        if(!$baseCurrency){
            $error = ['error'=>[__('Default currency not found')]];
            return Helpers::error($error);
        }
        $minLimit =  $cardCharge->min_limit *  $rate;
        $maxLimit =  $cardCharge->max_limit *  $rate;
        if($amount < $minLimit || $amount > $maxLimit) {
            $error = ['error'=>[__("Please follow the transaction limit")]];
            return Helpers::error($error);
        }
        $fixedCharge = $cardCharge->fixed_charge *  $rate;
        $percent_charge = ($amount / 100) * $cardCharge->percent_charge;
        $total_charge = $fixedCharge + $percent_charge;
        $payable = $total_charge + $amount;
        if($payable > $wallet->balance ){
            $error = ['error'=>[__('Sorry, insufficient balance')]];
            return Helpers::error($error);
        }

        $public_key     = $this->api->config->strowallet_public_key;
        $base_url       = $this->api->config->strowallet_url;
        $mode           = $this->api->config->strowallet_mode??GlobalConst::SANDBOX;
        $form_params    = [
            'card_id'       => $myCard->card_id,
            'amount'        => $amount,
            'public_key'    => $public_key
        ];
        if ($mode === GlobalConst::SANDBOX) {
            $form_params['mode'] = "sandbox";
        }

        $client = new \GuzzleHttp\Client();

        $response               = $client->request('POST', $base_url.'fund-card/', [
            'headers'           => [
                'accept'        => 'application/json',
            ],
            'form_params'       => $form_params,
        ]);

        $result         = $response->getBody();
        $decodedResult  = json_decode($result, true);

        if(!empty($decodedResult['success'])  && $decodedResult['success'] === true){
            //added fund amount to card
            $myCard->balance += $amount;
            $myCard->save();
            $trx_id = 'CF'.getTrxNum();
            $sender = $this->insertCardFund( $trx_id,$user,$wallet,$amount, $myCard ,$payable);
            $this->insertFundCardCharge( $fixedCharge,$percent_charge, $total_charge,$user,$sender,$myCard->card_number,$amount);
            if($this->basic_settings->email_notification == true){
                $notifyDataSender = [
                    'trx_id'  => $trx_id,
                    'title'  => "Virtual Card (Fund Amount)",
                    'request_amount'  => getAmount($amount,4).' '.get_default_currency_code(),
                    'payable'   =>  getAmount($payable,4).' ' .get_default_currency_code(),
                    'charges'   => getAmount( $total_charge,2).' ' .get_default_currency_code(),
                    'card_amount'  => getAmount($myCard->balance,2).' ' .get_default_currency_code(),
                    'card_pan'  =>    $myCard->card_number??"---- ----- ---- ----",
                    'status'  => "Success",
                ];
                try{
                    $user->notify(new Fund($user,(object)$notifyDataSender));
                }catch(Exception $e){}
            }
            //admin notification
            $this->adminNotificationFund($trx_id,$total_charge,$amount,$payable,$user,$myCard);
            $message =  ['success'=>[__('Card Funded Successfully')]];
            return Helpers::onlysuccess($message);

        }else{

            $error = ['error'=>[@$decodedResult['message'].' ,'.__('Please Contact With Administration.')]];
            return Helpers::error($error);
        }

    }
    //card fund helper
    public function insertCardFund( $trx_id,$user,$wallet,$amount, $myCard ,$payable) {
        $trx_id = $trx_id;
        $authWallet = $wallet;
        $afterCharge = ($authWallet->balance - $payable);
        $details =[
            'card_info' =>   $myCard??''
        ];
        DB::beginTransaction();
        try{
            $id = DB::table("transactions")->insertGetId([
                'user_id'                       => $user->id,
                'user_wallet_id'                => $authWallet->id,
                'payment_gateway_currency_id'   => null,
                'type'                          => PaymentGatewayConst::VIRTUALCARD,
                'trx_id'                        => $trx_id,
                'request_amount'                => $amount,
                'payable'                       => $payable,
                'available_balance'             => $afterCharge,
                'remark'                        => ucwords(remove_speacial_char(PaymentGatewayConst::CARDFUND," ")),
                'details'                       => json_encode($details),
                'attribute'                      =>PaymentGatewayConst::RECEIVED,
                'status'                        => true,
                'created_at'                    => now(),
            ]);
            $this->updateSenderWalletBalance($authWallet,$afterCharge);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__("Something went wrong! Please try again."));
        }
        return $id;
    }
    public function insertFundCardCharge($fixedCharge,$percent_charge, $total_charge,$user,$id,$card_number,$amount) {
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    => $percent_charge,
                'fixed_charge'      =>$fixedCharge,
                'total_charge'      =>$total_charge,
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
            $notification_content = [
                'title'         =>"Card Fund",
                'message'       => __("Card fund successful card")." : ".$card_number.' '.getAmount($amount,2).' '.get_default_currency_code(),
                'image'         => files_asset_path('profile-default'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::CARD_FUND,
                'user_id'  => $user->id,
                'message'   => $notification_content,
            ]);


            //Push Notifications
            if( $this->basic_settings->push_notification == true){
                try{
                    (new PushNotificationHelper())->prepare([$user->id],[
                        'title' => $notification_content['title'],
                        'desc'  => $notification_content['message'],
                        'user_type' => 'user',
                    ])->send();
                }catch(Exception $e) {dd($e);}
            }
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__("Something went wrong! Please try again."));
        }
    }
    //admin notification
    public function adminNotification($trx_id,$total_charge,$amount,$payable,$user,$v_card){
        $notification_content = [
            //email notification
            'subject' => __("Virtual Card (Buy Card)"),
            'greeting' => __("Virtual Card Information"),
            'email_content' =>__("TRX ID")." : ".$trx_id."<br>".__("Request Amount")." : ".get_amount($amount,get_default_currency_code())."<br>".__("Fees & Charges")." : ".get_amount($total_charge,get_default_currency_code())."<br>".__("Total Payable Amount")." : ".get_amount($payable,get_default_currency_code())."<br>".__("card Masked")." : ".$v_card->card_number??"---- ----- ---- ----"."<br>".__("Status")." : ".__("Success"),

            //push notification
            'push_title' => __("Virtual Card (Buy Card)")." (".userGuard()['type'].")",
            'push_content' => __('TRX ID')." : ".$trx_id." ".__("Request Amount")." : ".get_amount($amount,get_default_currency_code())." ".__("card Masked")." : ".$v_card->card_number??"---- ----- ---- ----",

            //admin db notification
            'notification_type' =>  NotificationConst::CARD_BUY,
            'admin_db_title' => "Virtual Card Buy"." (".userGuard()['type'].")",
            'admin_db_message' => "Transaction ID"." : ".$trx_id.",".__("Request Amount")." : ".get_amount($amount,get_default_currency_code()).","."Card Masked"." : ".$v_card->card_number??"---- ----- ---- ----"." (".$user->email.")",
        ];

        try{
            //notification
            (new NotificationHelper())->admin(['admin.virtual.card.logs'])
                                    ->mail(ActivityNotification::class, [
                                        'subject'   => $notification_content['subject'],
                                        'greeting'  => $notification_content['greeting'],
                                        'content'   => $notification_content['email_content'],
                                    ])
                                    ->push([
                                        'user_type' => "admin",
                                        'title' => $notification_content['push_title'],
                                        'desc'  => $notification_content['push_content'],
                                    ])
                                    ->adminDbContent([
                                        'type' => $notification_content['notification_type'],
                                        'title' => $notification_content['admin_db_title'],
                                        'message'  => $notification_content['admin_db_message'],
                                    ])
                                    ->send();


        }catch(Exception $e) {}

    }
    public function adminNotificationFund($trx_id,$total_charge,$amount,$payable,$user,$myCard){
        $notification_content = [
            //email notification
            'subject' => __("Virtual Card (Fund Amount)"),
            'greeting' => __("Virtual Card Information"),
            'email_content' =>__("TRX ID")." : ".$trx_id."<br>".__("Request Amount")." : ".get_amount($amount,get_default_currency_code())."<br>".__("Fees & Charges")." : ".get_amount($total_charge,get_default_currency_code())."<br>".__("Total Payable Amount")." : ".get_amount($payable,get_default_currency_code())."<br>".__("card Masked")." : ".$myCard->masked_card??"---- ----- ---- ----"."<br>".__("Status")." : ".__("Success"),

            //push notification
            'push_title' => __("Virtual Card (Fund Amount)")." (".userGuard()['type'].")",
            'push_content' => __('TRX ID')." : ".$trx_id." ".__("Request Amount")." : ".get_amount($amount,get_default_currency_code())." ".__("card Masked")." : ".$myCard->masked_card??"---- ----- ---- ----",

            //admin db notification
            'notification_type' =>  NotificationConst::CARD_FUND,
            'admin_db_title' => "Virtual Card Funded"." (".userGuard()['type'].")",
            'admin_db_message' => "Transaction ID"." : ".$trx_id.",".__("Request Amount")." : ".get_amount($amount,get_default_currency_code()).","."Card Masked"." : ".$myCard->card_number??"---- ----- ---- ----"." (".$user->email.")",
        ];

        try{
            //notification
            (new NotificationHelper())->admin(['admin.virtual.card.logs'])
                                    ->mail(ActivityNotification::class, [
                                        'subject'   => $notification_content['subject'],
                                        'greeting'  => $notification_content['greeting'],
                                        'content'   => $notification_content['email_content'],
                                    ])
                                    ->push([
                                        'user_type' => "admin",
                                        'title' => $notification_content['push_title'],
                                        'desc'  => $notification_content['push_content'],
                                    ])
                                    ->adminDbContent([
                                        'type' => $notification_content['notification_type'],
                                        'title' => $notification_content['admin_db_title'],
                                        'message'  => $notification_content['admin_db_message'],
                                    ])
                                    ->send();


        }catch(Exception $e) {}

    }
    public function apiErrorHandle($apiErrors){
        $error = ['error' => []];
        if (isset($apiErrors)) {
            if (is_array($apiErrors)) {
                foreach ($apiErrors as $field => $messages) {
                    if (is_array($messages)) {
                        foreach ($messages as $message) {
                            $error['error'][] = $message;
                        }
                    } else {
                        $error['error'][] = $messages;
                    }
                }
            } else {
                $error['error'][] = $apiErrors;
            }
        }

        $errorMessages = array_map(function($message) {
            return rtrim($message, '.');
        }, $error['error']);

        $errorString = implode(', ', $errorMessages);
        $errorString .= '.';

        $error = ['error' => [$errorString ?? __("Something went wrong! Please try again.")]];
        return Helpers::error($error);

    }
    public function webhookTransaction(){
        $validator = Validator::make(request()->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $card_id = request()->card_id;
        $user = authGuardApi()['user'];
        $myCard = StrowalletVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        if(!$myCard){
            $error = ['error'=>[__("Something is wrong in your card")]];
            return Helpers::error($error);
        }
        $logs = StrowalletWebhookData::where('user_id',$user->id)->where('cardId',$card_id)->latest()->get()->map(function($item){
           if($item->event === global_const()::TERMINATED){
                return[
                    'event_type'                => $item->event,
                    'event'                     => str_replace(' ', ' ', ucwords(str_replace('.', ' ', $item->event))),
                    'amount'                    => get_amount($item->data->amount ?? 0,$item->card_currency),
                    'transition_id'             => $item->transaction_id ?? "",
                    'card_id'                   => $item->cardId ?? "",
                    'reference'                 => $item->data->reference ?? "",
                    'status'                    => $item->data->status ?? "",
                    'balance_before_termination'  => get_amount($item->data->balanceBeforeTermination ?? 0,$item->card_currency)
                ];
           }elseif($item->event === global_const()::CROSSBORDER){
                return[
                    'event_type'                => $item->event,
                    'event'                     => str_replace(' ', ' ', ucwords(str_replace('.', ' ', $item->event))),
                    'amount'                    => get_amount($item->data->amount ?? 0,$item->card_currency),
                    'transition_id'             => $item->transaction_id ?? "",
                    'card_id'                   => $item->cardId ?? "",
                    'reference'                 => $item->data->reference ?? "",
                    'charge_amount'             => get_amount($item->data->chargedAmount ?? 0,$item->card_currency),
                    'narrative'                 => $item->data->narrative ?? "",
                    'status'                    => $item->data->status ?? "",
                    'created_at'                => $item->data->createdAt ?? "",
                ];
           }elseif($item->event === global_const()::DECLINED){
                return[
                    'event_type'                => $item->event,
                    'event'                     => str_replace(' ', ' ', ucwords(str_replace('.', ' ', $item->event))),
                    'amount'                    => get_amount($item->data->amount ?? 0,$item->card_currency),
                    'transition_id'             => $item->transaction_id ?? "",
                    'card_id'                   => $item->cardId ?? "",
                    'reference'                 => $item->data->reference ?? "",
                    'narrative'                 => $item->data->narrative ?? "",
                    'reason'                    => $item->data->reason ?? "",
                    'status'                    => $item->data->status ?? "",
                    'created_at'                => $item->data->date ?? "",
                ];
           }

        });
        $data =[
            'transactions'=> $logs,
        ];
        $message =  ['success'=>[__('Webhook Logs')]];
        return Helpers::success($data,$message);

    }

}
