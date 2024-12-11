<?php 

namespace Project\Installer\Helpers;

use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

class DBHelper {

    public function create(array $data) {

        $this->updateEnv([
            'DB_CONNECTION'     => "mysql",
            'DB_HOST'           => $data['host'],
            'DB_PORT'           => $data['port'],
            'DB_DATABASE'       => $data['db_name'],
            'DB_USERNAME'       => $data['db_user'],
            'DB_PASSWORD'       => $data['db_user_password'],
        ]);
        
        $this->setStepSession();
        $this->saveDataInSession($data);

        $helper = new Helper();
        $helper->cache($data);
    }

    public function updateEnv(array $replace_array) 
    {
        sleep(2);

        $env_content_string = File::get(App::environmentFilePath());
        cache()->driver("file")->set(Helper::ENV_CONTENT_CACHE_KEY, $env_content_string);

        $array_going_to_modify  = $replace_array;

        if (count($array_going_to_modify) == 0) {
            return false;
        }

        $env_file_content_string = cache()->driver('file')->get(Helper::ENV_CONTENT_CACHE_KEY);

        $lines = array_values(array_filter(explode("\n", $env_file_content_string)));

        $env_content = [];
        foreach($lines as $line) {
            $line = trim($line);
            if ($line) {
                list($key, $value) = explode('=', $line, 2);
                // Remove any quotes from the value
                $value = trim($value, '"');
                // Store the key-value pair in the array
                $env_content[$key] = $value;
            }
        }

        $update_array = ["APP_ENV" => App::environment()];

        foreach ($env_content as $key => $value) {
            foreach ($array_going_to_modify as $modify_key => $modify_value) {

                if(!array_key_exists($modify_key,$env_content) && !array_key_exists($modify_key,$update_array)) {
                    $update_array[$modify_key] = $this->setEnvValue($modify_key,$modify_value);
                    break;
                }

                if ($key == $modify_key) {
                    $update_array[$key] = $this->setEnvValue($key,$modify_value);
                    break;
                } else {
                    $update_array[$key] = $this->setEnvValue($key,$value);
                }
            }
        }

        $string_content = "";
        foreach ($update_array as $key => $item) {
            $line = $key . "=" . $item;

            $string_content .= $line . "\r\n\n";
        }

        $env_file = App::environmentFilePath();

        File::put($env_file, $string_content);
    }

    public function setEnvValue($key,$value) {
        if($key == "APP_KEY") {
            return $value;
        }
        return '"' .$value . '"';
    }

    public function saveDataInSession($data) {
        session()->put('database_config_data',$data);
    }

    public static function getSessionData() {
        return session('database_config_data');
    }

    public function setStepSession() {
        session()->put("database_config","PASSED");
    }

    public static function step($step = 'database_config') {
        return session($step);
    }

    public function migrate() {

        if(App::environment() != "local") {
            $this->updateEnv([
                'APP_ENV'               => "local",
            ]);

            sleep(2);
        }

        self::execute("php artisan migrate:fresh --seed");
        self::execute("php artisan migrate");
        self::execute("php artisan passport:install");

        $this->setMigrateStepSession();

        // $helper = new Helper();
        // $data = cache()->driver("file")->get($helper->cache_key);

        // update env to production
        $this->updateEnv([
            'APP_ENV'               => "production",
        ]);
    }

    public function setMigrateStepSession() {
        session()->put('migrate','PASSED');
    }

    public function updateAccountSettings(array $data) {

        $helper = new Helper();
        $helper->cache($data);

        $p_key = $helper->cache()['product_key'] ?? "";

        if($p_key == "") {
            cache()->driver('file')->forget($helper->cache_key);
            throw new Exception("Something went wrong! Purchase code registration failed! Please try again");
        }

        $admin = DB::table('admins')->first();
        if(!$admin) {
            DB::table('admins')->insert([
                'firstname'     => $data['f_name'],
                'lastname'      => $data['l_name'],
                'password'      => Hash::make($data['password']),
                'email'         => $data['email'],
            ]);
        }else {
            DB::table("admins")->where('id',$admin->id)->update([
                'firstname'     => $data['f_name'],
                'lastname'      => $data['l_name'],
                'password'      => Hash::make($data['password']),
                'email'         => $data['email'],
            ]);
        }

        $validator = new ValidationHelper();

        if($validator->isLocalInstallation() == false) {
            $helper->connection($helper->cache());
        }

        $client_host = parse_url(url('/'))['host'];
        $filter_host = preg_replace('/^www\./', '', $client_host);

        if(Schema::hasTable('script')) {
            DB::table('script')->truncate();
            DB::table('script')->insert([
                'client'        => $filter_host,
                'signature'     => $helper->signature($helper->cache()),
            ]);
        }
        if(Schema::hasTable('basic_settings')) {
            try{
                DB::table('basic_settings')->where('id',1)->update([
                    'site_name'     => $helper->cache()['app_name'] ?? "",
                ]);
            }catch(Exception $e) {
                //handle error
            }
        }

        $db = new DBHelper();

        $db->updateEnv([
            'PRODUCT_KEY'   => $p_key,
            'APP_MODE'      => "live",
            'APP_DEBUG'     => "false"
        ]);

        $this->setAdminAccountStepSession();

        self::execute("php artisan cache:clear");
        self::execute("php artisan config:clear");
    }

    public function setAdminAccountStepSession() {
        session()->put('admin_account','PASSED');
    }

    public static function execute($cmd): string
    {
        $process = Process::fromShellCommandline($cmd);

        $processOutput = '';

        $captureOutput = function ($type, $line) use (&$processOutput) {
            $processOutput .= $line;
        };

        $process->setTimeout(null)
            ->run($captureOutput);

        if ($process->getExitCode()) {
            throw new Exception($cmd . " - " . $processOutput);
        }

        return $processOutput;
    }
}