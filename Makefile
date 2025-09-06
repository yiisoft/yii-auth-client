.PHONY: menu psalm psalm_file psalm_clear_cache php_unit_test roave_infection_covered \
        roave_infection_uncovered infection outdated composerwhynot composer_update \
        composer_dumpautoload require_checker rector_see_changes rector_make_changes \
        exit exit_to_directory

menu:
	@echo "================================================================================"
	@echo "                    Auth Client SYSTEM MENU (Make targets)"
	@echo "================================================================================"
	@echo ""
	@echo "make p                      - Run PHP Psalm"
	@echo "make pf                     - Run PHP Psalm on a specific file"
	@echo "make pc                     - Clear Psalm's cache"
	@echo "make pu                     - Run PHPUnit tests"
	@echo "make ric                    - Roave Infection Covered"
	@echo "make riu                    - Roave Infection Uncovered"
	@echo "make i                      - Infection Mutation Test"
	@echo "make co                     - Composer outdated"
	@echo "make cwn                    - Composer why-not"
	@echo "make cu                     - Composer update"
	@echo "make cda                    - Composer dump-autoload"
	@echo "make crc                    - Composer require checker"
	@echo "make rdr                    - Rector Dry Run (see changes)"
	@echo "make rmc                    - Rector (make changes)"
	@echo "make ex                     - Exit (noop in Makefile)"
	@echo "make exd                    - Exit to current directory (noop in Makefile)"
	@echo ""

p:
	php vendor/bin/psalm

pf:
ifndef FILE
	$(error Please provide FILE, e.g. 'make psalm_file FILE=src/Foo.php')
endif
	php vendor/bin/psalm "$(FILE)"

pc:
	php vendor/bin/psalm --clear-cache

pu:
	php vendor/bin/phpunit

ric:
	php vendor/bin/roave-infection-static-analysis-plugin --only-covered --min-msi=92

riu:
	php vendor/bin/roave-infection-static-analysis-plugin --min-msi=92

i:
	php vendor/bin/infection

co:
	composer outdated

cwn:
ifndef REPO
	$(error Please provide REPO, e.g. 'make composerwhynot REPO=yiisoft/yii-demo VERSION=1.1.1')
endif
ifndef VERSION
	$(error Please provide VERSION, e.g. 'make composerwhynot REPO=yiisoft/yii-demo VERSION=1.1.1')
endif
	composer why-not $(REPO) $(VERSION)

cu:
	composer update

cda:
	composer dump-autoload -o

crc:
	php vendor/bin/composer-require-checker

rdr:
	php vendor/bin/rector process --dry-run

rmc:
	php vendor/bin/rector

ex:
	@echo "Exiting (noop in Makefile)."

exd:
	@echo "Returning to current directory (noop in Makefile)."