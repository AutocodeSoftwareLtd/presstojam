<?php

namespace PressToJam;

class DBManager {

    private $pdo;
    private $client;

    function __construct($client) {
        $this->pdo = \PressToJamCore\Configs\Factory::createPDO();
        $this->client = $client;
    }


    function getTables() {
		$jtables=array();
        $tables = $this->pdo->query("SHOW TABLES");
		foreach($tables as $table)
		{
			$tablename = $table[0];
			$obj = new \StdClass;
			
			$obj->name = $tablename;
            $obj->db_cols = [];
								
			$inqu =$this->pdo->query("DESCRIBE ".$tablename);

			foreach ($inqu as $arr) {
                
				$default = (strtoupper($arr['Default']) == "NULL") ? "" : $arr['Default'];
				$col = new \StdClass;
				$col->name = $arr['Field'];
				$col->type = str_replace("'", "", $arr['Type']);
                $col->indexes = [];

				if ($arr['Null'] == "NO") $col->required = true;
				if ($arr['Extra'] == "auto_increment") $col->increment=true;
				if ($arr['Key'] == "PRI") {
                    $col->is_primary = true;
                } 

				if (strpos($default, "current_timestamp") !== false) 
				{
					$col->default = "CURRENT_TIMESTAMP";
					if (strpos($arr['Extra'], "current_timestamp") !== false) $col->default .= " ON UPDATE CURRENT_TIMESTAMP";
				}
				else if ($default !== "" AND $default !== NULL) $col->default =$default;

				$obj->db_cols[] = $col;
			}
			
            //check for unique attributes
			$inqu = $this->pdo->query("SHOW INDEXES FROM " . $tablename);
			foreach ($inqu as $arr) {
                if ($arr['Non_unique'] == 0 AND $arr['Key_name'] != "PRIMARY") {
				    foreach($obj->db_cols as $col)
				    {
                        if ($col->name == $arr['Column_name']) {
                            if (count($col->indexes) == 0) {
                                //don't set unique if already has an index
                                $col->is_unique = true;
                            }
                            break;
                        }
					}
				}
			}

			$db = $this->pdo->query("select database()");
			$dbname = "";
			foreach($db as $row) {
				$dbname = $row[0];
			}
		

			$inqu = $this->pdo->query("SELECT 
			REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,TABLE_NAME,COLUMN_NAME,CONSTRAINT_NAME, REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME
		  FROM
			INFORMATION_SCHEMA.KEY_COLUMN_USAGE
		  WHERE
			TABLE_SCHEMA = '$dbname' AND TABLE_NAME='$tablename' AND REFERENCED_TABLE_NAME is not null");
			
			foreach($inqu as $arr) {
				$col_name = $arr['COLUMN_NAME'];

				foreach($obj->db_cols as $col) {
					if ($col->name == $col_name AND $arr['REFERENCED_TABLE_NAME']) {
                        $col->is_parent = true;
						$col->parent_ref_table = $arr['REFERENCED_TABLE_NAME'];
						$col->parent_ref_col = $arr['REFERENCED_COLUMN_NAME'];
						break;
					}
				}
			}

            $jtables[] = $obj;
		}


		return $jtables;
    }


    function sync($project_id, $explain = false) {
        $tables = $this->getTables();
        $json = json_encode($tables);
        $this->client->debug = true;
        $res = $this->client->post("/sync-db-log", ["projects-id"=>$project_id, "dbdetails"=>["ext"=>"json", "size"=>strlen($json)]]);
        $id = $res['id'];
        $this->client->pushAsset("/sync-db-log-dbdetails", ["id"=>$id], $json);
        sleep(15);
        $complete=false;
        while(!$complete) {
            $res = $this->client->get("/sync-db-log-primary", ["id"=>$id]);
            $complete = $res['complete'];
            if ($complete) {
                $data = $this->client->getAsset("/sync-db-log-dbdetails", ["id"=>$id]);
                $exp = explode("\n", $data);
                return $exp;
            }
            sleep(2);
        }
    }
}