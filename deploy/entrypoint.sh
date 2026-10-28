#!/bin/sh
set -eu
# Compose mounts file-backed secrets with host ownership; prepare a worker-readable
# copy in a private tmpfs, preserving the restrictive source-file permissions.
install -d -m 0700 -o www-data -g www-data /run/agora
install -m 0600 -o www-data -g www-data /run/secrets/integration_key /run/agora/integration.key
exec docker-php-entrypoint "$@"
