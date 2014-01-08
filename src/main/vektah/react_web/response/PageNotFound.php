<?php


namespace vektah\react_web\response;

use React\Http\Response;

class PageNotFound implements ControllerResponse {
    private $message;

    function __construct($message = 'Page not found')
    {
        $this->message = $message;
    }


    public function send(Response $response)
    {
        $response->writeHead(404, ['Content-type' => 'text/plain']);
        $response->end($this->message);
    }
}
