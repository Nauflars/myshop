#!/bin/bash

echo "Testing Cart API..."
echo ""

# Test with cookie for authenticated user
echo "=== Testing /api/cart ===" 

curl -s http://localhost/api/cart \
  -H "Cookie: PHPSESSID=test" \
  -H "Accept: application/json" | jq '.'

echo ""
echo "Done!"
