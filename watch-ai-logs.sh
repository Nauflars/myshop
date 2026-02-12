#!/bin/bash
# Script para ver logs de AI en tiempo real

echo "=== Watching AI Logs (Ctrl+C to stop) ==="
echo ""

# Ver logs de dev.log y ai_agent.log en paralelo
docker-compose exec php tail -f var/log/dev.log var/log/ai_agent.log var/log/ai_tools.log 2>&1 | \
  grep --color=always -E 'ai_agent|ai_tools|🤖|🔧|⚡|❌|$'
