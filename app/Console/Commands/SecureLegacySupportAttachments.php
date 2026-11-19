<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\SupportTicket;
use App\Services\SupportTicketAttachmentStorage;
use Illuminate\Console\Command;

class SecureLegacySupportAttachments extends Command
{
    protected $signature = 'support:secure-attachments
        {--dry-run : Report legacy references without changing files or rows}';

    protected $description = 'Move legacy public support-ticket ZIP files into private attachment storage';

    public function handle(SupportTicketAttachmentStorage $storage): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $counts = [
            'legacy' => 0,
            'migrated' => 0,
            'missing' => 0,
            'managed' => 0,
        ];

        SupportTicket::query()
            ->whereNotNull('attachment')
            ->orderBy('id')
            ->chunkById(100, function ($tickets) use ($storage, $dryRun, &$counts): void {
                foreach ($tickets as $ticket) {
                    if ($storage->isManagedPath($ticket->attachment)) {
                        $counts['managed']++;
                        continue;
                    }

                    $counts['legacy']++;
                    if ($dryRun) {
                        continue;
                    }

                    $legacyDirectory = $ticket->user_type === 'organizer'
                        ? 'assets/admin/img/support-ticket/attachment/'
                        : 'assets/admin/img/support-ticket/';
                    $legacyPath = $legacyDirectory . $ticket->attachment;
                    $migrated = $storage->migrateLegacy(
                        (string) $ticket->attachment,
                        $legacyPath,
                        'attachments'
                    );

                    if (!$migrated) {
                        $counts['missing']++;
                        continue;
                    }

                    $ticket->forceFill(['attachment' => $migrated])->saveQuietly();
                    $storage->deleteLegacy($legacyPath);
                    $counts['migrated']++;
                }
            });

        Conversation::query()
            ->whereNotNull('file')
            ->orderBy('id')
            ->chunkById(100, function ($replies) use ($storage, $dryRun, &$counts): void {
                foreach ($replies as $reply) {
                    if ($storage->isManagedPath($reply->file)) {
                        $counts['managed']++;
                        continue;
                    }

                    $counts['legacy']++;
                    if ($dryRun) {
                        continue;
                    }

                    $legacyPath = 'assets/admin/img/support-ticket/' . $reply->file;
                    $migrated = $storage->migrateLegacy(
                        (string) $reply->file,
                        $legacyPath,
                        'replies'
                    );

                    if (!$migrated) {
                        $counts['missing']++;
                        continue;
                    }

                    $reply->forceFill(['file' => $migrated])->saveQuietly();
                    $storage->deleteLegacy($legacyPath);
                    $counts['migrated']++;
                }
            });

        $this->table(['legacy', 'migrated', 'missing', 'already private'], [[
            $counts['legacy'],
            $counts['migrated'],
            $counts['missing'],
            $counts['managed'],
        ]]);

        if ($dryRun) {
            $this->comment('Dry run only. No files or database rows were changed.');
            return self::SUCCESS;
        }

        if ($counts['missing'] > 0) {
            $this->error('Some legacy database references did not resolve to a file. No reference was overwritten.');
            return self::FAILURE;
        }

        $this->info('All discovered support-ticket attachments are now in private storage.');
        return self::SUCCESS;
    }
}
