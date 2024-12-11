<?php

namespace App\Http\Middleware\Admin;

use App\Constants\AdminRoleConst;
use App\Models\Admin\SystemMaintenance as AdminSystemMaintenance;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SystemMaintenance
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
            if($request->routeIs('admin.*')){
                return $next($request);
            }else{
                if ($request->path() !== '/') {
                    return redirect('/'); // Redirect to home page
                }
                abort(503);
            }
        }
        return $next($request);

    }
}
