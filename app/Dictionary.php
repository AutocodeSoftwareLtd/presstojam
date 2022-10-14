<?php

namespace GenerCodeDev;

class Dictionary {

    private $client;
    private $project_id;

    function __construct($client, $project_id, $download_dir) {
        $this->client = $client;
        $this->project_id = $project_id;
        $this->download_dir = trim($download_dir, "/");
    }

    function download($id) {
        $complete=false;
        while (!$complete) {
            $res = $this->client->get("/data/dictionary-templates/active", ["--id"=>$id]);
            $complete = ($res['process']) ? false : true;
            if ($complete) {
                $lang = $res['language'];
                $body = $this->client->get("/asset/dictionary-templates/template/" . $id);
                echo "Writing to " . $this->download_dir . "/dict_" . $lang . ".json";
                file_put_contents($this->download_dir . "/dict_" . $lang . ".json", (string) $body);
                exit;
            }
            sleep(2);
        }
    }


    function createLanguage($lang) {
        $res = $this->client->post("/data/dictionary-templates", ["--parent"=>  $this->project_id, "language"=>$lang, "process"=>true]);
        $this->download($res["--id"]);
    }

    function updateLanguage($lang) {
        $res = $this->client->get("/data/dictionary-templates/parent", ["--parent"=>$this->project_id, "language"=>$lang, "__limit"=>1]);
       
        $id = $res["--id"];

        $file = file_get_contents($this->download_dir . "/dict_" . $lang.  ".json");
       
        $res = $this->client->pushAsset("/asset/dictionary-templates/template/" . $id, $file);
        $this->client->put("/data/dictionary-templates", ["--id"=>$id, "process"=> true]);
        $complete=false;
        while (!$complete) {
            $res = $this->client->get("/data/dictionary-templates/active", ["--id"=>$id]);
            
            $complete = ($res['process']) ? false : true;
            if ($complete) {
               echo "Completed";
               exit;
            }
            sleep(2);
        }
    }


    function resetLanguage($lang) {
        $ids=$this->client->get("/data/dictionary-templates/parent", ["--parent"=>$this->project_id, "language"=>$lang, "__limit"=>1]);
        $id = $ids["--id"];
        echo "\nGot id " . $id;
        $this->client->put("/data/dictionary-templates", ["--id"=> $id, "process"=>true]);
        $this->download($id);
    }


    function getLanguage($lang) {
       $ids = $this->client->get("/data/dictionary-templates/parent", ["--parent"=>$this->project_id, "language"=>$lang, "__limit"=>1]);
       $id = $ids["--id"];
       $this->download($id);
    }
}