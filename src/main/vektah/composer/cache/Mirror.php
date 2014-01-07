<?php


namespace vektah\composer\cache;

use React\Promise\Deferred;
use React\Promise\When;
use vektah\common\json\Json;
use vektah\react_web\CachedRemote;
use vektah\react_web\LoopContext;

class Mirror {
    private $context;

    /** @var CachedRemote[] */
    private $cache = [];

    /** @var HashStore */
    private $hash_store;

    function __construct(LoopContext $context, HashStore $hash_store)
    {
        $this->context = $context;
        $this->hash_store = $hash_store;
    }

    private function get($remote, LoopContext $context, $ttl = 36000) {
        if (!isset($this->cache[$remote])) {
            $upstream = Config::instance()->upstream;
            $this->cache[$remote] = new CachedRemote($upstream . $remote, $ttl);
        }

        return $this->cache[$remote]->get($context);
    }

    public function get_local_package_hash($vendor, $package, $remote_hash = null) {
        $deferred = new Deferred();

        if ($hash = $this->hash_store->get_local_package_hash($vendor, $package, $remote_hash)) {
            return $deferred->resolve($hash);
        } else {
            $this->get_package($vendor, $package, $remote_hash)->then(function ($package_data) use ($vendor, $package, $remote_hash, $deferred) {
                $local_hash = hash('sha256', Json::pretty($package_data));
                $this->hash_store->set_local_package_hash($vendor, $package, $remote_hash, $local_hash);
                $deferred->resolve($local_hash);
            });
        }

        return $deferred->promise();
    }

    public function get_local_provider_include_hash($provider_name, $remote_hash = null) {
        $deferred = new Deferred();

        if ($hash = $this->hash_store->get_local_provider_include_hash($provider_name, $remote_hash)) {
            return $deferred->resolve($hash);
        } else {
            $this->get_provider_include($provider_name, $remote_hash)->then(function ($package) use ($deferred, $provider_name, $remote_hash) {
                $hash = hash('sha256', Json::pretty($package));
                $this->hash_store->set_local_provider_include_hash($provider_name, $remote_hash, $hash);
                $deferred->resolve($hash);
            });
        }

        return $deferred->promise();
    }

    public function get_provider_include($include_name, $remote_hash) {
        $remote_hash = $remote_hash !== null ? "\$$remote_hash" : '';
        $deferred = new Deferred();

        $this->get("/p/provider-$include_name$remote_hash.json", $this->context)->then(function($provider_include) use ($deferred) {
            $data = Json::decode($provider_include);

            // Don't want too many concurrent requests going off here... One or two per provider include should be ok though
            $pending_requests = $data['providers'];

            $process_next_request = function() use (&$pending_requests, &$data, $deferred, &$process_next_request) {
                echo "Processing request.\n";
                // Nothing left to do!
                if (empty($pending_requests)) {
                    $deferred->resolve($data);
                }
                $provider_data = reset($pending_requests);
                $provider_name = key($pending_requests);
                unset($pending_requests[$provider_name]);
                list($vendor, $package) = explode('/', $provider_name, 2);

                $this->get_local_package_hash($vendor, $package, $provider_data['sha256'])->then(function($new_hash) use (&$data, $provider_name, $process_next_request) {
                    $data['providers'][$provider_name]['sha256'] = $new_hash;

                    $process_next_request();
                });
            };

            for ($i = 0; $i < Config::instance()->concurrency; $i++) {
                $process_next_request();
            }
        });

        return $deferred->promise();
    }

    public function get_packages_json() {
        return $this->get("/packages.json", $this->context, 30)->then(function($provider_include) {
            $data = Json::decode($provider_include);
            $new_hashes = [];

            foreach ($data['provider-includes'] as $provider_name => $provider_data) {
                $short_name = str_replace('p/provider-', '', $provider_name);
                $short_name = str_replace('.json', '', $short_name);
                $short_name = explode('$', $short_name, 2)[0];
                $new_hashes[$provider_name] = $this->get_local_provider_include_hash($short_name, $provider_data['sha256']);
            }

            return When::all($new_hashes, function($new_hashes) use ($data) {
                foreach ($new_hashes as $provider_name => $hash) {
                    $data['provider-includes'][$provider_name]['sha256'] = $hash;
                }

                return $data;
            });
        });
    }

    public function get_package($vendor, $package, $remote_hash = null) {
        $remote_hash = $remote_hash !== null ? "\$$remote_hash" : '';
        return $this->get("/p/$vendor/$package$remote_hash.json", $this->context)->then(function($package) {
            return Json::decode($package);
        });
    }
} 
