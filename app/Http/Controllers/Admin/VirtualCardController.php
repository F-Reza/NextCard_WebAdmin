<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\VirtualCardApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VirtualCardController extends Controller
{
    public function cardApi()
    {
        $page_title = __("Setup Virtual Card Api");
        $api = VirtualCardApi::first();
        return view('admin.sections.virtual-card.api',compact(
            'page_title',
            'api',
        ));
    }
    public function cardApiUpdate(Request $request){
        $validator = Validator::make($request->all(), [
            'api_method'                => 'required|in:flutterwave,sudo,stripe,strowallet',
            'flutterwave_secret_key'    => 'required_if:api_method,flutterwave',
            'flutterwave_secret_hash'   => 'required_if:api_method,flutterwave',
            'flutterwave_url'           => 'required_if:api_method,flutterwave',
            'sudo_api_key'              => 'required_if:api_method,sudo',
            'sudo_vault_id'             => 'required_if:api_method,sudo',
            'sudo_url'                  => 'required_if:api_method,sudo',
            'sudo_mode'                 => 'required_if:api_method,sudo',
            'card_details'              => 'required|string',
            'stripe_public_key'         => 'required_if:api_method,stripe',
            'stripe_secret_key'             => 'required_if:api_method,stripe',
            'stripe_url'                => 'required_if:api_method,stripe',
            'strowallet_public_key'     => 'required_if:api_method,strowallet',
            'strowallet_secret_key'     => 'required_if:api_method,strowallet',
            'strowallet_url'            => 'required_if:api_method,strowallet',
            'image'                     => "nullable|mimes:png,jpg,jpeg,webp,svg",
            'card_limit' => [
                'required',
                'numeric',
                Rule::in([1, 2, 3]),
            ],
        ]);
        if($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        $request->merge(['name'=>$request->api_method]);
        $data = array_filter($request->except('_token','api_method','_method','card_details','image','card_limit'));
        $api = VirtualCardApi::first();
        $api->card_details = $request->card_details;
        $api->card_limit = $request->card_limit;
        $api->config = $data;

        if ($request->hasFile("image")) {
            try {
                $image = get_files_from_fileholder($request, "image");
                $upload_file = upload_files_from_path_dynamic($image, "card-api");
                $api->image = $upload_file;
            } catch (Exception $e) {
                return back()->with(['error' => [__('Ops! Failed To Upload Image.')]]);
            }
        }
        $api->save();

        return back()->with(['success' => [__('Card API Has Been Updated.')]]);
    }

    public function transactionLogs()
    {
        $page_title = __("Virtual Card Logs");
        $transactions = Transaction::with(
          'user:id,firstname,lastname,email,username,full_mobile',
            'currency:id,name',
        )->where('type', 'VIRTUAL-CARD')->latest()->paginate(20);

        return view('admin.sections.virtual-card.logs', compact(
            'page_title',
            'transactions'
        ));
    }
}
