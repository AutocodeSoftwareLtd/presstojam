<?php

namespace PressToJam;

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
            $res = $this->client->get("/data/accounts/dictionary-templates/primary", ["--id"=>$id]);
            
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
        $res = $this->client->post("/data/accounts/dictionary-templates", ["--parentid"=>  $this->project_id, "language"=>$lang, "process"=>true]);
        $this->download($res["id"]);
    }

    function updateLanguage($lang) {
        $res = $this->client->get("/data/accounts/dictionary-templates/parent", ["--parentid"=>$this->project_id, "language"=>$lang, "__limit"=>1]);
        $id = $res["--id"];
        echo " ID is " . $id;
        $blob = file_get_contents($this->download_dir . "/dict_" . $lang.  ".json");
        $res = $this->client->pushAsset("/asset/dictionary-templates/template/" . $id, $blob);
        var_dump($res);
        $this->client->put("/data/accounts/dictionary-templates", ["--id"=>$id, "process"=> true]);
        echo "\nProcessing";
        $complete=false;
        while (!$complete) {
            $res = $this->client->get("/data/accounts/dictionary-templates/primary", ["--id"=>$id]);
            
            $complete = ($res['process']) ? false : true;
            if ($complete) {
               echo "Completed";
               exit;
            }
            sleep(2);
        }
    }


    function resetLanguage($lang) {
        $ids=$this->client->get("/data/accounts/dictionary-templates/parent", ["--parentid"=>$this->project_id, "language"=>$lang, "__limit"=>1]);
        $id = $ids["--id"];
        echo "\nGot id " . $id;
        $this->client->pushAsset("/asset/dictionary-templates/template/" . $id, [], "");
        echo "\nReset template";
        $res = $this->client->put("/data/accounts/dictionary-templates", ["process"=>1, "--id"=>$id]);
        echo "\nProcessing";
        $this->download($id);
        echo "\nReset";
    }


    function getLanguage($lang) {
       $ids = $this->client->get("/data/accounts/dictionary-templates/parent", ["--parentid"=>$this->project_id, "language"=>$lang, "__limit"=>1]);
       $id = $ids["--id"];
       $this->download($id);
    }
}