<?php


namespace vektah\composer\cache\cli;

use React\EventLoop\Factory;
use React\Http\Request;
use React\Http\Response;
use React\Socket\Server as SocketServer;
use React\Http\Server as HttpServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use vektah\common\json\Json;
use vektah\composer\cache\controller\Packages;
use vektah\react_web\Dispatcher;
use vektah\react_web\LoopContext;

class Web extends Command {
    protected function configure()
    {
        $this->setName('web');

        $this->addArgument('hostname', InputArgument::OPTIONAL, 'The host to bind to', 'localhost');
        $this->addArgument('port', InputArgument::OPTIONAL, 'The port to bind to', 8000);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $loop = Factory::create();
        $loop_context = new LoopContext($loop);
        $dispatcher = new Dispatcher($loop_context);
        $packages = new Packages($loop_context);
        $dispatcher->add_route('/packages.json', [$packages, 'packages']);
        $dispatcher->add_route('/p/provider-{provider}${hash}.json', [$packages, 'provider']);
        $dispatcher->add_route('/p/{vendor}/{package}${hash}.json', [$packages, 'package']);
        $dispatcher->add_route('/p/{vendor}/{package}.json', [$packages, 'package']);
        $dispatcher->add_route('/h/{vendor}/{package}.json', [$packages, 'package_hash']);

        $app = function(Request $request, Response $response) use ($dispatcher) {
            $dispatcher->dispatch($request, $response);
        };

        $socket = new SocketServer($loop);
        $http = new HttpServer($socket);

        $http->on('request', $app);

        $socket->listen($input->getArgument('port'), $input->getArgument('hostname'));
        $loop->run();
    }
} 
