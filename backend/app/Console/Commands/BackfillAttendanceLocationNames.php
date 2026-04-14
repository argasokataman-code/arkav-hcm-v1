<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Services\LocationService;
use Illuminate\Console\Command;

class BackfillAttendanceLocationNames extends Command
{
    protected $signature = 'attendance:backfill-locations {--dry-run : Show what would be updated without actually updating}';

    protected $description = 'Backfill location names for existing attendance records that have coordinates but no location names';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        // Find all records with coordinates but missing location names
        $query = AttendanceRecord::query()
            ->where(function ($q) {
                $q->whereNotNull('check_in_latitude')
                    ->whereNotNull('check_in_longitude')
                    ->whereNull('check_in_location_name');
            })
            ->orWhere(function ($q) {
                $q->whereNotNull('check_out_latitude')
                    ->whereNotNull('check_out_longitude')
                    ->whereNull('check_out_location_name');
            });

        $records = $query->get();
        $count = $records->count();

        if ($count === 0) {
            $this->info('No records to backfill.');
            return 0;
        }

        $this->info("Found {$count} records to backfill.");
        $this->newLine();

        $checkInCount = 0;
        $checkOutCount = 0;
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($records as $record) {
            // Backfill check-in location
            if ($record->check_in_latitude && $record->check_in_longitude && !$record->check_in_location_name) {
                $locationData = LocationService::reverseGeocode(
                    (float) $record->check_in_latitude,
                    (float) $record->check_in_longitude
                );
                
                if (!$dryRun) {
                    $record->check_in_location_name = $locationData['name'];
                    $record->check_in_location_address = $locationData['address'];
                    if (!$record->check_in_location_source) {
                        $record->check_in_location_source = $locationData['source'];
                    }
                }
                $checkInCount++;
            }

            // Backfill check-out location
            if ($record->check_out_latitude && $record->check_out_longitude && !$record->check_out_location_name) {
                $locationData = LocationService::reverseGeocode(
                    (float) $record->check_out_latitude,
                    (float) $record->check_out_longitude
                );
                
                if (!$dryRun) {
                    $record->check_out_location_name = $locationData['name'];
                    $record->check_out_location_address = $locationData['address'];
                    if (!$record->check_out_location_source) {
                        $record->check_out_location_source = $locationData['source'];
                    }
                }
                $checkOutCount++;
            }

            if (!$dryRun) {
                $record->save();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN: No records were actually updated.');
            $this->info("Would have updated {$checkInCount} check-in locations and {$checkOutCount} check-out locations.");
        } else {
            $this->info("✓ Backfilled {$checkInCount} check-in locations");
            $this->info("✓ Backfilled {$checkOutCount} check-out locations");
        }

        return 0;
    }
}
