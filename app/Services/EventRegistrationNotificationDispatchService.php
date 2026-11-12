<?php

namespace App\Services;

use App\Jobs\SendEventRegistrationNotification;
use App\Models\EventRegistrationNotification;
use Throwable;

class EventRegistrationNotificationDispatchService
{
    public function dispatchNotification(int $notificationId): bool
    {
        $notification = EventRegistrationNotification::query()->find($notificationId);
        if (!$notification || $notification->status !== 'pending') {
            return true;
        }

        $notification->forceFill([
            'dispatch_attempts' => (int) $notification->dispatch_attempts + 1,
            'dispatch_last_attempt_at' => now(),
            'dispatch_last_error' => null,
        ])->save();

        try {
            $this->pushNotificationJob((int) $notification->id);
            return true;
        } catch (Throwable $exception) {
            $notification->forceFill([
                'dispatch_last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            return false;
        }
    }

    protected function pushNotificationJob(int $notificationId): void
    {
        SendEventRegistrationNotification::dispatch($notificationId);
    }
}
