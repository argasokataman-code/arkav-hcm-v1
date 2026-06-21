<?php

namespace App\Jobs\Reporting;

use App\Services\Reporting\ReportSnapshotService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateReportSnapshot implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $reportType,
        protected Carbon $periodStart,
        protected Carbon $periodEnd,
        protected array $filters,
        protected int $userId,
        protected int $companyId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ReportSnapshotService $service): void
    {
        $service->generateSnapshot(
            $this->reportType,
            $this->periodStart,
            $this->periodEnd,
            $this->filters,
            $this->userId,
            $this->companyId,
        );
    }
}
