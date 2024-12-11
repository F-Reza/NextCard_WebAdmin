<?php

use App\Constants\GlobalConst;
use App\Models\StrowalletVirtualCard;
use GuzzleHttp\Client;
use App\Models\VirtualCardApi;


function stro_wallet_create_user($formData,$public_key,$base_url,$idImage,$userPhoto){
    $client = new Client();

    $response               = $client->request('POST', $base_url.'create-user/', [
        'headers'           => [
            'accept'        => 'application/json',
        ],
        'form_params'       => [
            'public_key'    => $public_key,
            'houseNumber'   => $formData['house_number'],
            'firstName'     => $formData['first_name'],
            'lastName'      => $formData['last_name'],

            'idNumber'      => rand(123456789,987654321),
            'customerEmail' => $formData['customer_email'],
            'phoneNumber'   => $formData['phone'],
            'dateOfBirth'   => $formData['date_of_birth'],
            'idImage'       => $idImage??"",
            'userPhoto'     => $userPhoto??"",
            'line1'         => $formData['address'],
            'state'         => 'Accra',
            'zipCode'       => $formData['zip_code'],
            'city'          => 'Accra',
            'country'       => 'Ghana',
            'idType'        => 'PASSPORT',
        ],
    ]);

    $result         = $response->getBody();
    $decodedResult  = json_decode($result, true);


    if(isset($decodedResult['success']) && $decodedResult['success'] == true ){
        $data =[
            'status'        => true,
            'message'       => "Create Customer Successfully.",
            'data'          => $decodedResult['response'],
        ];
    }else{
        $data =[
            'status'        => false,
            'message'       => $decodedResult['message'] ?? 'Something is wrong! Contact With Admin',
            'data'          => null,
        ];
    }

    return $data;

}
//get customer api response
function get_customer($public_key,$base_url,$customerId,$customerEmail){

    $url = $base_url . 'getcardholder/?public_key=' . urlencode($public_key) . '&customerId=' . urlencode($customerId) . '&customerEmail=' . urlencode($customerEmail);
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($curl);
    $result = json_decode($response,true);

    if(isset($result) && isset($result['success']) && $result['success'] == true){
        $data =[
            'status' => true,
            'message' => "Customer Get SuccessFully",
            'data' => $result['data'] ?? [],
        ];
    }else{
        $data =[
            'status' => false,
            'message' => $result['message']??__("Something went wrong! Please try again."),
            'data' => [],
        ];
    }
    return $data;

    // Close cURL session
    curl_close($curl);

}
//update customer api
function update_customer($formData,$public_key,$base_url,$idImage,$userPhoto,$customer){

    $ch = curl_init();
    // Set the URL
    $url = $base_url . 'updateCardCustomer/?public_key=' . urlencode($public_key) . '&customerId=' . urlencode($customer->customerId) .'&firstName=' .urlencode($formData['first_name']) . '&lastName=' . urlencode($formData['last_name']) . '&idImage=' . urlencode($idImage) . '&userPhoto=' . urlencode($userPhoto);

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return response instead of outputting
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT'); // Set request method to PUT
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json' // Set headers
    ]);

    // Execute cURL request
    $response = curl_exec($ch);
    $result     = json_decode($response,true);

    if(isset($result) && isset($result['success']) && $result['success'] == true){
        $data =[
            'status' => true,
            'message' => "Customer updated successfully",
            'data' => $result['response'] ?? [],
        ];
    }else{
        $data =[
            'status' => false,
            'message' => $result['message']??__("Something went wrong! Please try again."),
            'data' => [],
        ];
    }
    return $data;

    curl_close($ch);


}
// create virtual card for strowallet
function create_strowallet_virtual_card($user,$cardAmount,$customer,$public_key,$base_url,$formData){

    $method = VirtualCardApi::first();
    $mode = $method->config->strowallet_mode??GlobalConst::SANDBOX;
    $data = [
        'name_on_card'  => $formData['name_on_card'] ?? $user->username,
        'card_type'     => 'visa',
        'public_key'    => $public_key,
        'amount'        => $cardAmount,
        'customerEmail' => $customer->customerEmail,
    ];

    if ($mode === GlobalConst::SANDBOX) {
        $data['mode'] = "sandbox";
    }
    $data['developer_code'] = 'appdevsx';

    $curl = curl_init();

    curl_setopt_array($curl, [
    CURLOPT_URL => $base_url."create-card/",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => [
        "accept: application/json",
        "content-type: application/json"
    ],
    ]);
    $response = curl_exec($curl);

    curl_close($curl);
    $result  = json_decode($response, true);


    if(isset($result['success']) && $result['success'] == true ){
        $data =[
            'status'        => true,
            'message'       => "Create Card Successfully.",
            'data'          => $result['response'],
        ];
    }elseif(isset($result) && isset($result['error'])){
        $data =[
            'status'        => false,
            'message'       => "Contact With Strowallet Account Administration, ".$result['error']??"",
            'data'          => null,
        ];

    }else{
        $data =[
            'status'        => false,
            'message'       => "Contact With Strowallet Account Administration, ".$result['message']??"",
            'data'          => null,
        ];
    }

    return $data;
}
// card details
function card_details($card_id,$public_key,$base_url){
    $method = VirtualCardApi::first();
    $mode = $method->config->strowallet_mode??GlobalConst::SANDBOX;
    $data =[
        'public_key'    => $public_key,
        'card_id'       => $card_id
    ];

    if ($mode === GlobalConst::SANDBOX) {
        $data['mode'] = "sandbox";
    }

    $curl = curl_init();

    curl_setopt_array($curl, [
    CURLOPT_URL => $base_url . "fetch-card-detail/",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => [
        "accept: application/json",
        "content-type: application/json"
    ],
    ]);

    $response = curl_exec($curl);

    curl_close($curl);

    $result  = json_decode($response, true);

    if(isset($result['success']) && $result['success'] == true ){
        $data =[
            'status'        => true,
            'message'       => "Card Details Retrieved Successfully.",
            'data'          => $result['response'],
        ];
    }else{
        $data =[
            'status'        => false,
            'message'       => $result['message'] ?? 'Your Card Is Pending!Please Contact With Admin',
            'data'          => null,
        ];
    }

    return $data;
}
function strowalletBalance(){
    $currency_code = get_default_currency_code();
    $method = VirtualCardApi::first();
    $publicKey =  $method->config->strowallet_public_key;
    $url = 'https://strowallet.com/api/wallet/balance/'.$currency_code.'/?public_key='  . $publicKey;

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json'
    ));

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode(  $response,true);
    if(isset($result['balance']) ){
        $data =[
            'status'        => true,
            'message'       => __("SuccessFully Fetch Account Balance"),
            'balance'          => $result['balance'],
        ];
    }else{
        $data =[
            'status'        => false,
            'message'       => $result['message']??'',
            'balance'          => 0
        ];
    }
    return $data;
}
function updateStroWalletCardBalance($user,$card_id,$response){
    $card = StrowalletVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
    $card->balance = $response['data']['card_detail']['balance']??$card->balance;
    $card->save();
    return  $card->balance??0;
}
