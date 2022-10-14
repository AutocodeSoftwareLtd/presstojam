<?php
namespace GenerCodeDev;

class CustomFile {

    private $client;
    private $project_id;

    function __construct($client, $project_id) {
        $this->client = $client;
        $this->project_id = $project_id;
    }


    function download($dir) {
        $blob = $this->client->get("/asset/projects/custom-file/" . $this->project_id);
     
        file_put_contents($dir . "/custom.php", (string) $blob);

    }

    function upload($dir) {
        $blob = file_get_contents($dir . "/custom.php");
        $this->client->pushAsset("/asset/projects/custom-file/" . $id, $blob);
    }
}