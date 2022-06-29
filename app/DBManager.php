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
        $res = $this->client->post("/data/sync-db-log", ["--parentid"=>$project_id, "complete"=>false, "dbdetails"=>["ext"=>"json", "size"=>strlen($json)]]);
        var_dump($res);
        $id = $res['--id'];
        echo "ID is " . $id;

        $res = $this->client->pushAsset("/asset/sync-db-log/dbdetails/" . $id, $json);
        var_dump($res);

        sleep(20);
        $complete=false;
        while (!$complete) {
            $res = $this->client->get("/data/sync-db-log/primary", ["--id"=>$id, "__fields"=>["--id","complete"]]);
            var_dump($res);
            if ($res['complete']) {
                echo "\nFinished";
                exit;
            }
            sleep(2);
        }
    }
}