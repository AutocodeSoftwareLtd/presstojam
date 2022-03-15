<?php

namespace PresstoJam;

class Dictionary {

    private $client;
    private $project_id;

    function __construct($client, $project_id) {
        $this->client = $client;
        $this->project_id = $project_id;
    }

    function download($id) {
        $complete=false;
        while (!$complete) {
            $res = $this->client->get("/dictionary-templates-primary", ["id"=>$id]);
            $complete = ($res['process']) ? false : true;
            if ($complete) {
                $lang = $res['language'];
                $data = $this->client->getRaw("/dictionary-templates-template", ["id"=>$id]);
                echo "Writing to " . __DIR__ . "/tmp/dict_" . $lang . ".json";
                file_put_contents(__DIR__ . "/tmp/dict_" . $lang . ".json", $data);
                exit;
            }
            sleep(2);
        }
    }


    function createLanguage($lang) {
        $response = $this->client->post("/dictionary-templates", ["projects_id"=>  $this->project_id, "language"=>$lang, "process"=>true]);
        $this->download($response["id"]);
    }

    function updateLanguage($lang) {
        $ids = $this->client->get("/dictionary-templates", ["projects_id"=>$this->project_id, "language"=>$lang]);
        $id = $ids["__data"][0]["id"];
        $blob = file_get_contents(__DIR__ . "/tmp/dict_" . $lang.  ".json");
        $this->client->pushAsset("/dictionary-templates-template", ["id"=>$id], $blob);
        $this->client->put("/dictionary-templates", ["id"=>$id, "process"=> true]);
    }


    function resetLanguage($lang) {
        $ids=$this->client->get("/dictionary-templates", ["projects_id"=>$this->project_id, "language"=>$lang]);
        $id = $ids["__data"][0]["id"];
        $res = $this->client->put("/dictionary-templates", ["template"=>"", "process"=>true, "id"=>$id]);
        $this->download($id);
    }


    function getLanguage($lang) {
       $ids = $this->client->get("/dictionary-templates", ["projects_id"=>$this->project_id, "language"=>$lang]);
       $id = $ids["__data"][0]["id"];
       $this->download($id);
    }
}