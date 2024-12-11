<?php

namespace App\Http\Controllers\Admin;

use App\Constants\GlobalConst;
use App\Http\Controllers\Controller;
use App\Models\Admin\SystemMaintenance;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SystemMaintenanceController extends Controller
{
      /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = __("System Maintenance");
        $data = SystemMaintenance::first();
        return view('admin.sections.system-maintenance.index',compact(
            'page_title',
            'data',
        ));
    }

     /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'title'         => 'required|string',
            'status'        => 'required|integer',
            'details'       => 'required|string|max:50000',
        ]);

        $validated = $validator->validate();
        $status = [
            '0'     => false,
            '1'     => true,
        ];
        $validated['status'] = $status[$validated['status']];

        try{
            SystemMaintenance::updateOrCreate(['slug' => GlobalConst::SYSTEM_MAINTENANCE],['title' =>  $validated['title'] , 'details' => $validated['details'], 'status' => $validated['status']]);
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again")]]);
        }

        return back()->with(['success' => [__("Information updated successfully!")]]);
    }

}
