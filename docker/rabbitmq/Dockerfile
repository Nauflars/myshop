# RabbitMQ with Management Plugin
FROM rabbitmq:3-management-alpine

# Enable management plugin (included in management image)
# Enable shovel plugin for advanced routing (optional for future extensions)
RUN rabbitmq-plugins enable --offline rabbitmq_management rabbitmq_shovel rabbitmq_shovel_management

# Set permissions and configuration
COPY rabbitmq.conf /etc/rabbitmq/rabbitmq.conf
COPY definitions.json /etc/rabbitmq/definitions.json

# Copy custom entrypoint that fixes permissions at runtime
COPY docker-entrypoint.sh /usr/local/bin/custom-entrypoint.sh
RUN chmod +x /usr/local/bin/custom-entrypoint.sh

# Health check
HEALTHCHECK --interval=10s --timeout=5s --retries=5 \
  CMD rabbitmq-diagnostics -q ping

# Expose AMQP and management ports
EXPOSE 5672 15672

# Use custom entrypoint
ENTRYPOINT ["/usr/local/bin/custom-entrypoint.sh"]
CMD ["rabbitmq-server"]
