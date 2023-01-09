<?php

namespace GenerCodeCmd;

class UploadCommand extends GenericCommand
{
    protected $description = 'Uploads the current directory to reset current files';
    protected $signature = "gc:upload";



    public function zipFiles($zip, $dir)
    {
        $zip->addEmptyDir($dir);

        if (!file_exists($this->download_dir . "/" . $dir)) return;
        // Create recursive directory iterator
        /** @var SplFileInfo[] $files */
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->download_dir . "/" . $dir,
                \RecursiveDirectoryIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        

        foreach ($files as $name => $file) {
            // Skip directories (they would be added automatically)

            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($this->download_dir));
            $relativePath = ltrim(str_replace("\\", "/", $relativePath), "/");

            if (!$file->isDir()) {
                $zip->addFile($filePath, $relativePath);
            } else {
                $zip->addEmptyDir($relativePath);
            }
        }
    }

    public function handle()
    {
        try {
            $zip_name = $this->download_dir . "/project.zip";
       
            $this->info('Zipping files');

            $zip = new \ZipArchive();
            $zip->open($zip_name, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            $this->zipFiles($zip, "api");
            $this->zipFiles($zip, "migrations");
            $this->zipFiles($zip, "public");
            $this->zipFiles($zip, "tests");
            $this->zipFiles($zip, "meta");
            $this->zipFiles($zip, "bin");

            // Zip archive will be created only after closing object
            $zip->close();
            $this->info('Uploading directory');
            $this->info($this->http->pushAsset("/asset/projects/src/" . $this->project_id, "src", $zip_name));
            //unlink($zip_name);
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
