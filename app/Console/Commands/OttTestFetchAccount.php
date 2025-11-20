<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OttTestFetchAccount extends Command
{
    protected $signature = 'ott:test-fetch {user_id}';
    protected $description = '测试 OTT fetchAccount API，直接调试绑定关系';

    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $this->info("=== 测试用户 ID: {$userId} ===");
        $this->line('');

        // 1. 获取用户信息
        $user = User::find($userId);
        if (!$user) {
            $this->error("用户不存在: {$userId}");
            return 1;
        }

        $this->info("1. 用户信息:");
        $this->line("   - ID: {$user->id}");
        $this->line("   - Email: {$user->email}");
        $this->line("   - is_ott: " . ($user->is_ott ? 'true' : 'false'));
        $this->line('');

        // 2. 直接查询 v2_ott_user 表
        $ottUserRecords = DB::table('v2_ott_user')
            ->where('user_id', $userId)
            ->get();

        $this->info("2. 直接查询 v2_ott_user 表:");
        $this->line("   - 绑定记录数: " . $ottUserRecords->count());
        
        if ($ottUserRecords->count() > 0) {
            foreach ($ottUserRecords as $record) {
                $this->line("   - Account ID: {$record->account_id}, Expired At: " . date('Y-m-d H:i:s', $record->expired_at));
                $this->line("     Sub Account ID: " . ($record->sub_account_id ?? 'null'));
                $this->line("     Sub Account PIN: " . ($record->sub_account_pin ?? 'null'));
            }
        } else {
            $this->warn("   ⚠️  没有找到绑定记录！");
        }
        $this->line('');

        // 3. 通过 Eloquent 关系查询
        $accounts = $user->ottAccounts()->get();
        
        $this->info("3. 通过 Eloquent 关系查询 (ottAccounts()):");
        $this->line("   - 加载的账号数: " . $accounts->count());
        
        if ($accounts->count() > 0) {
            foreach ($accounts as $account) {
                $this->line("   - Account ID: {$account->id}, Name: {$account->name}, Type: {$account->type}");
                
                if ($account->pivot) {
                    $this->line("     ✓ Pivot 数据存在");
                    $this->line("       Expired At: " . date('Y-m-d H:i:s', $account->pivot->expired_at ?? 0));
                    $this->line("       Sub Account ID: " . ($account->pivot->sub_account_id ?? 'null'));
                } else {
                    $this->warn("     ⚠️  Pivot 数据不存在！");
                }
            }
        } else {
            $this->warn("   ⚠️  没有加载到任何账号！");
        }
        $this->line('');

        // 4. 对比分析
        $this->info("4. 对比分析:");
        $directCount = $ottUserRecords->count();
        $relationCount = $accounts->count();
        
        if ($directCount > 0 && $relationCount == 0) {
            $this->error("   ❌ 问题：数据库有绑定记录，但关系查询返回空！");
            $this->line("   可能原因：");
            $this->line("   1. OttAccount 表中的账号不存在");
            $this->line("   2. belongsToMany 关系配置有问题");
            $this->line("   3. 账号被软删除或过滤");
            
            // 检查账号是否存在
            $accountIds = $ottUserRecords->pluck('account_id')->unique();
            $this->line('');
            $this->line("   检查绑定的账号是否存在:");
            foreach ($accountIds as $accountId) {
                $account = DB::table('v2_ott_account')->find($accountId);
                if ($account) {
                    $this->line("     ✓ Account ID {$accountId} 存在: {$account->name}");
                } else {
                    $this->error("     ✗ Account ID {$accountId} 不存在！");
                }
            }
        } elseif ($directCount == 0 && $relationCount == 0) {
            $this->warn("   ⚠️  数据库和关系查询都没有找到记录");
            $this->line("   建议：检查管理后台是否正确绑定了用户");
        } elseif ($directCount == $relationCount) {
            $this->info("   ✓ 数据库和关系查询结果一致");
        }

        // 5. 模拟控制器逻辑
        $this->line('');
        $this->info("5. 模拟控制器返回的数据:");
        $data = [];
        
        foreach ($accounts as $account) {
            if (!$account->pivot) {
                continue;
            }

            $expiredAt = $account->pivot->expired_at ?? 0;
            $isExpired = $expiredAt < time();
            
            $item = [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'expired_at' => $expiredAt,
                'status' => $isExpired ? 'expired' : 'active',
            ];
            
            $data[] = $item;
        }

        $this->line("   - 返回数据数: " . count($data));
        if (count($data) > 0) {
            $this->line("   - 数据内容:");
            foreach ($data as $item) {
                $this->line("     • {$item['name']} ({$item['type']}) - {$item['status']}");
            }
        } else {
            $this->warn("   ⚠️  没有可返回的数据");
        }

        return 0;
    }
}

