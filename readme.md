composer-dist-cache
===================

This is an on demand caching proxy for composer. It builds dist zipballs of remote git repositories on demand. It can be placed in front of packagist and will only download the git repositories you use locally.

This is aimed to speed up fetching of dependencies, without having to manage a satis repository.

Coming soon
===========
 - tests!
 - local private packages
