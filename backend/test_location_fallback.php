<?php
require 'bootstrap/app.php';

use App\Models\AttendanceRecord;

$rec = AttendanceRecord::where('work_date', '2026-04-13')->first();

if ($rec) {
    echo "April 13 Record:\n";
    echo "  Location Name: " . ($rec->check_in_location_name ?? 'NULL') . "\n";
    echo "  Latitude: " . ($rec->check_in_latitude ?? 'NULL') . "\n";
    echo "  Longitude: " . ($rec->check_in_longitude ?? 'NULL') . "\n";
    
    // Apply fallback logic
    $checkInLoc = $rec->check_in_location_name ?? '-';
    if (!$checkInLoc || $checkInLoc === '-') {
        $checkInLoc = ($rec->check_in_latitude && $rec->check_in_longitude) 
            ? round((float)$rec->check_in_latitude, 4) . ', ' . round((float)$rec->check_in_longitude, 4)
            : '-';
    }
    
    echo "\n  Fallback Result: " . $checkInLoc . "\n";
} else {
    echo "No record found for April 13\n";
}
