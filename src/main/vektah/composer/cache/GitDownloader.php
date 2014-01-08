<?php


namespace vektah\composer\cache;

use React\ChildProcess\Process;
use React\Promise\Deferred;
use React\Promise\FulfilledPromise;
use vektah\react_web\LoopContext;

class GitDownloader {
    private $loop_context;

    /** @var Deferred[][] a map of repo name => Deferred[]. If the repo is in this list then you should block on the promise to get the zip name */
    private $locks = [];

    function __construct(LoopContext $loop_context)
    {
        $this->loop_context = $loop_context;
    }

    private function repo_dir_name($repo) {
        $repo = str_replace('@', '_at_', $repo);
        $repo = str_replace(':', '_colon_', $repo);
        $repo = str_replace('/', '_', $repo);

        return $repo;
    }

    private function git_basedir() {
        $dir = Config::instance()->get_basedir() . '/git/';

        if (!file_exists($dir)) {
            mkdir($dir);
        }

        return $dir;
    }

    private function cmd($command, $cwd) {
        $deferred = new Deferred();

        $process = new Process($command, $cwd);

        $process->on('exit', function($exitcode) use ($process, &$buffer, $command, $deferred) {
            if ($exitcode !== 0) {
                $deferred->reject("external command '$command' failed:\n $buffer");
            }
            $deferred->resolve();
        });

        $process->start($this->loop_context->getLoop());

        $buffer = '';
        $process->stdout->on('data', function($output) use (&$buffer) {
            $buffer .= $output;
        });

        $process->stderr->on('data', function($output) use (&$buffer)  {
            $buffer .= $output;
        });

        return $deferred->promise();
    }

    private function get_checkout($repo) {
        $deferred = new Deferred();

        $repo_dir = $this->git_basedir() . $this->repo_dir_name($repo);

        if (!file_exists($repo_dir)) {
            echo "Doing clone of new repo $repo because $repo_dir does not exist\n";
            $this->cmd("git clone $repo $repo_dir", $this->git_basedir())->then(function() use ($deferred) {
                $deferred->resolve();
            });

        } else {
            $deferred->resolve($repo_dir);
        }

        return $deferred->promise();
    }

    private function is_locked($repo) {
        return isset($this->locks[$repo]);
    }

    private function lock($repo) {
        $this->locks[$repo] = [];
    }

    private function unlock($repo, $zip_filename) {
        foreach ($this->locks[$repo] as $lock) {
            $lock->resolve($zip_filename);
        }
        unset($this->locks[$repo]);
    }

    private function unlock_failure($repo, $reason) {
        foreach ($this->locks[$repo] as $lock) {
            $lock->reject($reason);
        }
        unset($this->locks[$repo]);
    }

    private function wait($repo) {
        $deferred = new Deferred();
        $this->locks[$repo][] = $deferred;
        return $deferred->promise();
    }

    public function fetch_zip($repo, $reference) {
        $repo_dir = $this->git_basedir() . $this->repo_dir_name($repo);
        $zip_filename = $this->repo_dir_name($repo) . '_' . $reference . '.zip';
        if (file_exists($this->git_basedir() . $zip_filename)) {
            echo "Serving cached zipball $zip_filename\n";
            return new FulfilledPromise($this->git_basedir() . $zip_filename);
        }

        if ($this->is_locked($repo)) {
            echo "Locked, waiting for other request.\n";
            return $this->wait($repo);
        }

        echo "Locking\n";
        $this->lock($repo);

        return $this->get_checkout($repo)->then(function() use ($reference, $repo_dir) {
            echo "Fetching $reference\n";

            return $this->cmd("git fetch origin $reference", $repo_dir);
        })->then(function() use ($repo_dir) {
            echo "Checking out FETCH_HEAD\n";

            return $this->cmd("git checkout FETCH_HEAD", $repo_dir);
        })->then(function() use ($zip_filename, $repo, $repo_dir) {
            echo "Zipping up\n";

            return $this->cmd("zip -r ../$zip_filename *", $repo_dir);
        })->then(function() use ($zip_filename, $repo) {
            echo "Returning zip name\n";

            $this->unlock($repo, $this->git_basedir() . $zip_filename);

            return $this->git_basedir() . $zip_filename;
        }, function($reason) use ($repo, $zip_filename) {
                echo "FAIL: $reason\n";
            if (file_exists($this->git_basedir() . $zip_filename)) {
                unlink($this->git_basedir() . $zip_filename);
            }
            $this->unlock_failure($repo, $reason);
            return \React\Promise\reject($reason);
        });
    }
} 
