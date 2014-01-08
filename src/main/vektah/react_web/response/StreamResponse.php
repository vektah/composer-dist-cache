<?php


namespace vektah\react_web\response;

use React\Http\Response;
use React\Stream\Stream;

class StreamResponse implements ControllerResponse {
    private $stream;
    private $content_type;

    function __construct($content_type, Stream $stream)
    {
        $this->content_type = $content_type;
        $this->stream = $stream;
    }

    public function send(Response $response)
    {
        if ($response->isWritable()) {
            $response->writeHead(200, ['Content-type' => $this->content_type]);
            $this->stream->pipe($response);
        }
    }
}
