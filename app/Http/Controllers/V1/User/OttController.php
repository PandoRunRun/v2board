<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Models\OttAccount;
use App\Models\OttMessage;
use App\Models\OttRenewal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class OttController extends Controller
{
    public function fetchAccount(Request $request)
    {
        try {
            $user = $request->user;
            
            // Debug: Log user info
            Log::info('OTT fetchAccount - User info', [
                'user_id' => $user->id ?? null,
                'is_ott' => $user->is_ott ?? null,
                'user_email' => $user->email ?? null
            ]);
            
            // Check if user has is_ott field and it's true
            if (!isset($user->is_ott) || !$user->is_ott) {
                Log::info('OTT fetchAccount - User is not OTT user', [
                    'user_id' => $user->id ?? null
                ]);
                return response([
                    'data' => []
                ]);
            }

            // Debug: Check direct query to v2_ott_user table
            $ottUserCount = \DB::table('v2_ott_user')->where('user_id', $user->id)->count();
            Log::info('OTT fetchAccount - Direct query count', [
                'user_id' => $user->id,
                'ott_user_count' => $ottUserCount
            ]);

            // Load accounts with pivot data
            $accounts = $user->ottAccounts()->get();
            
            Log::info('OTT fetchAccount - Accounts loaded', [
                'user_id' => $user->id,
                'accounts_count' => $accounts->count(),
                'account_ids' => $accounts->pluck('id')->toArray()
            ]);
            
            $data = [];

            foreach ($accounts as $account) {
                // Check if pivot data exists
                if (!$account->pivot) {
                    Log::warning('OTT fetchAccount - Account without pivot', [
                        'user_id' => $user->id,
                        'account_id' => $account->id
                    ]);
                    continue;
                }

                $expiredAt = $account->pivot->expired_at ?? 0;
                $isExpired = $expiredAt < time();
                $item = $account->toArray();

                // Add pivot data
                $item['expired_at'] = $expiredAt;
                $item['sub_account_id'] = $account->pivot->sub_account_id ?? null;
                $item['sub_account_pin'] = $account->pivot->sub_account_pin ?? null;

                // Calculate Cost Per Year Per User
                $yearlyPrice = $account->price_yearly ? $account->price_yearly : ($account->price_monthly ? ($account->price_monthly * 12) : 0);
                $seats = $account->shared_seats > 0 ? $account->shared_seats : 1;
                $item['cost_per_year'] = $seats > 0 ? round($yearlyPrice / $seats, 2) : 0;

                // Mask sensitive data if expired
                if ($isExpired) {
                    $item['password'] = '******';
                    $item['username'] = '******'; // Mask full username if expired
                    $item['sub_account_pin'] = '******';
                    $item['status'] = 'expired';
                } else {
                    $item['status'] = 'active';
                }
                $data[] = $item;
            }

            Log::info('OTT fetchAccount - Final data count', [
                'user_id' => $user->id,
                'data_count' => count($data)
            ]);

            return response([
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('OTT fetchAccount error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user->id ?? null
            ]);
            
            return response([
                'message' => '获取账号列表失败：' . $e->getMessage()
            ], 500);
        }
    }

    public function fetchRenewal(Request $request)
    {
        $user = $request->user;
        
        $renewals = OttRenewal::where('user_id', $user->id)
            ->join('v2_ott_account', 'v2_ott_renewal.account_id', '=', 'v2_ott_account.id')
            ->select(
                'v2_ott_renewal.*',
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
            $user = $request->user;
            $accountId = $request->input('account_id');

            if (!isset($user->is_ott) || !$user->is_ott) {
                abort(403, '无权访问');
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
                'user_id' => $request->user->id ?? null,
                'account_id' => $request->input('account_id')
            ]);
            
            return response([
                'message' => '获取验证码失败：' . $e->getMessage()
            ], 500);
        }
    }
}
