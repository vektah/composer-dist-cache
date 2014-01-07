COMPOSER_BIN := composer
PHPUNIT_BIN := ./vendor/bin/phpunit
PHPCS_BIN := ./vendor/bin/phpcs
BUGFREE_BIN := ./vendor/bin/bugfree

all: style lint test

depends: vendor

cleandepends: cleanvendor vendor

vendor: composer.json
	$(COMPOSER_BIN) --dev update
	touch vendor

cleanvendor:
	rm -rf composer.lock
	rm -rf vendor

lint: depends
	echo " --- Lint ---"
	$(BUGFREE_BIN) lint src/main
	echo

style:
	echo " --- Style Checks ---"
	$(PHPCS_BIN) --standard=vendor/vektah/psr2 src

test: lint depends
	echo " --- Unit tests ---"
	$(PHPUNIT_BIN)
	echo

.SILENT:
