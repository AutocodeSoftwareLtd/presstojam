<?php

namespace GenerCodeCmd;

use Illuminate\Support\ServiceProvider;

class GenerCodeCmdServiceProvider extends ServiceProvider {

    public function boot() {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \GenerCodeCmd\DictionaryCommand::class,
                \GenerCodeCmd\DownloadCommand::class,
                \GenerCodeCmd\PublishCommand::class,
                \GenerCodeCmd\UploadCommand::class,
                \GenerCodeCmd\CdnCommand::class
            ]);
        }
    }
}