<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OttRenewal;
use App\Models\OttUser;
use App\Models\OttAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OttRenewalSwitch extends Command
{
    protected $signature = 'ott:renewal-switch {year?}';
    protected $description = 'Switch OTT renewals to active users for the target year';

    public function handle()
    {
        $year = $this->argument('year') ?? date('Y');
        $this->info("Starting OTT Renewal Switch for Year: $year");

        // 1. Get all paid renewals for the target year
        $renewals = OttRenewal::where('target_year', $year)
            ->where('is_paid', true)
            ->get();

        if ($renewals->isEmpty()) {
            $this->info("No paid renewals found for $year.");
            return;
        }

        $count = 0;
        DB::beginTransaction();
        try {
            foreach ($renewals as $renewal) {
                // 2. Update or Create OttUser
                // Calculate expiration: End of the target year
                $expiredAt = strtotime("$year-12-31 23:59:59");

                $ottUser = OttUser::where('user_id', $renewal->user_id)
                    ->where('account_id', $renewal->account_id)
                    ->first();

                if ($ottUser) {
                    $ottUser->expired_at = $expiredAt;
                    $ottUser->sub_account_id = $renewal->sub_account_id;
                    $ottUser->sub_account_pin = $renewal->sub_account_pin;
                    $ottUser->save();
                } else {
                    OttUser::create([
                        'user_id' => $renewal->user_id,
                        'account_id' => $renewal->account_id,
                        'expired_at' => $expiredAt,
                        'sub_account_id' => $renewal->sub_account_id,
                        'sub_account_pin' => $renewal->sub_account_pin
                    ]);
                }

                // 3. Update Account Pricing for the new year
                // This is tricky because multiple users share an account.
                // We should only update the account ONCE.
                // But simpler logic: The account settings (next_price) should be manually promoted or we just trust the renewal data.
                // Let's check if we need to promote next_price to current price.
                $account = OttAccount::find($renewal->account_id);
                if ($account && $account->next_price_yearly) {
                    $account->price_yearly = $account->next_price_yearly;
                    $account->shared_seats = $account->next_shared_seats ?? $account->shared_seats;
                    // Clear next settings to avoid re-applying or confusion
                    $account->next_price_yearly = null;
                    $account->next_shared_seats = null;
                    $account->save();
                }

                $count++;
            }

            DB::commit();
            $this->info("Successfully switched $count renewals.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Failed to switch renewals: " . $e->getMessage());
            Log::error("OttRenewalSwitch Error: " . $e->getMessage());
        }
    }
}
