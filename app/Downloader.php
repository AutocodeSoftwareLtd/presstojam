<?php
namespace GenerCodeDev;

class Downloader {

    private $client;
    private $project_id;

    function __construct($client, $project_id) {
        $this->client = $client;
        $this->project_id = $project_id;
    }


    function download($dir) {
        $blob = $this->client->get("/asset/projects/src/" . $this->project_id);
       
        file_put_contents($dir . "/src.zip", (string) $blob);

        $zip = new \ZipArchive();
        $zip->open($dir . "/src.zip");
        $zip->extractTo($dir);
        $zip->close();

        unlink($dir . "/src.zip");

        //then we need to unzip the file
    }
}