<?php

namespace vektah\composer\cache\cli;

use Symfony\Component\Console\Application as SymfonyApplication;

class Application extends SymfonyApplication {
    function __construct()
    {
        parent::__construct();
        $this->add(new Web());
        $this->add(new GenerateConfig());
    }
}
