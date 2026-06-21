<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Services\LocationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CreateTestAttendanceData extends Command
{
    protected $signature = 'attendance:create-test-data {--date=2026-04-14}';

    protected $description = 'Create test attendance records with location data for multiple employees';

    public function handle()
    {
        $date = $this->option('date');
        $tz = 'Asia/Jakarta';

        // Test coordinates in Jakarta area
        $testLocations = [
            [
                'name' => 'Employee 1',
                'user_id' => 1,
                'check_in_lat' => -6.2088,
                'check_in_lng' => 106.8456,
                'check_out_lat' => -6.2095,
                'check_out_lng' => 106.8465,
            ],
            [
                'name' => 'Employee 2',
                'user_id' => 2,
                'check_in_lat' => -6.1951,
                'check_in_lng' => 106.8202,
                'check_out_lat' => -6.1952,
                'check_out_lng' => 106.8203,
            ],
            [
                'name' => 'Employee 3',
                'user_id' => 3,
                'check_in_lat' => -6.2675,
                'check_in_lng' => 106.7852,
                'check_out_lat' => -6.2674,
                'check_out_lng' => 106.7853,
            ],
            [
                'name' => 'Employee 4',
                'user_id' => 4,
                'check_in_lat' => -6.1750,
                'check_in_lng' => 106.8270,
                'check_out_lat' => -6.1751,
                'check_out_lng' => 106.8271,
            ],
            [
                'name' => 'Employee 5',
                'user_id' => 5,
                'check_in_lat' => -6.2325,
                'check_in_lng' => 106.8456,
                'check_out_lat' => -6.2326,
                'check_out_lng' => 106.8457,
            ],
        ];

        $countCreated = 0;
        $bar = $this->output->createProgressBar(count($testLocations));
        $bar->start();

        foreach ($testLocations as $loc) {
            // Check if record already exists
            $existing = AttendanceRecord::where('user_id', $loc['user_id'])
                ->where('work_date', $date)
                ->first();

            if (! $existing) {
                $checkInTime = Carbon::parse($date.' 09:00:00', $tz);
                $checkOutTime = Carbon::parse($date.' 17:30:00', $tz);

                // Get location names via reverse geocoding
                $checkInLocation = LocationService::reverseGeocode($loc['check_in_lat'], $loc['check_in_lng']);
                $checkOutLocation = LocationService::reverseGeocode($loc['check_out_lat'], $loc['check_out_lng']);

                AttendanceRecord::create([
                    'user_id' => $loc['user_id'],
                    'work_date' => $date,
                    'status' => 'present',
                    'correction_status' => 'none',
                    'check_in_at' => $checkInTime,
                    'check_in_latitude' => $loc['check_in_lat'],
                    'check_in_longitude' => $loc['check_in_lng'],
                    'check_in_location_name' => $checkInLocation['name'],
                    'check_in_location_address' => $checkInLocation['address'],
                    'check_in_location_source' => 'gps',
                    'check_out_at' => $checkOutTime,
                    'check_out_latitude' => $loc['check_out_lat'],
                    'check_out_longitude' => $loc['check_out_lng'],
                    'check_out_location_name' => $checkOutLocation['name'],
                    'check_out_location_address' => $checkOutLocation['address'],
                    'check_out_location_source' => 'gps',
                    'break_minutes' => 30,
                    'late_minutes' => 0,
                ]);
                $countCreated++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✓ Created {$countCreated} test attendance records for {$date}");
        $this->info('You can now see location data in the attendance report for this date!');
    }
}
