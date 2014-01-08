<?php

namespace vektah\composer\cache;

use vektah\common\json\Json;

class Config {
    /** @var string the upstream packagist or satis server to dump from */
    public $upstream = 'http://packagist.org';

    /** @var string The url that this site is known by */
    public $baseurl = 'http://testing.vm:1234/';

    /** @var string the dns server to use for async requests */
    public $dns = '8.8.8.8';

    /** @var int The number of concurrent tasks PER INCLUDE. This means it will probably get multiplied by 4. This is only really advantageous during warmup */
    public $concurrency = 4;

    public $hostname = 'localhost';

    public $port = '8000';

    public $proxy = null;

    private static $instance;

    public static function instance() {
        if (!self::$instance) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    private function __construct() {
        if (file_exists($this->get_config_filename())) {
            $data = Json::decode(file_get_contents($this->get_config_filename()));

            foreach ($data as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    public function get_basedir() {
        return realpath(__DIR__ . '/../../../../../');
    }

    public function get_config_filename() {
        return $this->get_basedir() . '/config.json';
    }

    public function save() {
        file_put_contents($this->get_config_filename(), Json::pretty($this));
    }
}
