<?php

// Test the new NFL API endpoint using cURL
// Test with the specific date mentioned in the user's example
$today = '2025-10-24'; // The game is on October 24th
$apiDate = '20251023'; // Query October 23rd to get October 24th games

echo "Testing NFL API for date: {$today}\n";
echo "API query date: {$apiDate}\n";

$url = 'https://partners.api.espn.com/v2/sports/football/nfl/events?dates=' . $apiDate . '-' . $apiDate;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Test Script)');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response status: " . $httpCode . "\n";

if ($httpCode === 200) {
    $json = json_decode($response, true);
    $events = $json['events'] ?? [];
    
    echo "Events found: " . count($events) . "\n";
    
    foreach ($events as $event) {
        $competitions = $event['competitions'][0] ?? [];
        $competitors = $competitions['competitors'] ?? [];
        
        // Find home and away teams
        $home = null;
        $away = null;
        foreach ($competitors as $competitor) {
            if ($competitor['homeAway'] === 'home') {
                $home = $competitor;
            } elseif ($competitor['homeAway'] === 'away') {
                $away = $competitor;
            }
        }
        
        echo "Game: " . ($away['team']['displayName'] ?? 'Unknown') . " @ " . ($home['team']['displayName'] ?? 'Unknown') . "\n";
        echo "Status: " . ($competitions['status']['type']['name'] ?? 'Unknown') . "\n";
        echo "Date: " . ($event['date'] ?? 'Unknown') . "\n";
        echo "Venue: " . ($competitions['venue']['fullName'] ?? 'Unknown') . "\n";
        echo "Home Score: " . ($home['score']['value'] ?? 'N/A') . "\n";
        echo "Away Score: " . ($away['score']['value'] ?? 'N/A') . "\n";
        echo "Raw venue data: " . json_encode($competitions['venue'] ?? null) . "\n";
        echo "---\n";
    }
} else {
    echo "Error: HTTP {$httpCode}\n";
    echo "Response: " . substr($response, 0, 500) . "\n";
}
