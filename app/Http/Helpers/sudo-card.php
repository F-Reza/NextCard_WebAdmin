<?php

//sudo virtual card system

use App\Models\SudoVirtualCard;
use App\Models\VirtualCardApi;

function funding_source_create($api_key,$base_url){
    $url = $base_url.'/fundingsources';
    $data = ['type' => 'default', 'status' => 'active'];

    $headers = [
        "Authorization: Bearer ".$api_key,
        "accept: application/json",
        'Content-Type: application/json',
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response,true);

    if(isset($result['statusCode'])){
        if($result['statusCode'] == 200){
            $data =[
                'status' => true,
                'message' =>" Successfully Create Founding Source",
                'data' => $result['data'],
           ];

        }else{
            $data =[
                'status' => false,
                'message' =>$result['message']??'',
                'data' => [],
           ];
        }

    }
    return  $data;
}
function get_funding_source($api_key,$base_url){
    $curl = curl_init();
    curl_setopt_array($curl, [
    CURLOPT_URL => $base_url."/fundingsources",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer ".$api_key,
        "accept: application/json"
    ],
    ]);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    if ($err) {
        $result = json_decode( $response,true);
        return  $result;
    } else {
        $result = json_decode( $response,true);
        return  $result;
    }
}
function create_sudo_account($api_key,$base_url, $currency){

    $curl = curl_init();
    curl_setopt_array($curl, [
    CURLOPT_URL => $base_url."/accounts",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => json_encode([
        'type' => 'account',
        'currency' => $currency,
        'accountType' => 'Current'
    ]),
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer ".$api_key,
        "accept: application/json",
        "content-type: application/json"
    ],
    ]);

    $response = curl_exec($curl);

    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        $result = json_decode( $response,true);
        return  $result;
    } else {
        $result = json_decode( $response,true);
        return  $result['data']??[];
    }
}
function get_sudo_accounts($api_key,$base_url){
    $curl = curl_init();
    curl_setopt_array($curl, [
    CURLOPT_URL => $base_url."/accounts?type=account",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer ".$api_key,
        "accept: application/json"
    ],
    ]);
    $response = curl_exec($curl);
    curl_close($curl);
    $result = json_decode($response, true);
    if (isset($result['statusCode']) && $result['statusCode'] != 200) {
        $result = json_decode( $response,true);
        return  $result;
    }elseif(isset($result) && isset($result['statusCode']) && $result['statusCode'] == 200) {
        $result = json_decode( $response,true);
        $account_type = 'account';
        $filteredArray = array_filter($result['data'], function($item) use ($account_type) {
            return $item['type'] === $account_type;
        });
        return  $filteredArray??[];
    }else{
        return  [];
    }

}
function create_sudo_customer($api_key,$base_url,$user){
    $curl = curl_init();

    curl_setopt_array($curl, [
    CURLOPT_URL => $base_url."/customers",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => json_encode([
        "type" => "individual",
        "name" => $user->fullname,
        "status" => "active",
        'emailAddress' => $user->email,
        'phoneNumber' =>$user->mobile??'323456789',
        "individual" => [
            'identity' => [
                'type' => 'BVN',
                'number' => '123456789'
            ],
            "firstName" =>  $user->firstname,
            "lastName" =>  $user->lastname,
            'dob' => '1999/01/01'
        ],
        "billingAddress" => [
            "line1" => $user->address->address??"4 Barnawa Close",
            "line2" => "",
            "city" => $user->address->city??"Barnawa",
            "state" => $user->address->state??"Kaduna",
            "country" => $user->address->country??"Nigeria",
            "postalCode" => $user->address->state??"800243"
        ]
    ]),
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer ".$api_key,
        "accept: application/json",
        "content-type: application/json"
    ],
    ]);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {

        $result = json_decode( $response,true);
        return  $result;
    } else {
        $result = json_decode( $response,true);
        return  $result;
    }
}
function create_virtual_card($amount,$api_key,$base_url,$customerId, $currency,$bankCode, $debitAccountId, $issuerCountry){

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $base_url."/cards",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode([
            'type' => 'virtual',
            'currency' => $currency,
            'status' => 'active',
            'brand' =>"MasterCard",
            'issuerCountry' => $issuerCountry,
            'amount' =>  $amount,
            'customerId' => $customerId,
            'bankCode' => $bankCode,
            'debitAccountId' =>$debitAccountId
        ]),
        CURLOPT_HTTPHEADER => [
        "Authorization: Bearer ". $api_key,
        "accept: application/json",
        "content-type: application/json"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        return json_decode($response,true);
    } else {
        return json_decode($response,true);
    }
}
function cardUpdate($api_key,$base_url,$card_id,$status){
    $curl = curl_init();

    curl_setopt_array($curl, [
    CURLOPT_URL => $base_url."/cards"."/".$card_id,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "PUT",
    CURLOPT_POSTFIELDS => json_encode([
        'status' => $status
    ]),
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer ".$api_key,
        "accept: application/json",
        "content-type: application/json"
    ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return json_decode($response,true);
    } else {
        return json_decode($response,true);
    }

}
function getCardToken($api_key,$base_url,$card_id){
    $curl = curl_init();
    curl_setopt_array($curl, [
    CURLOPT_URL => $base_url."/cards"."/".$card_id."/token",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer ".$api_key,
        "accept: application/json"
    ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);


    curl_close($curl);

    if ($err) {
     $result = json_decode($response,true);
     return $result;
    } else {
        $result = json_decode($response,true);
        return $result;
    }

}
function getCardTransactions($api_key,$base_url,$card_id){
    $curl = curl_init();
    curl_setopt_array($curl, [
    CURLOPT_URL => $base_url."/cards"."/".$card_id."/transactions",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer ".$api_key,
        "accept: application/json"
    ],
    ]);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    if ($err) {
     $result = json_decode($response,true);
     return $result;
    } else {
        $result = json_decode($response,true);
        return $result;
    }

}

function getSudoBalance(){
    $method = VirtualCardApi::first();
    $currency = get_default_currency_code();
    $sudo_accounts = get_sudo_accounts( $method->config->sudo_api_key,$method->config->sudo_url);
    if(isset($sudo_accounts['statusCode'])){
        $data =[
            'amount' => 0,
            'status' => false,
            'message' => $sudo_accounts['message'],
       ];
        return $data;
    }
    $filteredArray = array_filter($sudo_accounts, function($item) use ($currency) {
        return $item['currency'] === $currency;
    });
    $matchingElements = array_values($filteredArray);
    if( $matchingElements == [] || $matchingElements == null || $matchingElements == ""){
       $data =[
            'amount' => 0,
            'status' => false,
            'message' => get_default_currency_code()." Currency Not Supported For Sudo Account",
       ];
       return $data;
    }
    $curl = curl_init();
    curl_setopt_array($curl, [
    CURLOPT_URL => $method->config->sudo_url."/accounts"."/".$matchingElements[0]['_id']."/balance",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer ". $method->config->sudo_api_key,
        "accept: application/json"
    ],
    ]);

    $response = curl_exec($curl);
    $result = json_decode( $response,true);

    if(isset($result['statusCode'])){
        if($result['statusCode'] == 200){
            $data =[
                'amount' => $result['data']['availableBalance'],
                'status' => true,
                'message' =>" SuccessFully Fetch Account Balance",
           ];
            return  $data;
        }else{
            $data =[
                'amount' => 0,
                'status' => false,
                'message' =>"Something Is Wrong,Please Contact With Owner",
           ];
            return $data;
        }

    }

}
function getSudoCard($card_id){
    $method = VirtualCardApi::first();
    $apiUrl = $method->config->sudo_url.'/'.'cards/'.$card_id;
    $apiKey = $method->config->sudo_api_key;
    $ch = curl_init($apiUrl);
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
    ]);
    // Execute cURL session and get the response
    $response = curl_exec($ch);
    // Close cURL session
    curl_close($ch);
    $result = json_decode( $response,true);
    if(isset($result['statusCode'])){
        if($result['statusCode'] == 200){
            $data =[
                'data' => $result['data'],
                'status' => true,
                'message' =>"Card fetched successfully.",
           ];
        }else{
            $data =[
                'data' => [],
                'status' => false,
                'message' =>__("Something Went Wrong! Please Try Again"),
           ];
        }

    }
    return $data;

}
function sudoFundCard($card_account_number,$amount){
    $method = VirtualCardApi::first();
    $currency = get_default_currency_code();
    $apiUrl = $method->config->sudo_url.'/accounts/transfer';
    $apiKey = $method->config->sudo_api_key;
    $sudo_accounts = get_sudo_accounts( $method->config->sudo_api_key,$method->config->sudo_url);
    $filteredArray = array_filter($sudo_accounts, function($item) use ($currency) {
        return $item['currency'] === $currency;
    });
    $matchingElements = array_values($filteredArray);
    if( $matchingElements == [] || $matchingElements == null || $matchingElements == ""){
        $data =[
            'data' => [],
            'status' => false,
            'message' =>__("Something Went Wrong! Please Try Again"),
        ];
        return $data;
     }

    $data = [
        'debitAccountId' => $matchingElements[0]['_id'],
        'creditAccountId' => $card_account_number,
        'beneficiaryBankCode' => '',
        'amount' => $amount,
        'paymentReference' => getTrxNum(),
    ];

    $ch = curl_init($apiUrl);

    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
        'Content-Type: application/json',
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode( $response,true);

    if(isset($result['statusCode'])){
        if($result['statusCode'] == 200){
            $data =[
                'data' => $result['data'],
                'status' => true,
                'message' =>"Approved or completed successfully",
           ];
        }else{
            $data =[
                'data' => [],
                'status' => false,
                'message' =>__("Something Went Wrong! Please Try Again"),
           ];
        }

    }
    return $data;
}
function updateSudoCardBalance($user,$card_id,$response){
    $card = SudoVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
    $card->amount = $response['data']['balance']??$card->amount;
    $card->save();
    return  $card->amount??0;
}
