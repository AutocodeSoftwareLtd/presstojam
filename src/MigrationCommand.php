<?php

namespace GenerCodeCmd;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class MigrationCommand extends GenericCommand
{
    protected static $defaultDescription = 'Run migrations for a given language';
    protected static $defaultName = "migrate";
    protected $migration_table = "migrations";
    protected $migration_runat_col = "last_ran_at";



    public function configure(): void
    {
        parent::configure();
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp('Handle migrations')
        ;

        $this
            // ...
            ->addArgument('action', InputArgument::REQUIRED, 'Which action do you want to run?');
    }

    public function getPDO()
    {
        $conn = $this->app->get(\Illuminate\Database\Connection::class);
        return $conn->getPdo();
    }


    public function checkMigrationTable($pdo)
    {
        $res = $pdo->query("SHOW TABLES LIKE '%" . $this->migration_table . "%'");
        $obj = $res->fetch();
        if (!$obj) {
            $pdo->query("CREATE TABLE " . $this->migration_table . " (" . $this->migration_runat_col . " timestamp not null default CURRENT_TIMESTAMP)");
            $pdo->query("INSERT INTO " . $this->migration_table . " VALUES (0)");
        }
    }


    public function getLastPublished($pdo)
    {
        $stmt = $pdo->query("SELECT " . $this->migration_runat_col . " from " . $this->migration_table . " limit 1", \PDO::FETCH_ASSOC);
        $res = $stmt->fetch();
        return  $res[$this->migration_runat_col];
    }

    public function updateMigrationRanAt($pdo)
    {
        $pdo->query("UPDATE " . $this->migration_table . " SET " . $this->migration_runat_table . " = now()");
    }


    public function writeMigration($version, $contents)
    {
        file_put_contents($this->download_dir . "/migrations/" . $version . ".php");
    }

    public function getMigrationsFromLastRan($date)
    {
        //run through the migrations directory
        $files = new \RecursiveDirectoryIterator(
            $this->download_dir . "/migrations",
            \RecursiveDirectoryIterator::SKIP_DOTS
        );

        $migrations = [];
        foreach ($files as $file) {
            $time = (int) str_replace(["Version", ".php"], "", basename($file));
            if ($time > $last_published) {
                $migrations[] = "\Migrations\\" . str_replace(".php", "", basename($file));
            }
        }
        return $migrations;
    }


    public function runMigrations($pdo)
    {
        $last_published = $this->getLastPublished($pdo);

        $migrations = $this->getMigrationsFromLastRan($last_published);
        //run through the migrations directory

        foreach ($migrations as $file) {
            $migration = new $file($this->app);
            $migration->up();
            $migration->run();
        }

        $this->updateMigrationRanAt($pdo);
    }


    public function createMigrationFile($class_name, $up = [], $down = [])
    {
        $str = "<?php\n\nclass " . $class_name . " extends \GenerCodeOrm\Migration {";
        $str .= "\n\npublic function up() {";
        foreach ($up as $sql) {
            $str .= "\n\t\t\$this->addSQL(\"" . $sql . "\");";
        }
        $str .= "\n\n}";
        $str .= "\n\n\tpublic function down() {";
        foreach ($down as $sql) {
            $str .= "\n\t\t\$this->addSQL(\"" . $sql . "\");";
        }
        $str .= "\n\n\t}";
        $str .= "\n\n}";
        return $str;
    }


    public function initMigrations($pdo)
    {
        $query = $pdo->query("SHOW TABLES");
        $class_name = "Version1";
        $up = [];
        $down = [];

        foreach ($query as $table) {
            $q = $this->pdo->query("SHOW CREATE TABLE " . $table, \PDO::FETCH_NUM);
            $up[] = $q;
            $down[] = "DROP " . $table;
        }

        $str = $this->createMigrationFile("Version1", $up, $down);
        $this->writeMigration("Version1", $str);
    }


    public function buildMigration()
    {
        $class_name = "Version" . date("Ymdhis");
        $str = $this->createMigrationsFile($class_name);
        $this->writeMigration($class_name, $str);
    }

    public function syncMigrations($pdo)
    {
        $last_published = $this->getLastPublished($pdo);
        $migrations = $this->getMigrationsFromLastRan($last_published);

        $sqls = [];
        foreach ($migrations as $migration) {
            $migration = new $file($this->app);
            $migration->up();
            $sqls = array_merge($sqls, $migration->getSQLs());
        }

        $json = json_encode($sqls);
        $file = $this->app->createFile();
        $res = $this->http->post("/data/sync-db-log", ["--parentid"=>$project_id, "complete"=>false, "dbdetails"=>["ext"=>"json", "size"=>strlen($json)]]);
        $id = $res['--id'];
        echo "ID is " . $id;
        sleep(10);
        $this->processQueue($res['--dispatchid']);
        return true;
    }


    public function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->executeWrapper($input, $output, function ($input, $output) {
            $action = $input->getArgument("action");
            if ($action == "create") {
                $this->buildMigration();
            } elseif ($action == "init") {
                $this->initMigrations($this->getPDO());
            } elseif ($action == "run") {
                $this->runMigrations($this->getPDO());
            } elseif ($action == "sync") {
                $this->syncMigrations($this->getPDO());
            }
        });
    }
}
