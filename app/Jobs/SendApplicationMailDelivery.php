<?php

namespace App\Jobs;

use App\Models\ApplicationMailDelivery;
use App\Models\DataExport;
use App\Services\ConfiguredMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendApplicationMailDelivery implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $uniqueFor = 3600;

    public function __construct(public int $deliveryId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'application-mail-delivery:'.$this->deliveryId;
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter(900)];
    }

    public function handle(ConfiguredMailer $mailer): void
    {
        $delivery = DB::transaction(function (): ?ApplicationMailDelivery {
            $locked = ApplicationMailDelivery::query()->lockForUpdate()->findOrFail($this->deliveryId);
            if ($locked->status === 'sent') {
                return null;
            }
            $locked->forceFill([
                'status' => 'processing',
                'attempts' => (int) $locked->attempts + 1,
                'processing_at' => now(),
                'last_error' => null,
                'alerted_at' => null,
            ])->save();

            return $locked;
        }, 3);

        if (!$delivery) {
            return;
        }

        try {
            $mailer->sendHtml(
                (string) $delivery->recipient_email,
                (string) $delivery->subject,
                (string) $delivery->body,
                (array) ($delivery->attachments ?: []),
                $delivery->reply_to ? (string) $delivery->reply_to : null
            );
            $delivery->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'processing_at' => null,
                'last_error' => null,
            ])->save();
            if ($delivery->kind === 'data_export.ready'
                && $delivery->source_type === DataExport::class
                && $delivery->source_id) {
                DataExport::query()
                    ->whereKey($delivery->source_id)
                    ->where('ready_notification_delivery_id', $delivery->id)
                    ->update(['ready_notified_at' => now(), 'updated_at' => now()]);
            }
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => 'pending',
                'processing_at' => null,
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $delivery = ApplicationMailDelivery::query()->find($this->deliveryId);
        if (!$delivery || $delivery->status === 'sent') {
            return;
        }
        $delivery->forceFill([
            'status' => 'failed',
            'processing_at' => null,
            'failed_at' => now(),
            'last_error' => mb_substr($exception->getMessage(), 0, 2000),
        ])->save();
    }
}
