<?php

namespace App\Http\Helpers;

use App\Http\Controllers\Admin\PushNotificationController;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminHasRole;
use App\Models\Admin\AdminNotification;
use App\Models\Admin\AdminRole;
use Exception;
use Illuminate\Notifications\Notification as NotificationsNotification;
use Illuminate\Support\Facades\Notification;

class NotificationHelper {

    /**
     * Store permission routes that is uses for sending notification to admin
     */
    public array $permission_routes;

    /**
     * Store Mail template key - It should be laravel notification or notification template key
     */
    public mixed $mail_notification_via;

    /**
     * store mail notification data
     */
    public mixed $mail_notification_data;

    /**
     * store push notification content
     */
    public array $push_notification_data;

    /**
     * store admin db notification content
     */
    public array $admin_db_notification_data;


    /**
     * prepare for sending notification to admin
     * @param string|array $permission_routes
     * @return self
     */
    public function admin(string | array $permission_routes): self
    {


        if(is_string($permission_routes)) {
            $permission_routes = [$permission_routes];
        }

        $this->permission_routes = $permission_routes;

        return $this;
    }

    /**
     * Get admins id based on the permission routes
     * @return array $admins_id
     */
    public function getPermissionAdminsId():array
    {
        $admins_id = AdminHasRole::join('admin_role_permissions', 'admin_has_roles.admin_role_id', '=', 'admin_role_permissions.admin_role_id')
                    ->join('admin_role_has_permissions', 'admin_role_permissions.id', '=', 'admin_role_has_permissions.admin_role_permission_id')
                    ->whereIn('admin_role_has_permissions.route', $this->permission_routes)
                    ->pluck('admin_has_roles.admin_id')
                    ->toArray();

        return $admins_id;
    }

    /**
     * get user using model
     * @param mixed $model - It should be user model or admin model
     * @param array $users_id = It should be model users id
     * @return mixed $users - model users
     */
    public function getUsersFromId(mixed $model, array $users_id):mixed
    {
        $admin_roles = AdminRole::superAdmin()->active()->first();
        $super_admin = $model::where('id',$admin_roles->admin_id)->first();
        $users = $model::whereIn('id', $users_id)->get();

        // Append the super admin to the users array if it exists
        if ($super_admin) {
            $users->push($super_admin);
        }

        return $users;
    }

    /**
     * Send notifications using mail
     * @param mixed $notification_via - It should be laravel notification class or notification template key
     * @return self
     */
    public function mail(mixed $notification_via, mixed $data): self
    {
        $this->mail_notification_via = $notification_via;
        $this->mail_notification_data = $data;
        return $this;
    }
    /**
     * Store Admin Db notifications
     * @param mixed $notification_via - It should be laravel notification class or notification template key
     * @return self
     */
    public function adminDbContent(mixed $data): self
    {
        $this->admin_db_notification_data = $data;
        return $this;
    }

    /**
     * Setup push notification data
     * @param array $data
     * @return self
     */
    public function push(array $data):self
    {
        $this->push_notification_data = $data;
        return $this;
    }

    /**
     * Send notification
     */
    public function send()
    {
        //admin db notifications
        try{
            if(!empty($this->admin_db_notification_data)){
                $this->storeAdminNotification();
            }
        }catch(Exception $e){}
        //admin email notifications
        try{
            if(!empty($this->mail_notification_via)) {

                $this->sendMail();
            }
        }catch(Exception $e){}
        //admin pusher notifications
        try{
            if(isset($this->push_notification_data) && is_array($this->push_notification_data) && count($this->push_notification_data) > 0) {
                $this->sendPush();
            }
        }catch(Exception $e){}

    }

    /**
     * Send mail to target audiences
     */
    public function sendMail()
    {
        if(class_exists($this->mail_notification_via)) {
            $notification_class = $this->mail_notification_via;
            $users = $this->getUsersFromId(Admin::class, $this->getPermissionAdminsId());
            Notification::send($users, new $notification_class($this->mail_notification_data));
        }
    }

    /**
     * Send push notification to target audiences
     */
    public function sendPush()
    {
        $admin_ids = $this->getUsersFromId(Admin::class, $this->getPermissionAdminsId())->pluck('id')->toArray();
        if(isset($this->push_notification_data['unauthorize'])){
            (new PushNotificationHelper())->prepareUnauthorize($admin_ids, [
                'user_type'     => $this->push_notification_data['user_type'],
                'title'         => $this->push_notification_data['title'],
                'desc'          => $this->push_notification_data['desc'],
                'user_guard'    => $this->push_notification_data['user_guard'],
            ])->send();
        }elseif(isset($this->push_notification_data['from']) && $this->push_notification_data['from'] === 'api'){
            (new PushNotificationHelper())->prepareApi($admin_ids, [
                'user_type'     => $this->push_notification_data['user_type'],
                'title'         => $this->push_notification_data['title'],
                'desc'          => $this->push_notification_data['desc'],
            ])->send();
        }else{


            (new PushNotificationHelper())->prepare($admin_ids, [
                'user_type' => $this->push_notification_data['user_type'],
                'title'     => $this->push_notification_data['title'],
                'desc'      => $this->push_notification_data['desc'],
            ])->send();
        }

    }
     /**
     * Store Admin notification data
     */
    public function storeAdminNotification(){
        $admins = $this->getUsersFromId(Admin::class, $this->getPermissionAdminsId());
        $data = $this->admin_db_notification_data;
        foreach($admins as $admin){
            $data['image'] = get_image($admin->image,'admin-profile','profile');
            AdminNotification::create([
                'type'      =>  $data['type'],
                'admin_id'  => $admin->id,
                'message'   => $data,
            ]);
        }
    }
}
