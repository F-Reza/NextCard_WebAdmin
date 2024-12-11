<?php

namespace Database\Seeders\Admin;

use App\Models\Admin\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            [
                'name'          => "English",
                'code'          => "en",
                'status'        => 1,
                'last_edit_by'  => 1,
                'dir'           => 'ltr',
                'created_at'    => now(),

            ],
            [
                'name'          => "Spanish",
                'code'          => "es",
                'status'        => 0,
                'last_edit_by'  => 1,
                'dir'           => 'ltr',
                'created_at'    => now(),

            ],
            [
                'name'          => "Arabic",
                'code'          => "ar",
                'status'        => 0,
                'last_edit_by'  => 1,
                'dir'           => 'rtl',
                'created_at'    => now(),

            ],
            [
                'name'          => "French",
                'code'          => "fr",
                'status'        => 0,
                'last_edit_by'  => 1,
                'dir'           => 'ltr',
                'created_at'    => now(),

            ],
            [
                'name'          => "Hindi",
                'code'          => "hi",
                'status'        => 0,
                'last_edit_by'  => 1,
                'dir'           => 'ltr',
                'created_at'    => now(),

            ]
        ];


        Language::insert($data);
    }
}
