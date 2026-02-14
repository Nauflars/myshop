#!/bin/sh
set -e

# Fix permissions for RabbitMQ directories
chown -R rabbitmq:rabbitmq /var/lib/rabbitmq
chmod 700 /var/lib/rabbitmq

# Execute the original entrypoint
exec docker-entrypoint.sh "$@"
