<?php


namespace vektah\composer\cache;

use vektah\common\json\Json;

class HashStore {
    private $filename;

    private $data = [
        'package_hashes' => [],
        'package_hashes_reversed' => [],
        'provider_include_hashes' => [],
        'provider_include_hashes_reversed' => []
    ];

    function __construct($filename)
    {
        $this->filename = $filename;
        if (file_exists($filename)) {
            $this->data = Json::decode(file_get_contents($filename));
        }
    }

    public function get_local_package_hash($vendor, $package, $remote_hash = null) {
        if (!isset($this->data['package_hashes'][$vendor][$package][$remote_hash])) {
            return null;
        }

        return $this->data['package_hashes'][$vendor][$package][$remote_hash];
    }

    public function get_remote_package_hash($vendor, $package, $remote_hash = null) {
        if (!isset($this->data['package_hashes_revesed'][$vendor][$package][$remote_hash])) {
            return null;
        }

        return $this->data['package_hashes_revesed'][$vendor][$package][$remote_hash];
    }

    public function set_local_package_hash($vendor, $package, $remote_hash, $local_hash) {
        if (!isset($this->data['package_hashes'][$vendor])) {
            $this->data['package_hashes'][$vendor] = [];
        }

        if (!isset($this->data['package_hashes'][$vendor][$package])) {
            $this->data['package_hashes'][$vendor][$package] = [];
        }

        $this->data['package_hashes'][$vendor][$package][$remote_hash] = $local_hash;
        $this->data['package_hashes_reversed'][$vendor][$package][$local_hash] = $remote_hash;
        $this->save();
    }

    public function get_local_provider_include_hash($provider_include, $remote_hash)
    {
        if (!isset($this->data['provider_include_hashes'][$provider_include][$remote_hash])) {
            return null;
        }

        return $this->data['provider_include_hashes'][$provider_include][$remote_hash];
    }


    public function get_remote_provider_include_hash($provider, $hash)
    {
        if (!isset($this->data['provider_include_hashes_reversed'][$provider][$hash])) {
            return null;
        }

        return $this->data['provider_include_hashes_reversed'][$provider][$hash];
    }


    public function set_local_provider_include_hash($provider_include, $remote_hash, $local_hash) {
        if (!isset($this->data['provider_include_hashes'][$provider_include])) {
            $this->data['provider_include_hashes'][$provider_include] = [];
            $this->data['provider_include_hashes_reversed'][$provider_include] = [];
        }

        $this->data['provider_include_hashes'][$provider_include][$remote_hash] = $local_hash;
        $this->data['provider_include_hashes_reversed'][$provider_include][$local_hash] = $remote_hash;
        $this->save();
    }

    public function save() {
        // Still not sure if this should be cached to disk or just ram...
        file_put_contents($this->filename, Json::pretty($this->data));
    }
} 
