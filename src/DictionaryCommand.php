<?php

namespace GenerCodeCmd;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;


#[AsCommand(name: 'gc:dictionary')]
class DictionaryCommand extends GenericCommand
{
    protected static $defaultDescription = 'Creates or downloads copy of the dictionary for a given language';
    protected static $defaultName = "dictionary";



    public function configure(): void
    {
        parent::configure();
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp('Create / download dictionary')
        ;

        $this
            // ...
            ->addArgument('lang', InputArgument::REQUIRED, 'Which language do you want to work with?');
    }



    public function download($lang, $id)
    {
        $asset = $this->http->getAsset("/data/dictionary-templates/active", ["--id"=>$id]);
        file_put_contents($this->download_dir . "/dict_" . $lang . ".json");
    }


    public function createLanguage($lang)
    {
        $res = $this->client->post("/data/dictionary-templates", ["--parent"=>  $this->project_id, "language"=>$lang, "process"=>true]);
        return $res;
    }

    public function updateLanguage($lang)
    {
        $res = $this->http->pushAsset(
            "/asset/dictionary-templates/template/" . $id,
            "template",
            $this->download_dir . "/dict_" . $lang.  ".json"
        );
    }



    public function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->executeWrapper($input, $output, function ($input, $output) {
            $lang = $input->getArgument("lang");
            $obj = $this->http->get("/data/dictionary-templates", ["--parent"=>$this->project_id, "lang"=>$lang, "__limit"=>1]);
            if ($obj) {
                return;
            }

            $res = $this->createLanguage($lang);

            sleep(10);
            if ($this->processQueue($res['--dispatch-id'])) {
                $this->download($lang, $res["--id"]);
            }
        });
    }
}
