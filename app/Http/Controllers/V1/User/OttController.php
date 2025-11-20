<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Models\OttAccount;
use App\Models\OttMessage;
use App\Models\OttRenewal;
use Illuminate\Http\Request;

class OttController extends Controller
{
    public function fetchAccount(Request $request)
    {
        $user = $request->user;
        if (!$user->is_ott) {
            return response([
                'data' => []
            ]);
        }

        $accounts = $user->ottAccounts;
        $data = [];

        foreach ($accounts as $account) {
            $isExpired = $account->pivot->expired_at < time();
            $item = $account->toArray();

            // Add pivot data
            $item['expired_at'] = $account->pivot->expired_at;
            $item['sub_account_id'] = $account->pivot->sub_account_id;
            $item['sub_account_pin'] = $account->pivot->sub_account_pin;

            // Calculate Cost Per Year Per User
            $yearlyPrice = $account->price_yearly ? $account->price_yearly : ($account->price_monthly * 12);
            $seats = $account->shared_seats > 0 ? $account->shared_seats : 1;
            $item['cost_per_year'] = round($yearlyPrice / $seats, 2);

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

        return response([
            'data' => $data
        ]);
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
        $user = $request->user;
        $accountId = $request->input('account_id');

        if (!$user->is_ott) {
            abort(403, '无权访问');
        }

        // Check if user has access to this account and not expired
        $account = $user->ottAccounts()
            ->where('account_id', $accountId)
            ->first();

        if (!$account) {
            abort(403, '无权访问此账号');
        }

        if ($account->pivot->expired_at < time()) {
            abort(403, '账号授权已过期');
        }

        if (!$account->has_otp) {
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
    }
}
