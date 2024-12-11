<?php

namespace Database\Seeders\Admin;

use App\Constants\ModuleSetting;
use App\Models\Admin\ModuleSetting as AdminModuleSetting;
use Illuminate\Database\Seeder;

class ModuleSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
         //make module for user
        $data = [
            ModuleSetting::TRANSFER_MONEY           => 'Transfer Money',
            ModuleSetting::ADD_MONEY                => 'Add Money',
            ModuleSetting::WITHDRAW_MONEY           => 'Withdraw Money',
            ModuleSetting::VIRTUAL_CARD             => 'Virtual Card',
            ModuleSetting::GIFTCARDS                => 'Gift Cards',

        ];
        $create = [];
        foreach($data as $slug => $item) {
            $create[] = [
                'admin_id'          => 1,
                'slug'              => $slug,
                'user_type'         => "USER",
                'status'            => true,
                'created_at'        => now(),
            ];
        }
        AdminModuleSetting::insert($create);
    }
}
