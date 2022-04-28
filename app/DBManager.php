<?php

namespace PressToJam;

class DBManager {

    private $client;

    function __construct($client) {
        
        $this->client = $client;
    }


    function getTables() {
        $pdo = \PressToJamCore\Configs\Factory::createPDO();
		$jtables=array();
        $tables = $pdo->query("SHOW TABLES");
        foreach ($tables as $table) {
            $tablename = $table[0];
            $obj = new \StdClass;
            
            $obj->name = $tablename;
            $obj->db_cols = [];
                                
            $inqu =$pdo->query("SHOW CREATE TABLE ".$tablename);

            foreach ($inqu as $arr) {
                $jtables[$tablename] = $arr["Create Table"];
            }
        }
		return $jtables;
    }


    function importDumpFile($file) {
        $jtables = [];
        $str = file_get_contents($file);
        $matches = [];
        preg_match_all(
            "/CREATE TABLE[^;]+;/",
            $str,
            $matches,
            \PREG_PATTERN_ORDER);


        foreach($matches[0] as $stmt) {
            $names = [];
            preg_match("/CREATE TABLE `([a-zA-Z0-9_]+)`/",
            $stmt,
            $names);

            if (!$names) {
                echo "Couldn't find table name for " . $stmt;
                exit;
            }

            $jtables[$names[1]] = $stmt;
        }
        return $jtables;
    }


    function sync($project_id, $file = null) {
        if ($file) $tables = $this->importDumpFile($file);
        else $tables = $this->getTables();
        $json = json_encode($tables);
        $this->client->debug = true;
        $res = $this->client->post("/sync-db-log", ["projects-id"=>$project_id, "complete"=>false, "dbdetails"=>["ext"=>"json", "size"=>strlen($json)]]);
        $id = $res['__key'];
        $res = $this->client->pushAsset("/sync-db-log-dbdetails", ["id"=>$id], $json);
        var_dump($res);

        sleep(20);
        $complete=false;
        while (!$complete) {
            $res = $this->client->get("/sync-db-log", ["id"=>$id, "__fields"=>["id","complete"]]);
            if ($res['complete']) {
                echo "\nFinished";
                exit;
            }
            sleep(2);
        }
    }
}