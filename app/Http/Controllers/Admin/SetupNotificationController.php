<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class SetupNotificationController extends Controller
{
    public function index(){
        $page_title = __("All Notification");
        return view('admin.sections.admin-notification.index',compact('page_title'));
    }
}
