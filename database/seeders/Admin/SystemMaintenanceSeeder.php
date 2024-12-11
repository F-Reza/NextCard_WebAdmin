<?php

namespace Database\Seeders\Admin;

use App\Models\Admin\AppSettings;
use App\Models\Admin\SystemMaintenance;
use Illuminate\Database\Seeder;

class SystemMaintenanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $system_maintenances = array(
            array('slug' => 'system-maintenance','title' => 'Enhancing Your Experience – Site Under Maintenance','details' => '<p>Our website is down for upgrades and will be back shortly. If you need assistance, please email us at <strong>support@stripcard.com</strong> or message us on WhatsApp at <strong>+1234567890</strong>. Thank you for your patience!</p>','status' => '0','created_at' => now(),'updated_at' => now())
        );
        SystemMaintenance::upsert($system_maintenances,['slug'],['title','details','status','created_at','updated_at']);
    }
}
