#!/bin/bash

echo "=== Testing Product Search with Profile Update ==="
echo ""

# Step 1: Login
echo "Step 1: Logging in as test6@test6.com..."
LOGIN_RESPONSE=$(docker exec myshop_nginx curl -s -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -d '{"username":"test6@test6.com","password":"test123"}' \
  -c /tmp/cookies.txt)

echo "Login response: $LOGIN_RESPONSE"
echo ""

# Extract session cookie
SESSION_COOKIE=$(docker exec myshop_nginx cat /tmp/cookies.txt 2>/dev/null | grep -v '^#' | awk '{print $6"="$7}')
echo "Session: $SESSION_COOKIE"
echo ""

# Step 2: Make a product search
echo "Step 2: Searching for 'programming books'..."
SEARCH_RESPONSE=$(docker exec myshop_nginx curl -s -X GET \
  "http://localhost/api/products?q=programming+books" \
  -H "Cookie: $SESSION_COOKIE" \
  -b /tmp/cookies.txt)

echo "Products found: $(echo $SEARCH_RESPONSE | grep -o '"id"' | wc -l)"
echo ""

# Step 3: Wait for profile update
echo "Step 3: Waiting 3 seconds for profile update..."
sleep 3

# Step 4: Check MongoDB
echo "Step 4: Checking profile in MongoDB..."
docker exec myshop_mongodb mongosh -u root -p rootpassword \
  --authenticationDatabase admin myshop --quiet \
  --eval "var doc = db.user_profiles.findOne({userId: '952ffe27-3c02-4b55-9428-a0bc8492c6d2'}); \
    print('AFTER - Searches count: ' + doc.dataSnapshot.recentSearches.length); \
    print('Searches: ' + JSON.stringify(doc.dataSnapshot.recentSearches)); \
    print('Embedding[0]: ' + doc.embeddingVector[0]);"

echo ""
echo "=== Test Complete ==="
