<?php

namespace GenerCodeCmd;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;


#[AsCommand(name: 'gc:migration')]
class MigrationCommand extends GenericCommand
{
    protected static $defaultDescription = 'Run migrations for a given language';
    protected static $defaultName = "gc:migrate";
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


    public function getLastPublished($pdo)
    {
        $stmt = $pdo->query("SELECT " . $this->migration_runat_col . " from " . $this->migration_table . " limit 1", \PDO::FETCH_ASSOC);
        $res = $stmt->fetch();
        return  $res[$this->migration_runat_col];
    }


    public function runMigrations()
    {
        $migrator = $this->app->get("migrator");
        $migrator->run([$this->app->config->get("migration.repository")]);

    }


    public function createTable() {
        $sql = "CREATE TABLE `migrations` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `migration` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
            `batch` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`)
          ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
        
        $conn = $this->app["db"];
        $db = $conn->statement($conn->raw($sql));
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
                $this->runMigrations();
            } elseif ($action == "sync") {
                $this->syncMigrations($this->getPDO());
            }
        });
    }
}
