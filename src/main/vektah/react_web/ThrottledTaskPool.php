<?php


namespace vektah\react_web;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

class ThrottledTaskPool extends EventEmitter {
    private $concurrency;

    /** @var callable[]|PromiseInterface[] */
    private $pending = [];

    /** @var callable[]|PromiseInterface[] */
    private $processing = [];

    /** @var array */
    private $complete = [];

    public function __construct(LoopInterface $loop, $concurrency = 1) {
        $this->concurrency = $concurrency;
        $this->loop = $loop;
    }

    public function add($name, callable $callable) {
        $this->pending[$name] = $callable;
        $this->run_next();
    }

    private function run_next() {
        while (count($this->pending) > 0 && count($this->processing) < $this->concurrency) {
            $task = reset($this->pending);
            $task_name = key($this->pending);
            unset($this->pending[$task_name]);
            $this->processing[$task_name] = $task;

            // Flatten the stack, this can get deeply recursive otherwise.
            $this->loop->addTimer(0.001, function() use ($task, $task_name) {
                if (is_callable($task)) {
                    $task = $task();
                }

                $task->then(function($result) use ($task_name) {
                    $this->complete[$task_name] = $result;
                    unset($this->processing[$task_name]);

                    if (count($this->pending) === 0 && count($this->processing) === 0) {
                        $this->emit('end', [$this->complete]);
                    } else {
                        $this->run_next();
                    }
                });
            });
        }
    }
}
