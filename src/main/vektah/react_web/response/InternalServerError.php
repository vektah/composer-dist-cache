<?php


namespace vektah\react_web\response;

use React\Http\Response;

class InternalServerError implements ControllerResponse {
    private $message;

    public function __construct($message) {
        $this->message = $message;
    }

    public function send(Response $response)
    {
        $response->writeHead(500, ['Content-type' => 'text/plain']);
        $response->end($this->message);
    }
}
