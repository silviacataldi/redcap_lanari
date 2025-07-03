#!/bin/sh

set -e

# Unset this so that Psalm does not default to a minimal output format
unset GITHUB_ACTIONS

composer install -q
npm install > /dev/null

phpunitPath=`php bin/get-phpunit-path.php`

# php -dxdebug.mode=debug -dxdebug.client_host=127.0.0.1 -dxdebug.client_port=9003 -dxdebug.start_with_request=yes `php bin/get-phpunit-path.php`
php $phpunitPath

echo
echo "Running REDCap Core's unit tests to make sure we didn't break any of them..."
redcapVersionPath=$phpunitPath
for i in 1 2 3 4 5; do
    redcapVersionPath=`dirname $redcapVersionPath`
done
cd $redcapVersionPath

grep ExternalModules::getTestPIDs UnitTests/Project/ProjectTest.php > /dev/null || unsafeREDCapCommit=1
if [ $unsafeREDCapCommit ] ; then
    echo "REDCap Core tests are not safe in this REDCap Core version!!!"
    exit
fi

php $phpunitPath
cd - > /dev/null

echo
echo Checking code standards...
minPhpVersion=`php bin/get-min-php-version.php`;
vendor/bin/phpcs -ps \
--runtime-set testVersion $minPhpVersion- \
--runtime-set installed_paths vendor/phpcompatibility/php-compatibility/PHPCompatibility \
--standard=tests/phpcs-framework,tests/phpcs-shared,vendor/phpcompatibility/php-compatibility/PHPCompatibility \
--extensions=php --ignore=/vendor,/node_modules .

# We get intermittent constant not found errors if we don't clear the cache first...
bin/psalm --clear-cache

echo "Running Psalm (the normal scan must succeed to guarantee that all code can be traversed for the taint analysis)"
bin/psalm 2>&1

echo "Running Psalm's Taint Analysis "
bin/psalm --taint-analysis 2>&1

echo Ensuring JavaScript browser compatibility...
node_modules/.bin/eslint .
echo

echo 'All tests completed successfully!'
echo
