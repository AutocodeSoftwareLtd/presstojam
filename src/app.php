#!/usr/bin/env php
<?php
// application.php

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Console\Application;

//need to create a generic container, need some laravel install
$configs = ["api_url"=>"https://api.presstojam.com"];

$application = new Application();
$application->add(new \GenerCodeDev\PublishCommand($configs));
$application->add(new \GenerCodeDev\DownloadCommand($configs));
$application->add(new \GenerCodeDev\UploadCommand($configs));
// ... register commands

$application->run();