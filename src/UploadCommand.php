<?php

namespace GenerCodeCmd;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


#[AsCommand(name: 'gc:upload')]
class UploadCommand extends GenericCommand
{
    protected static $defaultDescription = 'Uploads the current directory to reset current files';
    protected static $defaultName = "gc:upload";

    public function configure(): void
    {
        parent::configure();
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp('Upload directory, overwriting static files such as index and public profile routes')
        ;
    }


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
            $relativePath = substr($filePath, strlen($this->download_dir) + 1);
            $relativePath = str_replace("\\", "/", $relativePath);

            if (!$file->isDir()) {
                $zip->addFile($filePath, $relativePath);
            } else {
                $zip->addEmptyDir($relativePath);
            }
        }
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->executeWrapper($input, $output, function ($input, $output) {
            $zip_name = $this->download_dir . "/project.zip";
            echo "|ip name is " . $zip_name;

            $output->writeln('Zipping files');

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
            $output->writeln('Uploading directory');
            $output->writeln($this->http->pushAsset("/asset/projects/src/" . $this->project_id, "src", $zip_name));
            //unlink($zip_name);
        });
    }
}
