<?php


namespace vektah\react_web;

use React\Dns\Resolver\Factory as DnsFactory;
use React\Dns\Resolver\Resolver;
use React\HttpClient\Client;
use React\HttpClient\Factory as HttpClientFactory;
use React\EventLoop\LoopInterface;
use vektah\composer\cache\Config;

class LoopContext {
    /** @var LoopInterface */
    private $loop;

    /** @var  Resolver */
    private $dns;

    private $http_client;

    function __construct($loop)
    {
        $this->loop = $loop;
    }

    /**
     * @return \React\EventLoop\LoopInterface
     */
    public function getLoop()
    {
        return $this->loop;
    }

    /**
     * @return Resolver
     */
    public function getDns() {
        if (!$this->dns) {
            $factory = new DnsFactory();
            $this->dns = $factory->createCached(Config::instance()->dns, $this->getLoop());
        }

        return $this->dns;
    }

    /**
     * @return Client
     */
    public function getHttpClient() {
        if (!$this->http_client) {
            $factory = new HttpClientFactory();
            $this->http_client = $factory->create($this->getLoop(), $this->getDns());
        }

        return $this->http_client;
    }
}
