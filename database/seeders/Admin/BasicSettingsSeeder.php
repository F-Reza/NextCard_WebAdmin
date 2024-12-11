<?php

namespace Database\Seeders\Admin;

use App\Models\Admin\BasicSettings;
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
                "host" => "appdevs.team",
                "port" => "465",
                "encryption" => "ssl",
                "username" => "system@appdevs.net",
                "password" => "QP2fsLk?80Ac",
                "from" => "system@appdevs.net",
                "app_name" => "StripCard",
            ],
            'broadcast_config'  => [
                "method" => "pusher",
                "app_id" => "1574360",
                "primary_key" => "971ccaa6176db78407bf",
                "secret_key" => " a30a6f1a61b97eb8225a",
                "cluster" => "ap2"
            ],
            'push_notification_config'  => [
                "method" => "pusher",
                "instance_id" => "255ae045-4995-4b74-9caf-b9b5101780df",
                "primary_key" => "CDBB1D7FC33B562C63019647D3076998A14B97B251F651CB72B3934E49114200"
            ],
        ];

        BasicSettings::firstOrCreate($data);
    }
}
