<?php

namespace PressToJam;

class FileDetails {
    public $file;
    public $path;
    public $atime;
    public $size;
}

class UploadDirectory {

    private $config;
    private $client;

    function __construct($client, $config) {
        $this->client = $client;
        $this->config = $config;
    }

    function getRelativeFileName($file) {
        $file = str_replace("\\", "/", $file);
        $dir = str_replace("\\", "/", $this->config->dir);
        return str_replace($dir, "", $file);
    }

    function scanFileTree() {
        $tree=new \StdClass;
        $it = new \RecursiveDirectoryIterator($this->config->dir, \FilesystemIterator::SKIP_DOTS);
        foreach(new \RecursiveIteratorIterator($it) as $file) {
            if ($file->getType() == "link" OR $file->getType() == "dir") continue;
            $details = new FileDetails();
            $details->file = $file->getRealPath();
            $details->atime = $file->getATime();
            $details->size = $file->getSize();
            $uri = $this->getRelativeFilename($details->file);
            $tree->$uri = $details;
        }
        return $tree;
    }

    function loadTree() {
        $file = $this->config->tree_file;
        if (!file_exists($file)) return new \StdClass;
        return json_decode(file_get_contents($file));
    }

    function saveTree($tree) {
        file_put_contents($this->config->tree_file, json_encode($tree));
    }


    function scanFiles() {
        $files=[];
        $tree = $this->loadTree();
        $latest_tree = $this->scanFileTree();

        foreach($latest_tree as $uri => $file) {
            if (!property_exists($tree, $uri)) $files[$uri] = $file;
            else if ($tree->$uri->atime != $file->atime) $files[$uri] = $file;
        }

        foreach($tree as $uri=>$file) {
            if (!property_exists($latest_tree, $uri)) $files[$uri] = null;
        }

        $this->saveTree($latest_tree);

        return $files;
    }


    function zipFiles($files) {
        $zip = new \ZipArchive();
        $zip->open($this->config->zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach($files as $uri => $file) {
            $zip->addFromString($uri, file_get_contents($file->file));
        }
        $zip->close();
    }

    function run() {
        $files = $this->scanFiles();
        $this->zipFiles($files);
        $this->client->post("/custom-import-directory", ["blob"=> file_get_contents($this->config->zip_file)]);
        unlink($this->config->zip_file);
    }
}