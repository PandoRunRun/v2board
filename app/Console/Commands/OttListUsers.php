<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OttListUsers extends Command
{
    protected $signature = 'ott:list-users {--email=}';
    protected $description = '列出所有 OTT 用户，或根据邮箱搜索';

    public function handle()
    {
        $email = $this->option('email');
        
        if ($email) {
            $users = User::where('email', 'like', "%{$email}%")->get();
        } else {
            // 列出所有 is_ott = 1 的用户，或者有 OTT 绑定的用户
            $users = User::where('is_ott', true)
                ->orWhereIn('id', function($query) {
                    $query->select('user_id')->from('v2_ott_user');
                })
                ->get();
        }

        if ($users->isEmpty()) {
            $this->warn("没有找到用户");
            return 1;
        }

        $this->info("找到 " . $users->count() . " 个用户:");
        $this->line('');

        $headers = ['ID', 'Email', 'is_ott', 'OTT 绑定数'];
        $rows = [];

        foreach ($users as $user) {
            $bindCount = DB::table('v2_ott_user')->where('user_id', $user->id)->count();
            $rows[] = [
                $user->id,
                $user->email,
                $user->is_ott ? '✓' : '✗',
                $bindCount
            ];
        }

        $this->table($headers, $rows);
        
        return 0;
    }
}

