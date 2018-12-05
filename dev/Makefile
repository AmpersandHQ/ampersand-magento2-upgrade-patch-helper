list: 					# Lists all available commands, the default command when running `make` with no arguments
	grep -v -e "^\t" Makefile | grep . | awk -F":.+?#" '{ print $$1 " #" $$2 }' | column -t -s '#'

configure-magento-2-1:  # Install magento 2.1 with the test module, and create vendor.patch for analysis
	./setup-magento-with-diff.sh 2.1.0 2.1.16 21

test-magento-2-1:  # Analyse 2.1 with vendor.patch
	php ../bin/patch-helper.php analyse $(shell pwd)/instances/magento21 > output/actual/magento21.out.txt
	diff output/expected/magento21.out.txt output/actual/magento21.out.txt

configure-magento-2-2:  # Install magento 2.2 with the test module, and create vendor.patch for analysis
	./setup-magento-with-diff.sh 2.2.0 2.2.7 22

test-magento-2-2:  # Analyse 2.2 with vendor.patch
	php ../bin/patch-helper.php analyse $(shell pwd)/instances/magento22 > output/actual/magento22.out.txt
	diff output/expected/magento22.out.txt output/actual/magento22.out.txt
