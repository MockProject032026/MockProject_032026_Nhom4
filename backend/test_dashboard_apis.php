<?php
// filepath: d:\VSCODE\sql\htdocs\htdocs-laravel\laraveltest\MockProject_032026_Nhom4\backend\test_dashboard_apis.php

$apiEndpoints = [
    [
        'name' => 'Dashboard KPI Summary',
        'url' => 'http://127.0.0.1:8000/api/v1/dashboard/kpi-summary',
        'method' => 'GET',
        'params' => []
    ],
    [
        'name' => 'Dashboard KPI Summary - State Filter',
        'url' => 'http://127.0.0.1:8000/api/v1/dashboard/kpi-summary?venue_state=CA',
        'method' => 'GET',
        'params' => ['venue_state' => 'CA']
    ],
    [
        'name' => 'Compliance Logs',
        'url' => 'http://127.0.0.1:8000/api/v1/dashboard/compliance-logs',
        'method' => 'GET',
        'params' => []
    ],
    [
        'name' => 'Compliance Logs - With Limit',
        'url' => 'http://127.0.0.1:8000/api/v1/dashboard/compliance-logs?limit=5',
        'method' => 'GET',
        'params' => ['limit' => 5]
    ],
    [
        'name' => 'Journals List',
        'url' => 'http://127.0.0.1:8000/api/v1/journals',
        'method' => 'GET',
        'params' => []
    ],
    [
        'name' => 'Journals - Status Filter',
        'url' => 'http://127.0.0.1:8000/api/v1/journals?status=completed',
        'method' => 'GET',
        'params' => ['status' => 'completed']
    ],
    [
        'name' => 'Journals - With Pagination',
        'url' => 'http://127.0.0.1:8000/api/v1/journals?page=1&limit=10',
        'method' => 'GET',
        'params' => ['page' => 1, 'limit' => 10]
    ]
];

echo "================================================================================\n";
echo "DASHBOARD API TEST REPORT\n";
echo "================================================================================\n";
echo "Test Date: " . date('Y-m-d H:i:s') . "\n";
echo "API Base URL: http://127.0.0.1:8000\n";
echo "Total APIs to Test: " . count($apiEndpoints) . "\n";
echo "================================================================================\n\n";

$passCount = 0;
$failCount = 0;
$results = [];

foreach ($apiEndpoints as $index => $api) {
    echo "TEST #" . ($index + 1) . ": " . $api['name'] . "\n";
    echo "URL: " . $api['url'] . "\n";
    echo "Method: " . $api['method'] . "\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "Status: ❌ FAIL (Connection Error)\n";
        echo "Error: " . $error . "\n";
        $failCount++;
        $results[] = [
            'test' => $api['name'],
            'status' => 'FAIL',
            'code' => 'ERROR',
            'message' => $error
        ];
    } elseif ($httpCode === 200) {
        echo "Status: ✅ PASS\n";
        echo "HTTP Code: " . $httpCode . "\n";
        $responseData = json_decode($response, true);
        if ($responseData) {
            echo "Response Keys: " . implode(', ', array_keys($responseData)) . "\n";
        }
        $passCount++;
        $results[] = [
            'test' => $api['name'],
            'status' => 'PASS',
            'code' => $httpCode,
            'message' => 'Success'
        ];
    } else {
        echo "Status: ❌ FAIL\n";
        echo "HTTP Code: " . $httpCode . "\n";
        echo "Response: " . substr($response, 0, 200) . "...\n";
        $failCount++;
        $results[] = [
            'test' => $api['name'],
            'status' => 'FAIL',
            'code' => $httpCode,
            'message' => substr($response, 0, 100)
        ];
    }
    
    echo "\n";
}

echo "================================================================================\n";
echo "TEST SUMMARY\n";
echo "================================================================================\n";
echo "Total Tests: " . count($apiEndpoints) . "\n";
echo "Passed: ✅ " . $passCount . "\n";
echo "Failed: ❌ " . $failCount . "\n";
echo "Pass Rate: " . round(($passCount / count($apiEndpoints)) * 100, 2) . "%\n";
echo "================================================================================\n";

// Save to file
$reportFile = __DIR__ . '/DASHBOARD_API_TEST_RESULTS.txt';
$reportContent = "================================================================================\n";
$reportContent .= "DASHBOARD API TEST REPORT\n";
$reportContent .= "================================================================================\n";
$reportContent .= "Test Date: " . date('Y-m-d H:i:s') . "\n";
$reportContent .= "Total Tests: " . count($apiEndpoints) . "\n";
$reportContent .= "Passed: " . $passCount . "\n";
$reportContent .= "Failed: " . $failCount . "\n";
$reportContent .= "Pass Rate: " . round(($passCount / count($apiEndpoints)) * 100, 2) . "%\n";
$reportContent .= "================================================================================\n\n";

foreach ($results as $result) {
    $reportContent .= "TEST: " . $result['test'] . "\n";
    $reportContent .= "Status: " . $result['status'] . "\n";
    $reportContent .= "Code: " . $result['code'] . "\n";
    $reportContent .= "Message: " . $result['message'] . "\n";
    $reportContent .= "---\n";
}

file_put_contents($reportFile, $reportContent);
echo "\nReport saved to: " . $reportFile . "\n";