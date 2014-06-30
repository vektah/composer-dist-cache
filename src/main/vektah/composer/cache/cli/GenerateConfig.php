<?php


namespace vektah\composer\cache\cli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use vektah\composer\cache\Config;

class GenerateConfig extends Command {
    protected function configure()
    {
        $this->setName('generate-config');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        Config::instance()->save();
    }
} 
