<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\Admin\ModuleSetting;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ModuleSettingController extends Controller
{
    public function index() {
        $page_title = __("Setup Module Settings");
        $data = ModuleSetting::all();
        return view('admin.sections.module-setting.index',compact(
            'page_title',
            'data'
        ));
    }

    public function statusUpdate(Request $request) {
        $validator = Validator::make($request->all(),[
            'status'                    => 'required|boolean',
            'data_target'               => 'required|string',
        ]);
        if ($validator->stopOnFirstFailure()->fails()) {
            $error = ['error' => $validator->errors()];
            return Response::error($error,null,400);
        }
        $validated = $validator->safe()->all();
        $page_slug = $validated['data_target'];

        $page = ModuleSetting::where('slug',$page_slug)->first();
        if(!$page) {
            $error = ['error' => [__("Module not found!")]];
            return Response::error($error,null,404);
        }
        try{
            $page->update([
                'status' => ($validated['status'] == true) ? false : true,
            ]);
        }catch(Exception $e) {
            return $e;
            $error = ['error' => [__("Something went wrong! Please try again")]];
            return Response::error($error,null,500);
        }

        $success = ['success' => [__("Module status updated successfully!")]];
        return Response::success($success,null,200);
    }
}
