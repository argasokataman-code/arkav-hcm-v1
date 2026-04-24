<?php

namespace App\Notifications;

use App\Models\PerformanceReview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PerformanceReviewCreatedNotification extends Notification
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
            'event_key' => 'performance.review.created',
            'company_uuid' => $this->review->company?->uuid,
            'review_id' => $this->review->id,
            'employee_id' => $this->review->user_id,
            'employee_name' => $this->review->employee?->name,
            'cycle_name' => $this->review->cycle?->name,
            'template_name' => $this->review->template?->name,
            'status' => $this->review->status,
        ];
    }
}
