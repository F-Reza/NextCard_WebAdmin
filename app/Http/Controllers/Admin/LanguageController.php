<?php

namespace App\Http\Controllers\Admin;

use App\Constants\LanguageConst;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Imports\LanguageImport;
use App\Models\Admin\Language;
use Exception;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\File;

class LanguageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = __("Language Manager");
        $languages = Language::paginate(10);
        return view('admin.sections.language.index',compact(
            'page_title',
            'languages',
        ));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'name'      => 'required|string|max:80|unique:languages,name',
            'code'      => 'required|string|max:20|unique:languages,code',
            'dir'       => 'required|string|max:20|in:ltr,rtl',
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with("modal","language-add");
        }

        $validated = $validator->validate();

        $default = false;
        if(!Language::default()->exists()) {
            $default = true;
        }

        $validated['status']            = $default;
        $validated['last_edit_by']      = auth()->user()->id;

        try{
            Language::create($validated);
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again")]]);
        }

        return back()->with(['success' => [__("Language created successfully!")]]);
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
            'target'        => 'required|numeric|exists:languages,id',
            'edit_name'     => ["required","string","max:80",Rule::unique("languages","name")->ignore($request->target)],
            'edit_code'     => ["required","string","max:80",Rule::unique("languages","code")->ignore($request->target)],
            'edit_dir'      => ["required","string","max:20","in:ltr,rtl"],
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with("modal","language-edit");
        }

        $validated = $validator->validate();
        $validated = replace_array_key($validated,"edit_");
        $validated = Arr::except($validated,['target']);

        $language = Language::find($request->target);

        try{
            $language->update($validated);
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again")]]);
        }

        return back()->with(['success' => [__("Language updated successfully!")]]);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $request)
    {
        $request->validate([
            'target'    => 'required|numeric|exists:languages,id',
        ]);

        $language = Language::find($request->target);
        if($language->code == LanguageConst::NOT_REMOVABLE) {
            return back()->with(['error' => ['Language ('.$language->name.') is not removable.']]);
        }

        try{
           $language->delete();
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again")]]);
        }

        // Delete File
        try{
            $file = lang_path($language->code.".json");
            delete_file($file);
        }catch(Exception $e) {
            return back()->with(['warning' => [__("File remove failed!")]]);
        }

        return back()->with(['success' => [__("Language deleted successfully!")]]);
    }


    public function statusUpdate(Request $request) {
        $validator = Validator::make($request->all(),[
            'data_target'       => 'required|numeric|exists:languages,id',
            'status'            => 'required|boolean',
        ]);

        if($validator->fails()) {
            $errors = ['error' => $validator->errors() ];
            return Response::error($errors);
        }

        $validated = $validator->validate();

        if(Language::whereNot("id",$validated['data_target'])->default()->exists()) {
            $warning = ['warning' => [__("Please deselect your default language first.")]];
            return Response::warning($warning);
        }

        $language = Language::find($validated['data_target']);

        try{
            $language->update([
                'status'        => ($validated['status']) ? false : true,
            ]);
        }catch(Exception $e) {
            $errors = ['error' => [__("Something went wrong! Please try again")] ];
            return Response::error($errors,null,500);
        }

        $success = ['success' => [__("Language status updated successfully!")]];
        return Response::success($success);
    }


    public function info($code) {

        $language = Language::where("code",$code);

        if(!$language->exists()) {
            return back()->with(['error' => [__("Sorry! Language not found!")]]);
        }

        $file = lang_path($code.".json");
        if(!is_file($file)) {
            return back()->with(['error' => [__("Something went wrong! Please try again")]]);
        }

        $data = file_get_contents($file);
        try{
            $key_value = json_decode($data,true);
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again")]]);
        }

        $language = $language->first();

        $page_title = "Language Information";
        return view('admin.sections.language.info',compact(
            'page_title',
            'key_value',
            'language',
        ));
    }


    public function import(Request $request) {

        $validator = Validator::make($request->all(),[
            'language'      => 'required|string|exists:languages,code',
            'file'          => 'required|file|mimes:csv,xlsx',
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with("modal","language-import");
        }

        $validated = $validator->validate();

        try{
            $sheets = (new LanguageImport)->toArray($validated['file'])->columnData()->keyValue();
        }catch(Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }
        //  dd(json_encode((new LanguageImport)->toArray($validated['file'])->columnData()->getArray()['Key']));
        $filter_with_database_lang = array_intersect_key($sheets,[$validated['language'] => "value"]);

        $get_predefine_keys = LanguageImport::getKeys();

        foreach($filter_with_database_lang as $code => $item) {

            $item = array_intersect_key($item,array_flip($get_predefine_keys));

            $json_format = json_encode($item);

            $file = lang_path($code.".json");
            if(is_file($file)) {
                file_put_contents($file,$json_format);
            }else {
                create_file($file);
                file_put_contents($file,$json_format);
            }
        }

        try{
            if($request->hasFile('file')) {
                $file_name = 'language-'.Carbon::parse(now())->format("Y-m-d") . "." .$validated['file']->getClientOriginalExtension();
                $file_link = get_files_path('language-file') . '/' . $file_name;
                (new Filesystem)->cleanDirectory(get_files_path('language-file'));
                File::move($validated['file'],$file_link);
            }
        }catch(Exception $e) {
            return back()->with(['warning' => [__("Failed to store new file.")]]);
        }

        return back()->with(['success' => [__("Language updated successfully!")]]);
    }


    public function switch(Request $request) {
        $code = $request->target;
        $language = Language::where("code",$code);
        if(!$language->exists()) {
            return back()->with(['error' => [__("Oops! Language not found!")]]);
        }

        Session::put('local',$code);

        return back()->with(['success' => [__("Language switch successfully!")]]);
    }


    public function download() {
        $file_path = get_files_path('language-file');
        $file_name = get_first_file_from_dir($file_path);
        if($file_name == false) {
            return back()->with(['error' => [__("File does not exists.")]]);
        }
        $file_link = $file_path . '/' . $file_name;
        return response()->download($file_link);
    }
}
