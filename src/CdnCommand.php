<?php

namespace GenerCodeCmd;


class CdnCommand extends GenericCommand
{
    protected $description = 'Pushes public files to cdn';
    protected $signature = "gc:cdn";
   


    public function uploadFiles($dir_path) {
        $fileHandler = new \GenerCodeOrm\FileHandler(true);
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
            'DistributionId' =>config("hosting.cfdistid"),
            "InvalidationBatch" => [
                "CallerReference" => time(),
                "Paths" => [
                    "Items" => $invalidations,
                    "Quantity" => count($invalidations)
                ]
            ]
        ]);
    }

    public function runWebpack() {
        $module_dir = config("cmd.node_modules");
        $cmd = "webpack --config /home/ec2-user/manager/webpack.config.js";
            $cmd .= " --env dir=\"" . $this->download_dir . "/public/src\"";
            $cmd .= " --env modules=\"" . $module_dir . "\"";
            $cmd .= " --env output=\"" . $this->download_dir . "/public/dist\"";
            echo "\nComamnd is " . $cmd;
            echo shell_exec($cmd);
    }

    public function handle()
    {
        try {
            $this->login();
            config("filesystems.default", "cdn");
            $this->runWebpack();
            $invalidations = $this->uploadFiles($this->download_dir . "/public");
            $invalidations = array_merge($invalidations, $this->uploadFiles($this->download_dir . "/public/dist"));
            $invalidations = array_merge($invalidations, $this->uploadFiles($this->download_dir . "/public/css"));
            $invalidations = array_merge($invalidations, $this->uploadFiles($this->download_dir . "/public/assets"));
            $invalidations = array_merge($invalidations, $this->uploadFiles($this->download_dir . "/public/css/fonts"));
            $this->runInvalidations($invalidations);
            $this->info("Files Completed");
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
