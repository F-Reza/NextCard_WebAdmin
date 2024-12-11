<?php

namespace App\Http\Middleware\Admin;

use App\Constants\AdminRoleConst;
use App\Http\Helpers\Api\Helpers;
use App\Models\Admin\SystemMaintenance as AdminSystemMaintenance;
use Closure;
use Illuminate\Http\Request;

class SystemMaintenanceApi
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $system_maintenance = AdminSystemMaintenance::first();
        if( $system_maintenance->status == 1){
            $data =[
                'base_url'      => url("/"),
                'image_path'    => files_asset_path_basename("error-images"),
                "image"         => "maintenance-mode.webp",
                'status'        => $system_maintenance->status,
                'title'         => $system_maintenance->title,
                'details'       => strip_tags($system_maintenance->details),
            ];
            $message = ['error'=>[__($system_maintenance->title??"")]];
            return Helpers::maintenance($message,$data);

        }
        return $next($request);

    }
}
