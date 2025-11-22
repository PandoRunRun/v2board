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
        $this->info("Starting OTT Renewal Switch for Year: $year (GMT+8)");

        DB::beginTransaction();
        try {
            // 1. 清理上一年的到期用户（过期时间在去年年底之前的）
            $lastYear = $year - 1;
            $lastYearEnd = strtotime("$lastYear-12-31 23:59:59");
            $deletedExpiredCount = OttUser::where('expired_at', '<=', $lastYearEnd)->delete();
            $this->info("Deleted $deletedExpiredCount expired users from previous year.");

            // 2. 清理当前年份未付费的renewal对应的用户
            // 找到所有target_year为当前年份，但is_paid为false的renewal
            $unpaidRenewals = OttRenewal::where('target_year', $year)
                ->where('is_paid', false)
                ->get();
            
            $deletedUnpaidCount = 0;
            foreach ($unpaidRenewals as $renewal) {
                $deleted = OttUser::where('user_id', $renewal->user_id)
                    ->where('account_id', $renewal->account_id)
                    ->delete();
                if ($deleted > 0) {
                    $deletedUnpaidCount++;
                }
            }
            $this->info("Removed access for $deletedUnpaidCount unpaid renewals.");

            // 3. 激活已付费的renewal
            $renewals = OttRenewal::where('target_year', $year)
                ->where('is_paid', true)
                ->get();

            if ($renewals->isEmpty()) {
                $this->info("No paid renewals found for $year.");
                DB::commit();
                return;
            }

            $count = 0;
            $processedAccounts = []; // 避免重复更新账户价格

            foreach ($renewals as $renewal) {
                // 设置过期时间为目标年的最后一天 23:59:59 (GMT+8)
                // strtotime() 会使用 Laravel 配置的时区 (Asia/Shanghai)
                $expiredAt = strtotime("$year-12-31 23:59:59");

                $ottUser = OttUser::where('user_id', $renewal->user_id)
                    ->where('account_id', $renewal->account_id)
                    ->first();

                if ($ottUser) {
                    // 更新现有用户
                    $ottUser->expired_at = $expiredAt;
                    $ottUser->sub_account_id = $renewal->sub_account_id;
                    $ottUser->sub_account_pin = $renewal->sub_account_pin;
                    $ottUser->save();
                } else {
                    // 创建新用户
                    OttUser::create([
                        'user_id' => $renewal->user_id,
                        'account_id' => $renewal->account_id,
                        'expired_at' => $expiredAt,
                        'sub_account_id' => $renewal->sub_account_id,
                        'sub_account_pin' => $renewal->sub_account_pin
                    ]);
                }

                // 每个账户只更新一次价格（避免重复更新）
                if (!isset($processedAccounts[$renewal->account_id])) {
                    $account = OttAccount::find($renewal->account_id);
                    if ($account && $account->next_price_yearly) {
                        $account->price_yearly = $account->next_price_yearly;
                        $account->shared_seats = $account->next_shared_seats ?? $account->shared_seats;
                        // 清空下一年的设置，避免重复应用
                        $account->next_price_yearly = null;
                        $account->next_shared_seats = null;
                        $account->save();
                        $this->info("Updated account {$account->id} pricing for year $year.");
                    }
                    $processedAccounts[$renewal->account_id] = true;
                }

                $count++;
            }

            DB::commit();
            $this->info("Successfully switched $count renewals for year $year.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Failed to switch renewals: " . $e->getMessage());
            Log::error("OttRenewalSwitch Error: " . $e->getMessage(), [
                'year' => $year,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
