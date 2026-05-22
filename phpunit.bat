vendor/bin/phpunit --coverage-html build/coverage
rem vendor/bin/phpunit -c tests/phpunit.xml --coverage-html build/coverage
rem vendor/bin/phpunit --testsuite controllerTests
rem vendor/bin/phpunit --testsuite mapperTests
rem vendor/bin/phpunit --testsuite modelTests
rem vendor/bin/phpunit --testsuite repositoryTests
rem vendor/bin/phpunit --testsuite serviceTests --coverage-html build/coverage
rem vendor/bin/phpunit tests/controller/GitlabControllerTest.php
rem vendor/bin/phpunit tests/controller/PostamControllerTest.php
rem vendor/bin/phpunit phpunit --generate-configuration
rem vendor/bin/phpunit phpunit.xml --migrate-configuration
