#!/usr/bin/env bash

source /opt/app-root/scripts/util/hostname.sh

if [ "$BOOTSTRAP" != "false" ]; then
    source /opt/app-root/scripts/util/bootstrap.sh
    ../vendor/bin/drupal module:install config
fi

if [ "$DEFAULT_CONFIG" != "false" ]; then
    ../vendor/bin/drupal config:import:single --file /opt/app-root/test/dev/elastic_search.server.yml
fi

# Keep the container running with the hold script from the drupal_module_tester image
source /opt/app-root/scripts/util/hold.sh