<?php

namespace App\Http\Controllers\Admin;

use App\Constants\PaymentGatewayConst;
use App\Exports\SendMoneyTrxExport;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Providers\Admin\BasicSettingsProvider;
use Maatwebsite\Excel\Facades\Excel;

class TransferMoneyController extends Controller
{
     /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    protected $basic_settings;

    public function __construct()
    {
        $this->basic_settings = BasicSettingsProvider::get();
    }
    public function index()
    {
        $page_title = __("All Logs");
        $transactions = Transaction::with(
          'user:id,firstname,lastname,email,username,full_mobile',
            'currency:id,name,alias,payment_gateway_id,currency_code,rate',
        )->where('type', PaymentGatewayConst::TYPETRANSFERMONEY)->where('attribute',PaymentGatewayConst::SEND)->latest()->paginate(20);
        return view('admin.sections.transfer-money.index',compact(
            'page_title','transactions'
        ));
    }

}
