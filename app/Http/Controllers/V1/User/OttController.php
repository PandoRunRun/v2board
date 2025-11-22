<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Models\OttAccount;
use App\Models\OttMessage;
use App\Models\OttRenewal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class OttController extends Controller
{
    public function fetchAccount(Request $request)
    {
        try {
            $userArray = $request->user; // decryptAuthData 返回的是数组
            $userId = $userArray['id'] ?? null;
            
            // Debug: Log user info
            Log::info('OTT fetchAccount - User info', [
                'user_id' => $userId,
                'is_ott' => $userArray['is_ott'] ?? null,
                'user_email' => $userArray['email'] ?? null
            ]);
            
            // Check if user has is_ott field and it's true
            if (!isset($userArray['is_ott']) || !$userArray['is_ott']) {
                Log::info('OTT fetchAccount - User is not OTT user', [
                    'user_id' => $userId
                ]);
                return response([
                    'data' => []
                ]);
            }

            // Load User model to access relationships
            $user = User::find($userId);
            if (!$user) {
                return response([
                    'data' => []
                ]);
            }

            // Debug: Check direct query to v2_ott_user table
            $ottUserCount = DB::table('v2_ott_user')->where('user_id', $userId)->count();
            Log::info('OTT fetchAccount - Direct query count', [
                'user_id' => $userId,
                'ott_user_count' => $ottUserCount
            ]);

            // Load accounts with pivot data
            $accounts = $user->ottAccounts()->get();
            
            Log::info('OTT fetchAccount - Accounts loaded', [
                'user_id' => $userId,
                'accounts_count' => $accounts->count(),
                'account_ids' => $accounts->pluck('id')->toArray()
            ]);
            
            $data = [];

            foreach ($accounts as $account) {
                // Check if pivot data exists
                if (!$account->pivot) {
                    Log::warning('OTT fetchAccount - Account without pivot', [
                        'user_id' => $userId,
                        'account_id' => $account->id
                    ]);
                    continue;
                }

                $expiredAt = $account->pivot->expired_at ?? 0;
                $isExpired = $expiredAt < time();
                
                // 过期：完全不返回该账号信息
                if ($isExpired) {
                    continue;
                }
                
                // 只返回用户需要知道的字段，过滤敏感信息
                $item = [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type,
                    'has_otp' => $account->has_otp ?? false,
                    'price_monthly' => $account->price_monthly,
                    'price_yearly' => $account->price_yearly,
                    'shared_seats' => $account->shared_seats,
                    'is_active' => $account->is_active ?? true,
                    'created_at' => $account->created_at,
                    'updated_at' => $account->updated_at,
                ];

                // 添加用户特定的信息（来自 pivot）
                $item['expired_at'] = $expiredAt;
                $item['sub_account_id'] = $account->pivot->sub_account_id ?? null;
                $item['sub_account_pin'] = $account->pivot->sub_account_pin ?? null;

                // 计算每用户年费
                $yearlyPrice = $account->price_yearly ? $account->price_yearly : ($account->price_monthly ? ($account->price_monthly * 12) : 0);
                $seats = $account->shared_seats > 0 ? $account->shared_seats : 1;
                $item['cost_per_year'] = $seats > 0 ? round($yearlyPrice / $seats, 2) : 0;

                // 权限控制：根据 is_shared_credentials 决定是否返回账号密码
                $isSharedCredentials = $account->is_shared_credentials ?? false;
                $item['is_shared_credentials'] = $isSharedCredentials;
                
                // 未过期：始终返回 username，根据 is_shared_credentials 决定是否返回 password
                $item['username'] = $account->username;
                
                if ($isSharedCredentials) {
                    // 共享凭证：返回主账号的 password
                    $item['password'] = $account->password;
                } else {
                    // 非共享凭证：不返回 password，前端会提示用户账号不提供密码，仅支持OTP登录
                    $item['password'] = null;
                }
                $item['status'] = 'active';
                
                $data[] = $item;
            }

            Log::info('OTT fetchAccount - Final data count', [
                'user_id' => $userId,
                'data_count' => count($data)
            ]);

            return response([
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('OTT fetchAccount error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user['id'] ?? null
            ]);
            
            return response([
                'message' => '获取账号列表失败：' . $e->getMessage()
            ], 500);
        }
    }

    public function fetchRenewal(Request $request)
    {
        $userId = $request->user['id'] ?? null;
        
        // 只返回当前用户的 renewal 信息，不包含其他用户的敏感数据
        $renewals = OttRenewal::where('user_id', $userId)
            ->join('v2_ott_account', 'v2_ott_renewal.account_id', '=', 'v2_ott_account.id')
            ->select(
                'v2_ott_renewal.id',
                'v2_ott_renewal.account_id',
                'v2_ott_renewal.user_id',
                'v2_ott_renewal.target_year',
                'v2_ott_renewal.price',
                'v2_ott_renewal.is_paid',
                'v2_ott_renewal.sub_account_id',
                'v2_ott_renewal.sub_account_pin',
                'v2_ott_renewal.created_at',
                'v2_ott_renewal.updated_at',
                'v2_ott_account.name as account_name',
                'v2_ott_account.type as account_type'
            )
            ->get();

        return response([
            'data' => $renewals
        ]);
    }

    public function fetchMessage(Request $request)
    {
        try {
            $userArray = $request->user;
            $userId = $userArray['id'] ?? null;
            $accountId = $request->input('account_id');

            if (!isset($userArray['is_ott']) || !$userArray['is_ott']) {
                abort(403, '无权访问');
            }

            // Load User model to access relationships
            $user = User::find($userId);
            if (!$user) {
                abort(403, '用户不存在');
            }

            // Check if user has access to this account and not expired
            $account = $user->ottAccounts()
                ->where('account_id', $accountId)
                ->first();

            if (!$account || !$account->pivot) {
                abort(403, '无权访问此账号');
            }

            $expiredAt = $account->pivot->expired_at ?? 0;
            if ($expiredAt < time()) {
                abort(403, '账号授权已过期');
            }

            if (!isset($account->has_otp) || !$account->has_otp) {
                return response([
                    'data' => []
                ]);
            }

            $validityMinutes = $account->otp_validity_minutes ?? 10;
            $validAfter = time() - ($validityMinutes * 60);

            $messages = OttMessage::where('account_id', $accountId)
                ->where('received_at', '>=', $validAfter)
                ->orderBy('received_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($message) use ($validityMinutes) {
                    $message->expired_at = $message->received_at + ($validityMinutes * 60);
                    return $message;
                });

            return response([
                'data' => $messages
            ]);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('OTT fetchMessage error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user['id'] ?? null,
                'account_id' => $request->input('account_id')
            ]);
            
            return response([
                'message' => '获取验证码失败：' . $e->getMessage()
            ], 500);
        }
    }
}
