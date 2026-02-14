<?php

// Test registration via API endpoint

$url = 'http://localhost/api/users';
$data = [
    'name' => 'Test User 2',
    'email' => 'test2@test2.com',
    'password' => 'test123',
    'role' => 'ROLE_CUSTOMER',
];

$options = [
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($data),
    ],
];

$context = stream_context_create($options);
$result = @file_get_contents($url, false, $context);

if (false === $result) {
    echo "❌ Registration failed\n";
    echo "HTTP Response Headers:\n";
    print_r($http_response_header ?? 'No response');
} else {
    echo "✅ Registration successful\n";
    echo "Response:\n";
    print_r(json_decode($result, true));
}

// Wait a moment for profile creation
echo "\n⏳ Waiting 3 seconds for profile creation...\n";
sleep(3);

// Check MongoDB for profile
echo "\n🔍 Checking MongoDB for user profile...\n";
$response = json_decode($result, true);
if (isset($response['id'])) {
    $userId = $response['id'];
    echo "User ID: $userId\n";

    $mongoCommand = sprintf(
        'docker exec myshop_mongodb mongosh -u root -p rootpassword --authenticationDatabase admin myshop --quiet --eval "db.user_profiles.findOne({userId: \'%s\'}, {userId: 1, \'dataSnapshot.recentSearches\': 1, \'dataSnapshot.recentPurchases\': 1})"',
        $userId
    );

    echo "\nExecuting: $mongoCommand\n\n";
    system($mongoCommand);
}
