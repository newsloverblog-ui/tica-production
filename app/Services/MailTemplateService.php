<?php

namespace App\Services;

use App\Models\ApplicationMailDelivery;
use App\Models\BasicSettings\MailTemplate;
use App\Support\MailTemplateCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MailTemplateService
{
    public function render(string $type, array $data = []): array
    {
        $definition = MailTemplateCatalog::definitions()[$type] ?? null;
        $template = Schema::hasTable('mail_templates')
            ? MailTemplate::query()->where('mail_type', $type)->first()
            : null;

        if (!$definition && !$template) {
            throw new \InvalidArgumentException("Unsupported mail template: {$type}");
        }

        $settings = Schema::hasTable('basic_settings')
            ? DB::table('basic_settings')->select('website_title')->first()
            : null;
        $data = $this->aliases($data) + [
            'website_title' => (string) ($settings?->website_title ?: config('app.name', 'TICA')),
        ];
        $subject = (string) ($template?->mail_subject ?: $definition['subject']);
        $body = (string) ($template?->mail_body ?: $definition['body']);

        foreach (array_unique(array_merge(MailTemplateCatalog::placeholders($type), array_keys($data))) as $placeholder) {
            $value = $data[$placeholder] ?? '';
            if (is_bool($value)) $value = $value ? 'Yes' : 'No';
            if (!is_scalar($value) && $value !== null) $value = '';
            $escaped = e((string) $value);
            $subject = str_replace('{'.$placeholder.'}', strip_tags($escaped), $subject);
            $body = str_replace('{'.$placeholder.'}', $escaped, $body);
        }

        return ['subject' => trim($subject), 'html' => $body, 'type' => $type];
    }

    public function queueSafely(
        string $type,
        ?string $recipient,
        array $data = [],
        ?Model $source = null,
        ?string $dedupeKey = null,
        array $attachments = []
    ): ?ApplicationMailDelivery {
        $recipient = mb_strtolower(trim((string) $recipient));
        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)
            || !Schema::hasTable('application_mail_deliveries')) {
            return null;
        }

        try {
            $rendered = $this->render($type, $data);
            if ($source || $dedupeKey) {
                $recipientHash = hash_hmac('sha256', $recipient, (string) config('app.key'));
                $query = ApplicationMailDelivery::query()
                    ->where('kind', 'template.'.$type)
                    ->where('recipient_hash', $recipientHash)
                    ->when($source, fn ($q) => $q
                        ->where('source_type', get_class($source))
                        ->where('source_id', $source->getKey()))
                    ->when(!$source, fn ($q) => $q
                        ->whereNull('source_type')
                        ->whereNull('source_id'));
                if (Schema::hasColumn('application_mail_deliveries', 'dedupe_key')) {
                    $query->when($dedupeKey, fn ($q) => $q->where('dedupe_key', hash('sha256', $dedupeKey)))
                        ->when(!$dedupeKey, fn ($q) => $q->whereNull('dedupe_key'));
                }
                if ($existing = $query->first()) return $existing;
            }

            return app(ApplicationMailService::class)->queueHtml(
                'template.'.$type,
                $recipient,
                $rendered['subject'],
                $rendered['html'],
                $attachments,
                null,
                $source,
                $dedupeKey
            );
        } catch (Throwable $exception) {
            Log::error('Template email could not be queued.', [
                'mail_type' => $type,
                'source_type' => $source ? get_class($source) : null,
                'source_id' => $source?->getKey(),
                'error' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    public function queueAdminSafely(string $type, array $data = [], ?Model $source = null, ?string $dedupeKey = null): ?ApplicationMailDelivery
    {
        $recipient = Schema::hasTable('basic_settings')
            ? DB::table('basic_settings')->value('to_mail') ?: DB::table('basic_settings')->value('email_address')
            : null;
        return $this->queueSafely($type, $recipient, $data, $source, $dedupeKey);
    }

    public static function maskAccount(?string $number): string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);
        return $digits === '' ? 'Not provided' : str_repeat('*', max(6, strlen($digits) - 4)).substr($digits, -4);
    }

    private function aliases(array $data): array
    {
        $data['customer_name'] ??= $data['first_name'] ?? $data['buyer_name'] ?? null;
        $data['organizer_name'] ??= $data['first_name'] ?? $data['organizer_username'] ?? null;
        $data['account_name'] ??= $data['customer_name'] ?? $data['organizer_name'] ?? $data['username'] ?? null;
        $data['event_title'] ??= $data['event_name'] ?? $data['title'] ?? null;
        $data['event_venue'] ??= $data['venue'] ?? null;
        $data['ticket_quantity'] ??= $data['ticket_count'] ?? null;
        $data['amount'] ??= $data['refund_amount'] ?? $data['payout_amount'] ?? $data['gross_amount'] ?? null;
        $data['reference'] ??= $data['refund_reference'] ?? $data['payout_reference'] ?? $data['order_reference'] ?? null;
        $data['dashboard_link'] ??= $data['finance_url'] ?? $data['order_url'] ?? $data['analytics_url'] ?? $data['account_url'] ?? null;
        $data['event_link'] ??= $data['event_url'] ?? null;
        $data['ticket_link'] ??= $data['tickets_url'] ?? $data['order_url'] ?? null;
        $data['verification_link'] ??= $data['verification_url'] ?? null;
        $data['refund_info'] ??= $data['refund_timing'] ?? $data['refund_message'] ?? null;
        $data['dashboard_link'] ??= $data['refund_url'] ?? $data['account_url'] ?? null;
        if (!isset($data['masked_account_number'])) {
            $data['masked_account_number'] = isset($data['account_last4'])
                ? '******'.ltrim((string) $data['account_last4'], '*')
                : self::maskAccount($data['bank_account'] ?? null);
        }
        return array_filter($data, fn ($value) => $value !== null);
    }
}
