<?php


namespace vektah\composer\cache\controller;

use React\Http\Response;
use React\Stream\Stream;
use vektah\common\json\Json;
use vektah\composer\cache\Mirror;
use vektah\composer\cache\HashStore;
use vektah\composer\cache\GitDownloader;
use vektah\react_web\CachedRemote;
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

        return $this->mirror->get_package($vendor, $package)->then(function ($package_data) use ($vendor, $package, $version) {
            if (!isset($package_data['packages']["$vendor/$package"][$version])) {
                return new PageNotFound();
            }

            $package_version = $package_data['packages']["$vendor/$package"][$version];

            if (!isset($package_version['source'])) {
                return new InternalServerError('This package does not have a source section, cannot fetch!');
            }

            if ($package_version['source']['type'] !== 'git') {
                return new InternalServerError('Only git sources are currently supported');
            }

            return $this->git_downloader->fetch_zip($package_version['source']['url'], $package_version['source']['reference'])->then(function($zip_filename) {
                return new StreamResponse('application/zip', new Stream(fopen($zip_filename, 'r'), $this->context->getLoop()));
            });
        });
    }
} 
