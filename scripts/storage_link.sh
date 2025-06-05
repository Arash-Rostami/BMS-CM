#!/bin/bash

ARTISAN_PATH="/home/communit/import/artisan"
PHP_CLI="/usr/local/bin/ea-php82"

cd "$(dirname "$ARTISAN_PATH")" || exit 1
$PHP_CLI "$ARTISAN_PATH" storage:link --force > /dev/null 2>&1
