<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ApplicationMailDelivery;
use App\Models\Customer;
use App\Models\DataExport;
use App\Models\Organizer;
use App\Models\ScannerStaff;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Throwable;

class DataExportReadyNotificationService
{
    public function dispatchNotification(int $exportId): bool
    {
        try {
            $deliveryId = DB::transaction(function () use ($exportId): ?int {
                $export = DataExport::query()->lockForUpdate()->find($exportId);
                if (!$export || $export->status !== 'ready' || !$export->isDownloadable()) {
                    return null;
                }
                if ($export->ready_notification_delivery_id) {
                    return (int) $export->ready_notification_delivery_id;
                }

                $export->forceFill([
                    'ready_notification_attempts' => (int) $export->ready_notification_attempts + 1,
                    'ready_notification_last_attempt_at' => now(),
                    'ready_notification_last_error' => null,
                    'ready_notification_alerted_at' => null,
                ])->save();

                $recipient = $this->recipient($export);
                $downloadUrl = URL::temporarySignedRoute(
                    $this->downloadRoute($export),
                    $export->expires_at,
                    ['export' => $export]
                );
                $plainAppName = trim((string) config('app.name', 'Tica')) ?: 'Tica';
                $appName = e($plainAppName);
                $filename = e((string) $export->filename);
                $expires = e($export->expires_at->timezone((string) config('app.timezone'))->format('j M Y, g:i A T'));
                $safeUrl = e($downloadUrl);

                $delivery = ApplicationMailDelivery::create([
                    'public_id' => (string) \Illuminate\Support\Str::uuid(),
                    'kind' => 'data_export.ready',
                    'source_type' => DataExport::class,
                    'source_id' => $export->id,
                    'created_by_type' => 'system',
                    'created_by_id' => null,
                    'recipient_hash' => hash_hmac('sha256', $recipient, (string) config('app.key')),
                    'recipient_email' => $recipient,
                    'subject' => 'Your '.$plainAppName.' export is ready',
                    'body' => '<p>Your private export <strong>'.$filename.'</strong> is ready.</p>'
                        .'<p><a href="'.$safeUrl.'">Download your export</a></p>'
                        .'<p>This secure link expires on '.$expires.'. Sign in with the account that requested the export.</p>',
                    'attachments' => [],
                    'reply_to' => null,
                    'status' => 'pending',
                ]);

                $export->forceFill([
                    'ready_notification_delivery_id' => $delivery->id,
                ])->save();

                return (int) $delivery->id;
            }, 3);

            if ($deliveryId === null) {
                return true;
            }

            app(ApplicationMailService::class)->dispatchDelivery($deliveryId);

            return true;
        } catch (Throwable $exception) {
            DataExport::query()->whereKey($exportId)->whereNull('ready_notification_delivery_id')
                ->update([
                    'ready_notification_attempts' => DB::raw('ready_notification_attempts + 1'),
                    'ready_notification_last_attempt_at' => now(),
                    'ready_notification_last_error' => encrypt(mb_substr($exception->getMessage(), 0, 2000)),
                    'updated_at' => now(),
                ]);

            return false;
        }
    }

    private function recipient(DataExport $export): string
    {
        $actor = match ($export->requested_by_type) {
            'admin' => Admin::query()->find($export->requested_by_id),
            'organizer' => Organizer::query()->find($export->requested_by_id),
            'customer' => Customer::query()->find($export->requested_by_id),
            'scanner' => ScannerStaff::query()->find($export->requested_by_id),
            default => null,
        };
        $email = $actor instanceof Model ? mb_strtolower(trim((string) $actor->getAttribute('email'))) : '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('The export requester no longer has a valid email address.');
        }

        return $email;
    }

    private function downloadRoute(DataExport $export): string
    {
        if ($export->requested_by_type === 'customer'
            && $export->export_type === 'customer_personal_data') {
            return 'customer.data-export.download';
        }
        if ($export->requested_by_type === 'scanner'
            && $export->export_type === 'check_in_ledger') {
            return 'organizer.scanner.check_in_ledger_exports.download';
        }
        if ($export->requested_by_type === 'organizer') {
            return match ($export->export_type) {
                'withdrawals' => 'organizer.withdraw_exports.download',
                'transactions' => 'organizer.transaction_exports.download',
                'check_in_ledger' => 'organizer.check_in_ledger_exports.download',
                'bookings', 'event_registrations', 'event_guest_list', 'organizer_finance'
                    => 'organizer.data_exports.download',
                default => throw new DomainException('This organizer export has no authorized download route.'),
            };
        }
        if ($export->requested_by_type === 'admin') {
            return match ($export->export_type) {
                'bookings' => 'admin.data_exports.download',
                'withdrawals' => 'admin.withdraw_exports.download',
                'transactions' => 'admin.transaction_exports.download',
                'product_orders' => 'admin.product_order_exports.download',
                'audit_logs' => 'admin.audit_logs.download',
                'operational_incidents', 'webhook_receipts' => 'admin.operations_exports.download',
                'payment_reconciliation_runs', 'gateway_settlements',
                'payment_reconciliation_entries', 'gateway_settlement_entries'
                    => 'admin.reconciliation_exports.download',
                'event_cancellation_cases' => 'admin.event_cancellation_exports.download',
                default => throw new DomainException('This administrator export has no authorized download route.'),
            };
        }

        throw new DomainException('This export requester type cannot receive a completion notice.');
    }
}
