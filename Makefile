.DEFAULT_GOAL := help

CLI_ARGS := $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))
$(eval $(CLI_ARGS):;@:)

PRIMARY_GOAL := $(firstword $(MAKECMDGOALS))

#
# Targets
#

ifeq ($(PRIMARY_GOAL),p)
p: ## Run PHP Psalm
	php vendor/bin/psalm
endif

ifeq ($(PRIMARY_GOAL),pf)
pf: ## Run PHP Psalm on a specific file
ifndef FILE
	$(error Please provide FILE, e.g. 'make pf FILE=src/Foo.php')
endif
	php vendor/bin/psalm "$(FILE)"
endif

ifeq ($(PRIMARY_GOAL),pc)
pc: ## Clear Psalm's cache
	php vendor/bin/psalm --clear-cache
endif

ifeq ($(PRIMARY_GOAL),pu)
pu: ## Run PHPUnit tests
	php vendor/bin/phpunit
endif

ifeq ($(PRIMARY_GOAL),ric)
ric: ## Roave Infection Covered
	php vendor/bin/roave-infection-static-analysis-plugin --only-covered --min-msi=92
endif

ifeq ($(PRIMARY_GOAL),riu)
riu: ## Roave Infection Uncovered
	php vendor/bin/roave-infection-static-analysis-plugin --min-msi=92
endif

ifeq ($(PRIMARY_GOAL),i)
i: ## Infection Mutation Test
	php vendor/bin/infection
endif

ifeq ($(PRIMARY_GOAL),co)
co: ## Composer outdated
	composer outdated
endif

ifeq ($(PRIMARY_GOAL),cwn)
cwn: ## Composer why-not
ifndef REPO
	$(error Please provide REPO, e.g. 'make cwn REPO=yiisoft/yii-demo VERSION=1.1.1')
endif
ifndef VERSION
	$(error Please provide VERSION, e.g. 'make cwn REPO=yiisoft/yii-demo VERSION=1.1.1')
endif
	composer why-not $(REPO) $(VERSION)
endif

ifeq ($(PRIMARY_GOAL),cu)
cu: ## Composer update
	composer update
endif

ifeq ($(PRIMARY_GOAL),cda)
cda: ## Composer dump-autoload
	composer dump-autoload -o
endif

ifeq ($(PRIMARY_GOAL),crc)
crc: ## Composer require checker
	php vendor/bin/composer-require-checker
endif

ifeq ($(PRIMARY_GOAL),rdr)
rdr: ## Rector Dry Run (see changes)
	php vendor/bin/rector process --dry-run
endif

ifeq ($(PRIMARY_GOAL),rmc)
rmc: ## Rector (make changes)
	php vendor/bin/rector
endif

#
# Help
#

ifeq ($(PRIMARY_GOAL),help)
help: ## This help.
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)
endif

.PHONY: menu p pf pc pu ric riu i co cwn cu cda crc rdr rmc help