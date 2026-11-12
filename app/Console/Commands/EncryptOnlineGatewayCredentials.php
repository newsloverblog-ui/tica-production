<?php

namespace App\Console\Commands;

use App\Models\PaymentGateway\OnlineGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EncryptOnlineGatewayCredentials extends Command
{
    protected $signature = 'gateways:encrypt-credentials
        {--apply : Persist encryption; without this flag the command is a dry run}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Encrypt legacy online-gateway configuration blobs with the application key';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $rows = DB::table('online_gateways')->select(['id', 'keyword', 'information'])->orderBy('id')->get();
        $pending = $rows->filter(
            static fn (object $row): bool => !OnlineGateway::informationIsEncrypted((string) $row->information)
        );

        if ($apply && $pending->isNotEmpty()) {
            DB::transaction(function () use ($pending): void {
                foreach ($pending as $row) {
                    DB::table('online_gateways')->where('id', $row->id)->update([
                        'information' => OnlineGateway::encryptInformation((string) $row->information),
                    ]);
                }
            });
        }

        $result = [
            'mode' => $apply ? 'apply' : 'dry-run',
            'total' => $rows->count(),
            'pending' => $pending->count(),
            'encrypted' => $apply ? $pending->count() : 0,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info(sprintf(
                '%s: %d gateway row(s) inspected; %d %s.',
                $apply ? 'Applied' : 'Dry run',
                $result['total'],
                $result['pending'],
                $apply ? 'encrypted' : 'require encryption'
            ));
            if (!$apply && $result['pending'] > 0) {
                $this->warn('Review the count, back up the database, then rerun with --apply.');
            }
        }

        return self::SUCCESS;
    }
}
