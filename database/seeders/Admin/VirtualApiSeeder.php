<?php

namespace Database\Seeders\Admin;

use App\Models\VirtualCardApi;
use Illuminate\Database\Seeder;

class VirtualApiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
          $virtual_card_apis = array(
            array('id' => '1','admin_id' => '1','card_details' => '<p>This card is property of StripCard, Wonderland. Misuse is criminal offense. If found, please return to StripCard or to the nearest bank.</p>','config' => '{"flutterwave_secret_key":"FLWSECK_TEST-SANDBOXDEMOKEY-X","flutterwave_secret_hash":"AYxcfvgbhnj@34","flutterwave_url":"https:\\/\\/api.flutterwave.com\\/v3","sudo_api_key":"eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJfaWQiOiI2NTY3MDBiYmQxNTQwNzYyMzA1ZWUyNjMiLCJlbWFpbEFkZHJlc3MiOiJ1c2VyM0BhcHBkZXZzLm5ldCIsImp0aSI6IjY1NjcwMTc3ZDE1NDA3NjIzMDVlZWIxNyIsIm1lbWJlcnNoaXAiOnsiX2lkIjoiNjU2NzAwYmJkMTU0MDc2MjMwNWVlMjY2IiwiYnVzaW5lc3MiOnsiX2lkIjoiNjU2NzAwYmJkMTU0MDc2MjMwNWVlMjYxIiwibmFtZSI6IkFwcERldnMiLCJpc0FwcHJvdmVkIjpmYWxzZX0sInVzZXIiOiI2NTY3MDBiYmQxNTQwNzYyMzA1ZWUyNjMiLCJyb2xlIjoiQVBJS2V5In0sImlhdCI6MTcwMTI0OTM5OSwiZXhwIjoxNzMyODA2OTk5fQ.oB0i1Hn_MMLM3tZpbAEqU6YlDIqtk_yJT25EGhE021E","sudo_vault_id":"tntbuyt0v9u","sudo_url":"https:\\/\\/api.sandbox.sudo.cards","sudo_mode":"sandbox","stripe_public_key":"pk_test_51NjGM4K6kUt0AggqD10PfWJcB8NxJmDhDptSqXPpX2d4Xcj7KtXxIrw1zRgK4jI5SIm9ZB7JIhmeYjcTkF7eL8pc00TgiPUGg5","stripe_secret_key":"sk_test_51NjGM4K6kUt0Aggqfejd1Xiixa6HEjQXJNljEwt9QQPOTWoyylaIAhccSBGxWBnvDGw0fptTvGWXJ5kBO7tdpLNG00v5cWHt96","stripe_url":"https:\\/\\/api.stripe.com\\/v1","strowallet_public_key":"R67MNEPQV2ABQW9HDD7JQFXQ2AJMMY","strowallet_secret_key":"AOC963E385FORPRRCXQJ698C1Q953B","strowallet_url":"https:\\/\\/strowallet.com\\/api\\/bitvcard\\/","strowallet_mode":"sandbox","name":"strowallet"}','created_at' => now(),'updated_at' => now(),'image' => 'seeder/virtual-card.webp','card_limit' => '3')
          );

        VirtualCardApi::insert($virtual_card_apis);
    }
}
