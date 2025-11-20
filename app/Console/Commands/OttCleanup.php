<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OttAccount;
use App\Models\OttMessage;
use Illuminate\Support\Facades\Log;

class OttCleanup extends Command
{
    protected $signature = 'ott:cleanup';
    protected $description = 'Clean up expired OTT messages based on account validity settings';

    public function handle()
    {
        $this->info("Starting OTT Message Cleanup...");

        $accounts = OttAccount::all();
        $totalDeleted = 0;

        foreach ($accounts as $account) {
            $validityMinutes = $account->otp_validity_minutes ?? 10;
            // Add a small buffer (e.g. 1 minute) just in case, or strict. Strict is fine.
            $expireTimestamp = time() - ($validityMinutes * 60);

            $deleted = OttMessage::where('account_id', $account->id)
                ->where('received_at', '<', $expireTimestamp)
                ->delete();

            if ($deleted > 0) {
                $totalDeleted += $deleted;
                $this->info("Account {$account->id} ({$account->name}): Deleted $deleted expired messages.");
            }
        }

        $this->info("Cleanup complete. Total messages deleted: $totalDeleted");
        Log::info("OttCleanup: Deleted $totalDeleted expired messages.");
    }
}
