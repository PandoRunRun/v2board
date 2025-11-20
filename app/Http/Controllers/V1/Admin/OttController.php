<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\OttAccount;
use App\Models\OttUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OttController extends Controller
{
    public function saveAccount(Request $request)
    {
        $params = $request->validate([
            'id' => 'nullable|integer',
            'name' => 'required',
            'type' => 'required',
            'username' => 'required',
            'password' => 'required',
            'has_otp' => 'boolean',
            'is_shared_credentials' => 'boolean',
            'sender_filter' => 'nullable',
            'recipient_filter' => 'nullable',
            'subject_regex' => 'nullable|string',
            'otp_validity_minutes' => 'nullable|integer',
            'ignore_regex' => 'nullable|string',
            'price_monthly' => 'nullable|numeric',
            'price_yearly' => 'nullable|numeric',
            'shared_seats' => 'nullable|integer|min:1',
            'next_price_yearly' => 'nullable|numeric',
            'next_shared_seats' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'group_id' => 'nullable|integer',
        ]);

        if (isset($params['id'])) {
            $account = OttAccount::find($params['id']);
            if (!$account) {
                abort(500, '账号不存在');
            }
            $account->update($params);
        } else {
            OttAccount::create($params);
        }

        return response([
            'data' => true
        ]);
    }

    public function dropAccount(Request $request)
    {
        $account = OttAccount::find($request->input('id'));
        if (!$account) {
            abort(500, '账号不存在');
        }
        $account->delete();
        return response([
            'data' => true
        ]);
    }

    public function fetchAccount(Request $request)
    {
        return response([
            'data' => OttAccount::all()
        ]);
    }

    public function fetchUsers(Request $request)
    {
        $request->validate([
            'account_id' => 'required|integer'
        ]);

        $ottUsers = OttUser::where('account_id', $request->input('account_id'))
            ->join('v2_user', 'v2_ott_user.user_id', '=', 'v2_user.id')
            ->select(
                'v2_ott_user.*',
                'v2_user.email as user_email'
            )
            ->get();

        return response([
            'data' => $ottUsers
        ]);
    }

    public function bind(Request $request)
    {
        $request->validate([
            'user_id' => 'nullable|integer',
            'email' => 'nullable|email',
            'account_id' => 'required|integer',
            'expired_at' => 'required|integer',
            'sub_account_id' => 'nullable|string',
            'sub_account_pin' => 'nullable|string'
        ]);

        $userId = $request->input('user_id');
        if (!$userId && $request->input('email')) {
            $user = User::where('email', $request->input('email'))->first();
            if (!$user) {
                abort(500, 'User not found');
            }
            $userId = $user->id;
        }

        if (!$userId) {
            abort(500, 'User ID or Email is required');
        }

        $account = OttAccount::find($request->input('account_id'));
        if (!$account) {
            abort(500, 'Account not found');
        }

        // Check if binding exists
        $ottUser = OttUser::where('user_id', $userId)
            ->where('account_id', $account->id)
            ->first();

        if ($ottUser) {
            $ottUser->expired_at = $request->input('expired_at');
            $ottUser->sub_account_id = $request->input('sub_account_id');
            $ottUser->sub_account_pin = $request->input('sub_account_pin');
            $ottUser->save();
        } else {
            $ottUser = new OttUser();
            $ottUser->user_id = $userId;
            $ottUser->account_id = $account->id;
            $ottUser->expired_at = $request->input('expired_at');
            $ottUser->sub_account_id = $request->input('sub_account_id');
            $ottUser->sub_account_pin = $request->input('sub_account_pin');
            $ottUser->save();
        }

        return response([
            'data' => true
        ]);
    }

    public function unbindUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'account_id' => 'required|integer'
        ]);

        OttUser::where('user_id', $request->input('user_id'))
            ->where('account_id', $request->input('account_id'))
            ->delete();

        return response([
            'data' => true
        ]);
    }
}
