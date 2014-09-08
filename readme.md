composer-dist-cache
===================

This is an on demand caching proxy for composer. It builds dist zipballs of remote git repositories on demand. It can be placed in front of packagist and will only download the git repositories you use locally.

This is aimed to speed up fetching of dependencies, without having to manage a satis repository.

Setup
===================

Create an ssh key on github https://github.com/settings/ssh and place public/private key pair on github account.

NOTE: Must set the baseurl variable of config.json of the external DNS of composer-dist-cache

config.json
```json
{
	"hostname": "0.0.0.0",
	"baseurl": "${dns_address_of_composer_dist_cache}",
	"port": 1234
}
```

Coming soon
===========
 - tests!
 - local private packages
