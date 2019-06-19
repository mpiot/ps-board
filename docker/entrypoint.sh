#!/bin/bash
set -e

bin/console cache:clear --no-warmup
bin/console cache:warmup
chown -R www-data var

exec "$@"
