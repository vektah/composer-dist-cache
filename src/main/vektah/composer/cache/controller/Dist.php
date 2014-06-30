<?php


namespace vektah\composer\cache\controller;

use React\Stream\Stream;
use vektah\composer\cache\GitDownloader;
use vektah\composer\cache\Mirror;
use vektah\react_web\LoopContext;
use vektah\react_web\response\InternalServerError;
use vektah\react_web\response\PageNotFound;
use vektah\react_web\response\StreamResponse;

class Dist {
    private $context;

    function __construct(LoopContext $context, Mirror $mirror)
    {
        $this->context = $context;
        $this->mirror = $mirror;
        $this->git_downloader = new GitDownloader($context);
    }

    public function download(array $matches) {
        echo "Download request!\n";
        $vendor = $matches['vendor'];
        $package = $matches['package'];
        $version = $matches['version'];

        return $this->mirror->get_package($vendor, $package)->then(function ($package_data) use ($vendor, $package, $version, $matches) {
            if (!isset($package_data['packages']["$vendor/$package"][$version])) {
                return new PageNotFound("$vendor/$package was not found in $version");
            }

            $package_version = $package_data['packages']["$vendor/$package"][$version];

            if (!isset($package_version['source'])) {
                return new InternalServerError('This package does not have a source section, cannot fetch!');
            }

            if ($package_version['source']['type'] !== 'git') {
                return new InternalServerError('Only git sources are currently supported');
            }

            if (isset($matches['hash'])) {
                $hash = $matches['hash'];
            } else {
                $hash = $package_version['source']['reference'];
            }

            return $this->git_downloader->fetch_zip($package_version['source']['url'], $hash)->then(function($zip_filename) {
                return new StreamResponse('application/zip', new Stream(fopen($zip_filename, 'r'), $this->context->getLoop()));
            });
        });
    }
} 
