<?php

namespace GenerCodeCmd;

class DictionaryCommand extends GenericCommand
{
    protected $description = 'Creates or downloads copy of the dictionary for a given language';
    protected $signature = "gc:dictionary {lang : Which language do you want to work with?}";



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



    public function handle()
    {
        try {
            $lang = $this->argument("lang");
            $obj = $this->http->get("/data/dictionary-templates", ["--parent"=>$this->project_id, "lang"=>$lang, "__limit"=>1]);
            if ($obj) {
                return;
            }

            $res = $this->createLanguage($lang);

            sleep(10);
            if ($this->processQueue($res['--dispatch-id'])) {
                $this->download($lang, $res["--id"]);
            }
            $this->info("Language Created");
        } catch(\GenerCodeClient\ApiErrorException $e) {
            $this->error($e->getMessage());
            return 1;
          //  $output->writeln($e->getDetails());
        } catch (\Exception $e) {
            $this->error($e->getFile() . ": " . $e->getLine() . "\n" . $e->getMessage());
            return 2;
        }
    }
}
