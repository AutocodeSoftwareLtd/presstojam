<?php

namespace GenerCodeCmd;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'gc:cdn')]
class CdnCommand extends GenericCommand
{
    protected static $defaultDescription = 'Pushes public files to cdn';
    protected static $defaultName = "gc:cdn";
   

    public function configure(): void
    {
        parent::configure();
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp('Push public files to cdn and create invalidations')
        ;
   }



    public function uploadFiles($dir_path) {
        $fileHandler = $this->app->make(\GenerCodeOrm\FileHandler::class);
        $dir = new \DirectoryIterator($dir_path);

        $invalidations = [];
        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isDot() AND !$fileinfo->isDir()) {

                $real_path = $fileinfo->getRealPath();
                $relative_path = substr($real_path, strlen($this->download_dir . "/public"));

                $new_path = ltrim($relative_path, "/");

                $fileHandler->put($new_path, file_get_contents($real_path));
                $invalidations[] = "/" . $new_path;
            }
        }
        return $invalidations;
    }

    public function runInvalidations($invalidations) {
        $cfClient = new \Aws\CloudFront\CloudFrontClient([
            'version' => 'latest',
            'region' => 'eu-west-1'
        ]);

        $cfClient->createInvalidation([
            'DistributionId' =>$this->app->config->get("hosting.cfdistid"),
            "InvalidationBatch" => [
                "CallerReference" => time(),
                "Paths" => [
                    "Items" => $invalidations,
                    "Quantity" => count($invalidations)
                ]
            ]
        ]);
    }


    public function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->executeWrapper($input, $output, function ($input, $output) {
            $this->app->config->set("filesystems.default", "cdn");
            $invalidations = $this->uploadFiles($this->download_dir . "/public");
            $invalidations = array_merge($invalidations, $this->uploadFiles($this->download_dir . "/public/dist"));
            $this->runInvalidations($invalidations);
        });    
    }

}
