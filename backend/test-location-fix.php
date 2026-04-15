<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

try {
    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    // Test the AdminIndex endpoint logic
    $controller = app(\App\Http\Controllers\Api\AttendanceController::class);
    
    // Get a user
    $user = \App\Models\User::find(1);
    $request = new \Illuminate\Http\Request([
        'date' => '2026-04-14'
    ]);
    $request->setUserResolver(fn() => $user);
    
    // Call the admin method
    $response = $controller->adminIndex($request);
    $data = json_decode($response->getContent(), true);
    
    echo "=== Admin Response ===\n";
    echo "Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
    echo "Total Records: " . count($data['data']) . "\n";
    
    if (count($data['data']) > 0) {
        foreach (array_slice($data['data'], 0, 3) as $row) {
            echo "\n--- " . $row['employeeName'] . " ---\n";
            echo "Check In Location: " . $row['checkInLocation'] . "\n";
            echo "Check Out Location: " . $row['checkOutLocation'] . "\n";
        }
    }
    
    echo "\n\n=== meHistory Response ===\n";
    $request2 = new \Illuminate\Http\Request([]);
    $request2->setUserResolver(fn() => $user);
    
    $response2 = $controller->meHistory($request2);
    $data2 = json_decode($response2->getContent(), true);
    
    echo "Success: " . ($data2['success'] ? 'YES' : 'NO') . "\n";
    echo "Total Records: " . count($data2['data']) . "\n";
    
    if (count($data2['data']) > 0) {
        foreach (array_slice($data2['data'], 0, 3) as $row) {
            echo "\n--- " . $row['dateLabel'] . " ---\n";
            echo "Check In Location: " . $row['checkInLocation'] . "\n";
            echo "Check Out Location: " . $row['checkOutLocation'] . "\n";
        }
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
