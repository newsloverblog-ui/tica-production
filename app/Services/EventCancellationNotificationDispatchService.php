<?php

namespace App\Services;

use App\Jobs\SendEventCancellationNotice;
use App\Models\EventCancellationNotification;
use Throwable;

class EventCancellationNotificationDispatchService
{
    public function dispatchNotice(int $notificationId): bool
    {
        $notification = EventCancellationNotification::query()->find($notificationId);
        if (!$notification || $notification->status !== 'pending') {
            return true;
        }

        $notification->forceFill([
            'dispatch_attempts' => (int) $notification->dispatch_attempts + 1,
            'dispatch_last_attempt_at' => now(),
            'dispatch_last_error' => null,
        ])->save();

        try {
            $this->pushNoticeJob((int) $notification->id);
            return true;
        } catch (Throwable $exception) {
            $notification->forceFill([
                'dispatch_last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            return false;
        }
    }

    protected function pushNoticeJob(int $notificationId): void
    {
        SendEventCancellationNotice::dispatch($notificationId);
    }
}
