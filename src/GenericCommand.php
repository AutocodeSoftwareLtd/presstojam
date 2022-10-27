<?php

// src/Command/CreateUserCommand.php

namespace GenerCodeDev;

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
    protected $project_id;
    protected $config;
    protected $http;
    private $username;
    private $password;
    protected $download_dir;
    protected $app;

    public function __construct(Container $app)
    {
        parent::__construct();
        $this->app = $app;
        $this->http = new \GenerCodeClient\HttpClient($app->config['api_url']);
        //set token as session
    }

    public function configure(): void
    {
        $this->addOption(
            'env',
            null,
            InputOption::VALUE_REQUIRED
        );
    }

    public function checkUser()
    {
        $res = $this->http->get("/user/check-user");
        return $res["name"];
    }


    public function login()
    {
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

        $env = $input->getOption("env");
        if ($env) {
            $configs = include($env);
            $this->username = $configs['username'];
            $this->password = $configs['password'];
            $this->project_id = $configs['project_id'];
            $this->download_dir = $configs['dir'];

            if ($name == "public") {
                $this->login();
            }
        } else {
            $helper = $this->getHelper('question');
            if ($name == "public") {
                $question = new Question('Please enter your username: ', '');
                $this->username = $helper->ask($input, $output, $question);

                $question = new Question('Please enter your password: ');
                $question->setHidden(true);
                $question->setHiddenFallback(false);

                $this->password = $helper->ask($input, $output, $question);

                $this->login();
            }
        }




        if (!$this->project_id) {
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
            $res = $this->http->get("/data/queue", ["--id"=>$dispatch_id, "__fields"=>["status"]]);
            var_dump($res);
            if ($res['status'] == 'PROCESSED') {
                return true;
            } elseif ($res['status'] == "FAILED") {
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
