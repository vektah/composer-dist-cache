<?php

namespace vektah\react_web;

use React\EventLoop\LoopInterface;
use React\Http\Request;
use React\Http\Response;
use React\Promise\PromiseInterface;
use vektah\common\json\Json;
use vektah\react_web\response\ControllerResponse;
use vektah\react_web\response\InternalServerError;
use vektah\react_web\response\PageNotFound;

/**
 * This should be replaced with somthing annotation driven..
 */
class Dispatcher {
    private $routes = [];
    private $loop;

    public function __construct(LoopContext $loop) {
        $this->loop = $loop;
    }

    public function add_route($route, callable $target) {
        $route = preg_replace_callback('/\{(?P<part>[a-zA-Z0-9\-_\.]*)\}/', function($matches) {
            return "(?P<{$matches['part']}>[a-zA-Z0-9\\-_\\.]*)";
        }, $route);
        $route = str_replace('$', '\$', $route);
        $this->routes[$route] = $target;
    }

    public function dispatch(Request $request, Response $response) {
        foreach ($this->routes as $route => $target) {
            if (preg_match("|^$route$|", $request->getPath(), $matches)) {
                $result = call_user_func($target, $matches);

                if ($result instanceof PromiseInterface) {
                    $result->then(function($result) use ($request, $response) {
                        $this->complete_response($response, $result);
                    }, function($reason) use ($request, $response) {
                        $this->complete_response($response, new InternalServerError($reason));
                    });
                    return;
                }

                $this->complete_response($response, $result);
                return;
            }
        }

        $this->complete_response($response, new PageNotFound());
    }

    private function complete_response(Response $response, $result) {
        if ($result instanceof ControllerResponse) {
            $result->send($response);
        } else {
            $result = Json::pretty($result);

            $response->writeHead(200, ['Content-Type' => 'application/json']);
            $response->end($result);
        }
    }
} 
