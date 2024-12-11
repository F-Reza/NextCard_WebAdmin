<?php

namespace Database\Seeders\Admin\Fresh;

use App\Models\Admin\BasicSettings;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BasicSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            'site_name'         => "StripCard",
            'site_title'        => " Virtual Credit Card Solution",
            'base_color'        => "#635BFF",
            'web_version'       => "3.9.0",
            'secondary_color'   => "#ea5455",
            'otp_exp_seconds'   => "3600",
            'timezone'          => "Asia/Dhaka",
            'site_logo_dark'        => "seeder/logo-white.png",
            'site_logo'             => "seeder/logo-dark.png",
            'site_fav_dark'         => "seeder/favicon-dark.png",
            'site_fav'              => "seeder/favicon-white.png",
            'user_registration'   => 1,
            'email_verification'   => 1,
            'kyc_verification'   => 1,
            'agree_policy'   => 1,
            'email_notification'   => 1,
            'mail_config'       => [
                "method" => "smtp",
                "host" => "",
                "port" => "",
                "encryption" => "",
                "password" => "",
                "username" => "",
                "from" => "",
                "app_name" => "",
            ],
            'broadcast_config'  => [
                "method" => "pusher",
                "app_id" => "",
                "primary_key" => "",
                "secret_key" => "",
                "cluster" => "ap2"
            ],
            'push_notification_config'  => [
                "method" => "pusher",
                "instance_id" => "",
                "primary_key" => ""
            ],
        ];

        BasicSettings::firstOrCreate($data);
    }
}
