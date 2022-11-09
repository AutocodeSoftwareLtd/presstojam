<?php

// src/Command/CreateUserCommand.php

namespace GenerCodeCmd;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use \Illuminate\Container\Container;

class GenericCommand extends Command
{
    protected $app;
    protected $http;
    private $username;
    private $password;
    protected $project_id;
    protected $download_dir;
    

    public function __construct(Container $app)
    {
        parent::__construct();
        $this->app = $app;
        $this->http = new \GenerCodeClient\HttpClient("https://api.presstojam.com");
        //set token as session

        $this->username = $this->app->config->get("cmd.username");
        $this->password = $this->app->config->get("cmd.password");
        $this->project_id = $this->app->config->get("cmd.project_id");
        $this->download_dir = $this->app->config->get("cmd.download_dir");
    }

   
    public function checkUser()
    {
        $res = $this->http->get("/user/check-user");
        return $res["name"];
    }


    public function login(InputInterface $input, OutputInterface $output)
    {

        $helper = $this->getHelper('question');

        if (!$this->username) {              
            $question = new Question('Please enter your username: ', '');
            $this->username = $helper->ask($input, $output, $question);
        }

        if (!$this->password) {
            $question = new Question('Please enter your password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $this->password = $helper->ask($input, $output, $question);
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

    public function interact(InputInterface $input, OutputInterface $output)
    {
        $name = $this->checkUser();
        if ($name != "public" and $name != "accounts") {
            $this->logout();
            $name = "public";
        }


        if ($name == "public") {
                
            $this->login($input, $output);
            
        }




        if (!$this->project_id) {
            $helper = $this->getHelper('question');
            $projects = $this->http->get("/data/projects", ["__fields"=>["--id", "domain"]]);

            $arr = [];
            foreach ($projects as $row) {
                $arr[$row['--id']] = $row['domain'];
            }



            $question = new ChoiceQuestion(
                'Please select your project',
                // choices can also be PHP objects that implement __toString() method
                array_values($arr)
            );

            $question->setErrorMessage('Project %s is invalid.');

            $project = $helper->ask($input, $output, $question);
            $this->project_id = array_search($project, $arr);
        }


        if (!$this->download_dir) {
            $helper = $this->getHelper('question');
            $question = new Question('Please enter your download directory: ', '');
            $dir = $helper->ask($input, $output, $question);
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
            if ($res['progress'] == 'PROCESSED') {
                return true;
            } elseif ($res['progress'] == "FAILED") {
                return false;
            }
            sleep(2);
        }
    }

    public function executeWrapper(InputInterface $input, OutputInterface $output, $cb)
    {
        try {
            $cb($input, $output);
            $output->writeln('Process Complete');
            return 0;
        } catch(\GenerCodeClient\ApiErrorException $e) {
            $output->writeln($e->getMessage());
            $e->getDetails();
            return 1;
          //  $output->writeln($e->getDetails());
        } catch (\Exception $e) {
            $output->writeln($e->getFile() . ": " . $e->getLine() . "\n" . $e->getMessage());
            return 2;
        }
    }
}
