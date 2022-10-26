<?php

namespace GenerCodeDev;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DownloadCommand extends GenericCommand
{
    protected static $defaultDescription = 'Downloads copy of API without repblushing';
    protected static $defaultName = "download";

    public function configure(): void
    {
        parent::configure();
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp('This command downloads the last published version of your files')
        ;
    }


    public function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->executeWrapper($input, $output, function ($input, $output) {
            $blob = $this->http->get("/asset/projects/src/" . $this->project_id);

            file_put_contents($this->download_dir . "/src.zip", (string) $blob);

            $zip = new \ZipArchive();
            $zip->open($this->download_dir . "/src.zip");
            $zip->extractTo($this->download_dir);
            $zip->close();

            unlink($this->download_dir . "/src.zip");

            //then we need to unzip the file
        });
    }
}
