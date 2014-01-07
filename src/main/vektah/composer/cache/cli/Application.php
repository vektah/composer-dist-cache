<?php

namespace vektah\composer\cache\cli;

use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;

class Application extends SymfonyApplication {
    function __construct()
    {
        parent::__construct();
        $this->add(new Web());
        $this->add(new GenerateConfig());
    }
}
