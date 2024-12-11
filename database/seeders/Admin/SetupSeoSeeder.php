<?php

namespace Database\Seeders\Admin;

use App\Models\Admin\SetupSeo;
use Illuminate\Database\Seeder;

class SetupSeoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $setup_seos = array(
            array('id' => '1','slug' => 'strip-card','title' => 'StripCard- Virtual Credit Card Solution','desc' => 'StripCard is a software application used to conduct an online chat conversation for text and image, in lieu of providing direct contact with a live human agent. It is capable of maintaining a conversation with a user in natural language, understanding their intent, and replying based on preset rules and data.','tags' => '["24\\/7 support","card issuance","customizable","easy setup","financial inclusion","one-time-use codes","online payments","payment solutions","secure transactions","sleek design","User-Friendly interface","virtual credit cards"]','image' => 'seeder/seo_image.jpg','last_edit_by' => '1','created_at' => now(),'updated_at' => now())
          );

        SetupSeo::truncate();
        SetupSeo::insert($setup_seos);
    }
}
