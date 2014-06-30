<?php


namespace vektah\composer\cache;

use React\Promise\Deferred;
use vektah\common\json\InvalidJsonException;
use vektah\common\json\Json;
use vektah\react_web\LoopContext;
use vektah\react_web\ThrottledTaskPool;

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

        return $this->cache[$remote]->get($context)->then(function($result) use ($remote) {
            try {
                return Json::decode($result);
            } catch (InvalidJsonException $e) {
                $this->flush($remote);
                throw $e;
            }
        });
    }

    public function get_local_package_hash($vendor, $package, $remote_hash = null) {
        $deferred = new Deferred();

        if ($hash = $this->hash_store->get_local_package_hash($vendor, $package, $remote_hash)) {
            $deferred->resolve($hash);
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
            $deferred->resolve($hash);
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

        $this->get("/p/provider-$include_name$remote_hash.json", $this->context)->then(function($data) use ($deferred, $include_name) {
            $pool = new ThrottledTaskPool($this->context->getLoop(), Config::instance()->concurrency);

            foreach ($data['providers'] as $provider_name => $provider_data) {
                $pool->add($provider_name, function() use ($provider_name, $provider_data) {
                    list($vendor, $package) = explode('/', $provider_name, 2);

                    return $this->get_local_package_hash($vendor, $package, $provider_data['sha256']);
                });
            }

            $pool->on('end', function($result) use ($deferred, $data) {
                foreach ($result as $provider_name => $sha) {
                    $data['providers'][$provider_name]['sha256'] = $sha;
                }

                $deferred->resolve($data);
            });
        });

        return $deferred->promise();
    }

    public function get_packages_json() {
        $deferred = new Deferred();
        $this->get("/packages.json", $this->context, 10)->then(function($data) use ($deferred) {
            $pool = new ThrottledTaskPool($this->context->getLoop(), Config::instance()->concurrency);

            $pool->on('end', function($result) use ($deferred, $data) {
                foreach ($result as $provider_name => $sha) {
                    $data['provider-includes'][$provider_name]['sha256'] = $sha;
                }

                $deferred->resolve($data);
            });

            foreach ($data['provider-includes'] as $provider_name => $provider_data) {
                $pool->add($provider_name, function() use ($provider_name, $provider_data) {
                    $short_name = str_replace('p/provider-', '', $provider_name);
                    $short_name = str_replace('.json', '', $short_name);
                    $short_name = explode('$', $short_name, 2)[0];
                    return $this->get_local_provider_include_hash($short_name, $provider_data['sha256']);
                });
            }
        });

        return $deferred->promise();
    }

    public function get_package($vendor, $package, $remote_hash = null) {
        $remote_hash = $remote_hash !== null ? "\$$remote_hash" : '';
        $ttl = $remote_hash ? 3600 : 10;
        return $this->get("/p/$vendor/$package$remote_hash.json", $this->context, $ttl)->then(function($packages) {
            foreach ($packages['packages'] as $package_name => &$package) {
                foreach ($package as $version => &$version_data) {
                    $version_data['dist'] = [
                        'type' => 'zip',
                        'url' => Config::instance()->baseurl . "dist/$package_name/$version/{$version_data['source']['reference']}.zip",
                        'reference' => $version
                    ];
                }
            }

            return $packages;
        });
    }

    public function flush($remote) {
        if (isset($this->cache[$remote])) {
            $this->cache[$remote]->flush();
        }
    }
} 
