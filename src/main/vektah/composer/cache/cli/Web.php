<?php


namespace vektah\composer\cache\cli;

use React\EventLoop\Factory;
use React\Http\Request;
use React\Http\Response;
use React\Http\Server as HttpServer;
use React\Socket\Server as SocketServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use vektah\composer\cache\Config;
use vektah\composer\cache\HashStore;
use vektah\composer\cache\Mirror;
use vektah\composer\cache\controller\Dist;
use vektah\composer\cache\controller\Packages;
use vektah\react_web\LoopContext;
use vektah\react_web\ReactWeb;

class Web extends Command {
    protected function configure()
    {
        $this->setName('web');

        $this->addArgument('hostname', InputArgument::OPTIONAL, 'The host to bind to', Config::instance()->hostname);
        $this->addArgument('port', InputArgument::OPTIONAL, 'The port to bind to', Config::instance()->port);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $loop = Factory::create();
        $loop_context = new LoopContext($loop, Config::instance()->dns);
        $dispatcher = new ReactWeb($loop_context);
        $hash_store = new HashStore(Config::instance()->get_basedir() . '/cache/hash_store.json');
        $mirror = new Mirror($loop_context, $hash_store);
        $packages_controller = new Packages($loop_context, $hash_store, $mirror);
        $dist_controller = new Dist($loop_context, $mirror);
        $dispatcher->addRoute('GET', '/packages.json', [$packages_controller, 'packages']);
        $dispatcher->addRoute('GET', '/p/provider-{provider}${hash}.json', [$packages_controller, 'provider']);
        $dispatcher->addRoute('GET', '/p/{vendor}/{package}${hash}.json', [$packages_controller, 'package']);
        $dispatcher->addRoute('GET', '/p/{vendor}/{package}.json', [$packages_controller, 'package']);

        $dispatcher->addRoute('GET', '/dist/{vendor}/{package}/{version}/{hash}.zip', [$dist_controller, 'download']);
        $dispatcher->addRoute('GET', '/dist/{vendor}/{package}/{version}.zip', [$dist_controller, 'download']);

        $app = function(Request $request, Response $response) use ($dispatcher) {
            echo "----- ";
            $dispatcher->dispatch($request, $response);
        };

        $socket = new SocketServer($loop);
        $http = new HttpServer($socket);

        $http->on('request', $app);

        $hostname = $input->getArgument('hostname');
        $port = $input->getArgument('port');
        $socket->listen($port, $hostname);
        echo "Started on $hostname:$port\n";
        $loop->run();
    }
} 
