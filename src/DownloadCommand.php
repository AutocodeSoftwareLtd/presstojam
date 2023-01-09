<?php

namespace GenerCodeCmd;

class DownloadCommand extends GenericCommand
{
    protected $description = 'Downloads copy of API without repblushing';
    protected $signature = "gc:download";

   


    public function handle()
    {
        try {
            $blob = $this->http->get("/asset/projects/src/" . $this->project_id);

            file_put_contents($this->download_dir . "/src.zip", (string) $blob);

            $zip = new \ZipArchive();
            $zip->open($this->download_dir . "/src.zip");
            $zip->extractTo($this->download_dir);
            $zip->close();

            unlink($this->download_dir . "/src.zip");
            $this->info("Upload Completed");

            //then we need to unzip the file
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
