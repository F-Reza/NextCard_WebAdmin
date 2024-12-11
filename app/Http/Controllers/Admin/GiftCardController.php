<?php

namespace App\Http\Controllers\Admin;

use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Controller;
use App\Models\Admin\GiftCardApi;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Helpers\Response;

class GiftCardController extends Controller
{
    public function index()
    {
        $page_title = __("Setup Gift Card Api");
        $api = GiftCardApi::first();
        return view('admin.sections.gift-card.api',compact(
            'page_title',
            'api',
        ));
    }
    public function updateCredentials(Request $request){
        $validator = Validator::make($request->all(), [
            'client_id'                 => 'required|string',
            'secret_key'                => 'required|string',
            'production_base_url'       => 'required|url',
            'sandbox_base_url'          => 'required|url',
            'env'                       => 'required|string',
        ]);
        if($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        $validated = $validator->validate();
        $api = GiftCardApi::reloadly()->first();
        $credentials = array_filter($request->except('_token','env','_method'));
        $data['credentials'] =  $credentials;
        $data['env'] =  $validated['env'];
        $data['status'] = true;
        $data['provider'] =  GiftCardApi::PROVIDER_RELOADLY;
        if(!$api){
            GiftCardApi::create($data);
        }else{
            $api->fill($data)->save();
        }
        return back()->with(['success' => [__("Gift Card API Has Been Updated.")]]);
    }

    //transaction logs
    public function giftCards()
    {
        $page_title = __("All Logs");
        $transactions = Transaction::with(
            'user:id,firstname,lastname,email,username,full_mobile',
            'currency:id,name',
        )->where('type', PaymentGatewayConst::GIFTCARD)->latest()->paginate(20);
        return view('admin.sections.gift-card.log',compact(
            'page_title',
            'transactions'
        ));
    }

    public function giftCardSearch(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'text'  => 'required|string',
        ]);

        if($validator->fails()) {
            $error = ['error' => $validator->errors()];
            return Response::error($error,null,400);
        }
        $validated = $validator->validate();

        $transactions = Transaction::with(
            'user:id,firstname,lastname,email,username,full_mobile',
            'currency:id,name',
        )->where('type', PaymentGatewayConst::GIFTCARD)->where("trx_id","like","%".$validated['text']."%")->latest()->paginate(20);

        return view('admin.components.data-table.gift-card-transaction-log', compact(
            'transactions'
        ));
    }
    public function giftCardDetails($id){
        $data = Transaction::where('id',$id)->with(
            'user:id,firstname,lastname,email,username,full_mobile',
            'currency:id,name,alias,payment_gateway_id,currency_code,rate',
        )->where('type', PaymentGatewayConst::GIFTCARD)->first();
        $page_title = __("Gift Details For").'  '.$data->trx_id;
        return view('admin.sections.gift-card.details', compact(
            'page_title',
            'data'
        ));
    }
}
