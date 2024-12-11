<?php

namespace Database\Seeders\Admin;


use Illuminate\Database\Seeder;

class UpdateEnvData extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $env_modify_keys = [
            "APP_TIMEZONE"      => "Asia/Dhaka"
        ];

        modifyEnv($env_modify_keys);
    }
}
