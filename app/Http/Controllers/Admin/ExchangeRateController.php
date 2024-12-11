<?php

namespace App\Http\Controllers\Admin;

use Exception;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use App\Models\Admin\ExchangeRate;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class ExchangeRateController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = __("Exchange Rate");
        $exchange_rates = ExchangeRate::orderBy('name', 'asc')->paginate(20);
        return view('admin.sections.exchange-rate.index',compact(
            'page_title',
            'exchange_rates'
        ));
    }

    /**
     * Update transaction charges
     * @param Request closer
     * @return back view
     */
    public function update(Request $request) {
        try{
            foreach ($request->id as $key => $id) {
                ExchangeRate::find($id)->update(['rate' => $request->rate[$key] ? $request->rate[$key] : 0]);
            }
        }catch(Exception $e) {
            return back()->with(['error' => ["Something Went Wrong! Please Try Again."]]);
        }
        return back()->with(['success' => ['Exchange Rate Updated Successfully!']]);
    }


    public function search(Request $request) {
        $validator = Validator::make($request->all(),[
            'text'  => 'required|string',
        ]);

        if($validator->fails()) {
            $error = ['error' => $validator->errors()];
            return Response::error($error,null,400);
        }

        $validated = $validator->validate();
        $exchange_rates = ExchangeRate::search($validated['text'])->select()->limit(10)->get();
        return view('admin.components.data-table.exchange-rate-table',compact(
            'exchange_rates',
        ));
    }
}
