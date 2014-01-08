<?php


namespace vektah\react_web\response;

use React\Http\Response;

interface ControllerResponse {
    public function send(Response $response);
} 
