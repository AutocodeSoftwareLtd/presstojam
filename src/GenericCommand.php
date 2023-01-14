<?php

// src/Command/CreateUserCommand.php

namespace GenerCodeCmd;

use Illuminate\Console\Command;
use \Illuminate\Container\Container;

class GenericCommand extends Command
{

    protected $http;
    private $username;
    private $password;
    protected $project_id;
    protected $download_dir;
    

    public function __construct()
    {
        parent::__construct();
        $this->http = new \GenerCodeClient\HttpClient("https://api.presstojam.com");
        //set token as session

        $this->username = config("cmd.username");
        $this->password = config("cmd.password");
        $this->project_id = config("cmd.project_id");
        $this->download_dir = config("cmd.download_dir");
    }

   
    public function checkUser()
    {
        $res = $this->http->get("/user/check-user");
        return $res["name"];
    }


    public function login()
    {

        if (!$this->username) {              
            $this->username = $this->ask('Please enter your username:');
        }

        if (!$this->password) {
            $this->password = $this->secret('Please enter your password: ');
        }


        $res = $this->http->post("/user/login/accounts", [
            "email"=>$this->username,
            "password"=>$this->password
        ]);

        if ($res["--id"] == 0) {
            throw new \Exception("Username / password not recognised");
        }
    }

    public function logout()
    {
        $this->http->post("/user/logout");
    }

    public function checkStatus()
    {
        $name = $this->checkUser();
        if ($name != "public" and $name != "accounts") {
            $this->logout();
            $name = "public";
        }


        if ($name == "public") {
                
            $this->login();
            
        }


        if (!$this->project_id) {
            $projects = $this->http->get("/data/projects", ["__fields"=>["--id", "domain"]]);

            $arr = [];
            foreach ($projects as $row) {
                $arr[$row['--id']] = $row['domain'];
            }


            $project = $this->choice('Please select your project', array_values($arr));
            $this->project_id = array_search($project, $arr);
        }


        if (!$this->download_dir) {
            $dir = $this->ask('Please enter your download directory: ');
            if (substr($dir, 0, 2) == "./") {
                $this->download_dir = $_ENV['root'] . "/" . substr($dir, 2);
            } else {
                $this->download_dir = $dir;
            }
        }
    }


    public function processQueue($dispatch_id)
    {
        while (true) {
            $res = $this->http->get("/data/queue/active", ["--id"=>$dispatch_id, "__fields"=>["progress"]]);
            var_dump($res);
            if (!$res) {
                return true;
            } elseif ($res['progress'] == "FAILED") {
                return false;
            }
            sleep(2);
        }
    }

    public function executeWrapper($cb)
    {
        try {
            $cb($input, $output);
            $this->info('Process Complete');
            return 0;
        } catch(\GenerCodeClient\ApiErrorException $e) {
            $this->error($e->getMessage());
            return 1;
          //  $output->writeln($e->getDetails());
        } catch (\Exception $e) {
            $this->error($e->getFile() . ": " . $e->getLine() . "\n" . $e->getMessage());
            return 2;
        }
    }
}
