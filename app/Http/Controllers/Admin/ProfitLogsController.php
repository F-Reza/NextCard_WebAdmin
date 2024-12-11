<?php

namespace App\Http\Controllers\Admin;

use App\Constants\PaymentGatewayConst;
use App\Exports\AdminProfitLogs;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionCharge;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ProfitLogsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function profitLogs()
    {
        $page_title = __("All Profits Logs");
        $profits = TransactionCharge::with('transactions')
        ->whereHas('transactions', function ($query) {
            $query->whereNotIn('type', [PaymentGatewayConst::TYPEADDMONEY, PaymentGatewayConst::TYPEMONEYOUT,PaymentGatewayConst::TYPEADDSUBTRACTBALANCE]);
        })
        ->latest()->paginate(20);

        return view('admin.sections.profits.index',compact(
            'page_title','profits'
        ));
    }
}
