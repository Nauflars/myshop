# RabbitMQ with Management Plugin
FROM rabbitmq:3-management-alpine

# Enable management plugin (included in management image)
# Enable shovel plugin for advanced routing (optional for future extensions)
RUN rabbitmq-plugins enable --offline rabbitmq_management rabbitmq_shovel rabbitmq_shovel_management

# Set permissions and configuration
COPY rabbitmq.conf /etc/rabbitmq/rabbitmq.conf
COPY definitions.json /etc/rabbitmq/definitions.json

# Health check
HEALTHCHECK --interval=10s --timeout=5s --retries=5 \
  CMD rabbitmq-diagnostics -q ping

# Expose AMQP and management ports
EXPOSE 5672 15672

# Use default command from base image
CMD ["rabbitmq-server"]
