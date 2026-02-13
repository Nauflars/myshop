#!/bin/bash
CRUMB=$(curl -s -u admin:admin123 "http://localhost:8080/jenkins/crumbIssuer/api/json" | grep -oP '"crumb":"\K[^"]+')
CRUMB_FIELD=$(curl -s -u admin:admin123 "http://localhost:8080/jenkins/crumbIssuer/api/json" | grep -oP '"crumbRequestField":"\K[^"]+')
curl -X POST -u admin:admin123 -H "${CRUMB_FIELD}: ${CRUMB}" "http://localhost:8080/jenkins/job/myshop-deployment/buildWithParameters?ENVIRONMENT=test&GIT_BRANCH=main"
echo ""
echo "Build triggered successfully!"
