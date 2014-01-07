<?php


namespace vektah\react_web;

use React\Promise\Deferred;
use React\Promise\FulfilledPromise;
use vektah\common\json\InvalidJsonException;
use vektah\common\json\Json;

class CachedRemote {
    private $last_modified = 0;
    private $cached;
    private $ttl;
    private $remote;
    private $local_name;

    function __construct($remote, $ttl = 30)
    {
        $this->remote = $remote;
        $this->ttl = $ttl;
        $local_name = preg_replace('|http[s]?://|', '', $remote);
        $local_name = str_replace('/', '_', $local_name);
        $dir = __DIR__ . '/../../../../cache/';
        if (!file_exists($dir)) {
            mkdir($dir);
        }

        $this->local_name = $dir . $local_name;

        if (file_exists($this->local_name)) {
            $this->cached = file_get_contents($this->local_name);
            $this->last_modified = filemtime($this->local_name);
        }
    }

    public function get(LoopContext $context) {
        if ($this->last_modified > time() - $this->ttl) {
            echo "Serving cached {$this->remote}\n";
            return new FulfilledPromise($this->cached);
        }

        $deferred = new Deferred();

        $client = $context->getHttpClient();
        echo "Requesting {$this->remote}\n";
        $request = $client->request('GET', $this->remote);
        $request->on('response', function($response) use ($deferred) {
            $buffer = '';

            $response->on('data', function($data) use (&$buffer) {
                $buffer .= $data;
            });

            $response->on('end', function() use (&$buffer, $deferred) {
                file_put_contents($this->local_name, $buffer);
                $this->last_modified = time();
                $this->cached = $buffer;
                $deferred->resolve($this->cached);
                echo "Downloaded {$this->remote}\n";
            });
        });

        $request->on('end', function($error) {
            echo $error;
        });

        $request->end();

        return $deferred->promise();
    }
} 
