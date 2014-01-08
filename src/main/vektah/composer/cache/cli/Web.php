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
use vektah\composer\cache\Config;
use vektah\composer\cache\controller\Dist;
use vektah\composer\cache\controller\Packages;
use vektah\composer\cache\HashStore;
use vektah\composer\cache\Mirror;
use vektah\react_web\Dispatcher;
use vektah\react_web\LoopContext;

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
        $loop_context = new LoopContext($loop);
        $dispatcher = new Dispatcher($loop_context);
        $hash_store = new HashStore(Config::instance()->get_basedir() . '/cache/hash_store.json');
        $mirror = new Mirror($loop_context, $hash_store);
        $packages_controller = new Packages($loop_context, $hash_store, $mirror);
        $dist_controller = new Dist($loop_context, $mirror);
        $dispatcher->add_route('/packages.json', [$packages_controller, 'packages']);
        $dispatcher->add_route('/p/provider-{provider}${hash}.json', [$packages_controller, 'provider']);
        $dispatcher->add_route('/p/{vendor}/{package}${hash}.json', [$packages_controller, 'package']);
        $dispatcher->add_route('/p/{vendor}/{package}.json', [$packages_controller, 'package']);

        $dispatcher->add_route('/dist/{vendor}/{package}/{version}/{hash}.zip', [$dist_controller, 'download']);
        $dispatcher->add_route('/dist/{vendor}/{package}/{version}.zip', [$dist_controller, 'download']);

        $app = function(Request $request, Response $response) use ($dispatcher) {
            echo "----- ";
            $dispatcher->dispatch($request, $response);
        };

        $socket = new SocketServer($loop);
        $http = new HttpServer($socket);

        $http->on('request', $app);

        $socket->listen($input->getArgument('port'), $input->getArgument('hostname'));
        $loop->run();
    }
} 
