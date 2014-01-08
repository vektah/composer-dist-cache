<?php


namespace vektah\composer\cache\controller;

use vektah\common\json\Json;
use vektah\composer\cache\Mirror;
use vektah\composer\cache\HashStore;
use vektah\react_web\CachedRemote;

class Packages {
    private $context;

    function __construct($context, HashStore $hash_store, Mirror $mirror)
    {
        $this->context = $context;
        $this->hash_store = $hash_store;
        $this->mirror = $mirror;
    }

    public function packages() {
        return $this->mirror->get_packages_json();
    }

    public function provider(array $matches) {
        $remote_hash = $this->hash_store->get_remote_provider_include_hash($matches['provider'], $matches['hash']);
        return $this->mirror->get_provider_include($matches['provider'], $remote_hash);
    }

    public function package(array $matches) {
        $hash = isset($matches['hash']) ? $this->hash_store->get_remote_package_hash($matches['vendor'], $matches['package'], $matches['hash']) : null;
        return $this->mirror->get_package($matches['vendor'], $matches['package'], $hash);
    }
} 
