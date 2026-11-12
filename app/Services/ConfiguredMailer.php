<?php

namespace App\Services;

use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ConfiguredMailer
{
    /**
     * Send HTML through Laravel's configured transport. Callers may attach only
     * already-authorized private files; this service never resolves storage paths.
     */
    public function sendHtml(
        string $recipient,
        string $subject,
        string $html,
        array $attachments = [],
        ?string $replyTo = null
    ): void {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid recipient email address is required.');
        }

        foreach ($attachments as $path) {
            if (!is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) {
                throw new InvalidArgumentException('A mail attachment is missing or unreadable.');
            }
        }

        $settings = DB::table('basic_settings')->select('from_mail', 'from_name')->first();
        $from = filter_var($settings?->from_mail, FILTER_VALIDATE_EMAIL)
            ? (string) $settings->from_mail
            : null;
        $fromName = trim((string) ($settings?->from_name ?: config('app.name')));
        $validReplyTo = filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? $replyTo : null;

        Mail::html($html, function ($message) use (
            $recipient, $subject, $attachments, $from, $fromName, $validReplyTo
        ): void {
            $message->to($recipient)->subject($subject);
            if ($from) {
                $message->from($from, $fromName);
            }
            if ($validReplyTo) {
                $message->replyTo($validReplyTo);
            }
            foreach ($attachments as $path) {
                $message->attach($path);
            }
        });
    }
}
