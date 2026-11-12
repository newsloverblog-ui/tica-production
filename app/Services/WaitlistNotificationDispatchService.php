<?php

namespace App\Services;

use App\Jobs\SendEventWaitlistAvailability;
use App\Models\EventWaitlistEntry;
use Throwable;

class WaitlistNotificationDispatchService
{
    public function dispatchNotification(int $entryId): bool
    {
        $entry = EventWaitlistEntry::query()->find($entryId);
        if (!$entry || $entry->status !== 'notification_pending') {
            return true;
        }

        if (!$entry->notification_expires_at || $entry->notification_expires_at->isPast()) {
            $entry->forceFill([
                'status' => 'expired',
                'processing_at' => null,
                'dispatch_last_error' => null,
            ])->save();
            return true;
        }

        $entry->forceFill([
            'dispatch_attempts' => (int) $entry->dispatch_attempts + 1,
            'dispatch_last_attempt_at' => now(),
            'dispatch_last_error' => null,
        ])->save();

        try {
            $this->pushNotificationJob((int) $entry->id);
            return true;
        } catch (Throwable $exception) {
            $entry->forceFill([
                'dispatch_last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            return false;
        }
    }

    protected function pushNotificationJob(int $entryId): void
    {
        SendEventWaitlistAvailability::dispatch($entryId);
    }
}
