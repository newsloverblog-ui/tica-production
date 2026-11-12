<?php

namespace App\Services;

use App\Models\ApplicationMailDelivery;
use App\Support\LifecycleMailCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Throwable;

class LifecycleMailService
{
    public function render(string $kind, array $data = []): array
    {
        $message = LifecycleMailCatalog::compose($kind, $data);

        return [
            'subject' => $message['subject'],
            'html' => View::make('emails.lifecycle', ['message' => $message])->render(),
            'message' => $message,
        ];
    }

    public function queueOnce(
        string $kind,
        string $recipient,
        array $data = [],
        ?Model $source = null,
        array $attachments = [],
        ?string $dedupeKey = null
    ): ApplicationMailDelivery {
        $recipient = mb_strtolower(trim($recipient));
        $recipientHash = hash_hmac('sha256', $recipient, (string) config('app.key'));
        if ($source) {
            $query = ApplicationMailDelivery::query()
                ->where('kind', $kind)
                ->where('source_type', get_class($source))
                ->where('source_id', $source->getKey())
                ->where('recipient_hash', $recipientHash);
            if (Schema::hasColumn('application_mail_deliveries', 'dedupe_key')) {
                $query->when($dedupeKey, fn ($builder) => $builder->where('dedupe_key', hash('sha256', $dedupeKey)))
                    ->when(!$dedupeKey, fn ($builder) => $builder->whereNull('dedupe_key'));
            }
            $existing = $query->first();
            if ($existing) {
                return $existing;
            }
        }

        $rendered = $this->render($kind, $data);

        return app(ApplicationMailService::class)->queueHtml(
            $kind,
            $recipient,
            $rendered['subject'],
            $rendered['html'],
            $attachments,
            null,
            $source,
            $dedupeKey
        );
    }

    /**
     * Queue a lifecycle message without allowing a mail outage or an installation
     * that has not migrated the delivery ledger to roll back the business action.
     */
    public function queueSafely(
        string $kind,
        ?string $recipient,
        array $data = [],
        ?Model $source = null,
        array $attachments = [],
        ?string $dedupeKey = null
    ): ?ApplicationMailDelivery {
        $recipient = mb_strtolower(trim((string) $recipient));
        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)
            || !Schema::hasTable('application_mail_deliveries')) {
            return null;
        }

        try {
            return $this->queueOnce($kind, $recipient, $data, $source, $attachments, $dedupeKey);
        } catch (Throwable $exception) {
            Log::error('Lifecycle email could not be queued.', [
                'kind' => $kind,
                'source_type' => $source ? get_class($source) : null,
                'source_id' => $source?->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
