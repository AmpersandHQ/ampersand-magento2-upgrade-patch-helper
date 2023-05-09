list: 					        # Lists all available commands
	printf "\n"; grep -v -e "^\t" Makefile | grep . | grep -v ENV | awk -F":.+?#" '{ print $$1 " #" $$2 }' | column -t -s '#';

ENV='provide_ENV_var'
PHPUNIT_ARGS=''

docker-up:       # Launch docker container
	docker-compose --file=./Docker/docker-compose.yml up --remove-orphans -d $(ENV)
	sleep 5

docker-install:  # Prepare the 'manual' steps for running this tool
	docker exec -it uphelper-$(ENV) ./dev/Docker/setup-magento-with-diff.sh

docker-test:     # Run tests inside container
	docker exec -e PHPUNIT_ARGS='$(PHPUNIT_ARGS)' -it uphelper-$(ENV) ./dev/Docker/run-phpunit-functional-tests.sh

docker-stop-all-containers:     # Stop all upgrade helper docker containers
	docker container stop uphelper-m24nodb || true
	docker container stop uphelper-m24 || true
	docker container stop uphelper-m23 || true
	docker container stop uphelper-m22 || true
	docker container stop uphelper-mysql57 || true
	docker container stop uphelper-elasticsearch || true
	docker container rm uphelper-m24nodb || true
	docker container rm uphelper-m24 || true
	docker container rm uphelper-m23 || true
	docker container rm uphelper-m22 || true
	docker container rm uphelper-mysql57 || true
	docker container rm uphelper-elasticsearch || true
	docker volume rm docker_excludedvol || true