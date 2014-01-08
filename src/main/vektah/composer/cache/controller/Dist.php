<?php


namespace vektah\composer\cache\controller;

use vektah\common\json\Json;
use vektah\composer\cache\Mirror;
use vektah\composer\cache\HashStore;
use vektah\react_web\CachedRemote;

class Dist {
    private $context;

    function __construct($context)
    {
        $this->context = $context;
    }

    public function download() {
        return [];
    }
} 
