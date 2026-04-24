<?php

namespace App\Notifications;

use App\Models\PerformanceReview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PerformanceReviewSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private PerformanceReview $review,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event_key' => 'performance.review.submitted',
            'company_uuid' => $this->review->company?->uuid,
            'review_id' => $this->review->id,
            'employee_id' => $this->review->user_id,
            'employee_name' => $this->review->employee?->name,
            'manager_id' => $this->review->manager_user_id,
            'manager_name' => $this->review->manager?->name,
            'cycle_name' => $this->review->cycle?->name,
            'status' => $this->review->status,
        ];
    }
}
