#!/bin/bash
# Slack notification script for Jenkins pipeline status
# Usage: ./notify-slack.sh <status> <build_url> <message>

STATUS="${1:-unknown}"
BUILD_URL="${2:-}"
MESSAGE="${3:-Build completed}"

# Check if Slack webhook URL is configured
if [ -z "$SLACK_WEBHOOK_URL" ]; then
    echo "⚠ SLACK_WEBHOOK_URL not configured, skipping notification"
    exit 0
fi

# Determine color based on status
case "$STATUS" in
    success|passed)
        COLOR="good"
        EMOJI="✓"
        ;;
    failure|failed)
        COLOR="danger"
        EMOJI="✗"
        ;;
    warning)
        COLOR="warning"
        EMOJI="⚠"
        ;;
    *)
        COLOR="#808080"
        EMOJI="ℹ"
        ;;
esac

# Get git info
GIT_BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "unknown")
GIT_COMMIT=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")
GIT_AUTHOR=$(git log -1 --pretty=format:'%an' 2>/dev/null || echo "unknown")

# Build Slack payload
PAYLOAD=$(cat <<EOF
{
  "text": "${EMOJI} MyShop CI/CD Pipeline ${STATUS}",
  "attachments": [
    {
      "color": "${COLOR}",
      "fields": [
        {
          "title": "Project",
          "value": "MyShop",
          "short": true
        },
        {
          "title": "Branch",
          "value": "${GIT_BRANCH}",
          "short": true
        },
        {
          "title": "Commit",
          "value": "${GIT_COMMIT}",
          "short": true
        },
        {
          "title": "Author",
          "value": "${GIT_AUTHOR}",
          "short": true
        },
        {
          "title": "Message",
          "value": "${MESSAGE}",
          "short": false
        },
        {
          "title": "Build",
          "value": "<${BUILD_URL}|View Build Logs>",
          "short": false
        }
      ],
      "footer": "Jenkins CI/CD",
      "ts": $(date +%s)
    }
  ]
}
EOF
)

# Send notification to Slack
echo "→ Sending Slack notification..."
curl -X POST "${SLACK_WEBHOOK_URL}" \
  -H 'Content-Type: application/json' \
  -d "${PAYLOAD}" \
  --silent \
  --show-error

echo "✓ Notification sent"
