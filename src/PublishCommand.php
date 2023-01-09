<?php

// src/Command/CreateUserCommand.php

namespace GenerCodeCmd;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class PublishCommand extends GenericCommand
{
    
    protected $description = 'Writes latest version of files required for the API';
    protected $signature = "gc:publish";


    public function handle()
    {
        try {
            $response = $this->http->post("/publish", ["--id"=>$this->project_id]);
            $dispatch_id = $response['--dispatchid'];
            sleep(10);
            if ($this->processQueue($dispatch_id)) {
                $this->info("Publish process succeeded");
            } else {
                $this->error("Publish process failed - check for " . $dispatch_id);
            }
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
