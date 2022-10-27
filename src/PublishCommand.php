<?php

// src/Command/CreateUserCommand.php

namespace GenerCodeCmd;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;



class PublishCommand extends GenericCommand
{
    
    protected static $defaultDescription = 'Writes latest version of files required for the API';
    protected static $defaultName = "publish";



    public function configure(): void
    {
        parent::configure();
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp('This command creates the latest versions of your files')
        ;
    }




    public function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->executeWrapper($input, $output, function ($input, $output) {
            $response = $this->http->post("/publish", ["--id"=>$this->project_id]);
            $dispatch_id = $response['--dispatchid'];
            sleep(10);
            if ($this->processQueue($dispatch_id)) {
                if ($this->download_dir) {
                    $output->writeln('Downloading directory');
                    $download = new Downloader($this->http, $this->project_id);
                    $download->download($this->download_dir);
                }
            } else {
                $output->writeln("Publish process failed - check for " . $dispatch_id);
            }
            return 0;
        });
    }
}
