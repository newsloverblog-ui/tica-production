<?php

namespace App\Services;

use App\Jobs\SendApplicationMailDelivery;
use App\Models\ApplicationMailDelivery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class ApplicationMailService
{
    public function queueHtml(
        string $kind,
        string $recipient,
        string $subject,
        string $body,
        array $attachments = [],
        ?string $replyTo = null,
        ?Model $source = null,
        ?string $dedupeKey = null
    ): ApplicationMailDelivery {
        $kind = trim($kind);
        $recipient = mb_strtolower(trim($recipient));
        $subject = trim($subject);
        if ($kind === '' || mb_strlen($kind) > 80) {
            throw new InvalidArgumentException('A valid mail delivery kind is required.');
        }
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid recipient email address is required.');
        }
        if ($subject === '' || mb_strlen($subject) > 998 || trim(strip_tags($body)) === '') {
            throw new InvalidArgumentException('A valid mail subject and body are required.');
        }
        foreach ($attachments as $path) {
            if (!is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) {
                throw new InvalidArgumentException('A mail attachment is missing or unreadable.');
            }
        }
        if ($replyTo !== null && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid reply-to email address is required.');
        }
        [$actorType, $actorId] = $this->actor();

        $delivery = DB::transaction(function () use (
            $kind, $recipient, $subject, $body, $attachments, $replyTo, $source, $actorType, $actorId, $dedupeKey
        ): ApplicationMailDelivery {
            $attributes = [
                'public_id' => (string) Str::uuid(),
                'kind' => $kind,
                'source_type' => $source ? get_class($source) : null,
                'source_id' => $source?->getKey(),
                'created_by_type' => $actorType,
                'created_by_id' => $actorId,
                'recipient_hash' => hash_hmac('sha256', $recipient, (string) config('app.key')),
                'recipient_email' => $recipient,
                'subject' => $subject,
                'body' => $body,
                'attachments' => array_values($attachments),
                'reply_to' => $replyTo,
                'status' => 'pending',
            ];
            if (Schema::hasColumn('application_mail_deliveries', 'dedupe_key')) {
                $attributes['dedupe_key'] = $dedupeKey ? hash('sha256', $dedupeKey) : null;
            }
            $delivery = ApplicationMailDelivery::create($attributes);

            return $delivery;
        }, 3);

        $this->dispatchDelivery((int) $delivery->id);

        return $delivery;
    }

    public function dispatchDelivery(int $deliveryId): bool
    {
        $delivery = ApplicationMailDelivery::query()->find($deliveryId);
        if (!$delivery || $delivery->status !== 'pending') {
            return true;
        }
        $delivery->forceFill([
            'dispatch_attempts' => (int) $delivery->dispatch_attempts + 1,
            'dispatch_last_attempt_at' => now(),
            'dispatch_last_error' => null,
        ])->save();
        try {
            $this->pushDeliveryJob($delivery->id);
            return true;
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'dispatch_last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            return false;
        }
    }

    protected function pushDeliveryJob(int $deliveryId): void
    {
        SendApplicationMailDelivery::dispatch($deliveryId);
    }

    private function actor(): array
    {
        foreach (['admin', 'organizer', 'scanner', 'customer', 'web'] as $guard) {
            if (Auth::guard($guard)->check()) {
                return [$guard, Auth::guard($guard)->id()];
            }
        }

        return ['system', null];
    }
}
