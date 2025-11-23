<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\OttAccount;
use App\Models\OttRenewal;
use App\Models\OttUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OttRenewalController extends Controller
{
    public function fetch(Request $request)
    {
        $request->validate([
            'account_id' => 'nullable|integer',
            'target_year' => 'required|integer'
        ]);

        $query = OttRenewal::where('target_year', $request->input('target_year'))
            ->join('v2_user', 'v2_ott_renewal.user_id', '=', 'v2_user.id')
            ->join('v2_ott_account', 'v2_ott_renewal.account_id', '=', 'v2_ott_account.id')
            ->select(
                'v2_ott_renewal.*', 
                'v2_user.email as user_email',
                'v2_ott_account.name as account_name',
                'v2_ott_account.type as account_type'
            );

        if ($request->input('account_id')) {
            $query->where('account_id', $request->input('account_id'));
        }

        $renewals = $query->get();

        return response([
            'data' => $renewals
        ]);
    }

    public function save(Request $request)
    {
        $request->validate([
            'account_id' => 'required|integer',
            'target_year' => 'required|integer',
            'user_email' => 'required|email',
            'price' => 'required|numeric',
            'is_paid' => 'boolean',
            'is_dropped' => 'boolean',
            'sub_account_id' => 'nullable|string',
            'sub_account_pin' => 'nullable|string'
        ]);

        $user = User::where('email', $request->input('user_email'))->first();
        if (!$user) {
            abort(500, 'User not found');
        }

        $renewal = OttRenewal::updateOrCreate(
            [
                'account_id' => $request->input('account_id'),
                'user_id' => $user->id,
                'target_year' => $request->input('target_year')
            ],
            [
                'price' => $request->input('price'),
                'is_paid' => $request->input('is_paid', false),
                'is_dropped' => $request->input('is_dropped', false),
                'sub_account_id' => $request->input('sub_account_id'),
                'sub_account_pin' => $request->input('sub_account_pin')
            ]
        );

        return response([
            'data' => true
        ]);
    }

    /**
     * 标记用户下车（不再使用此账号）
     */
    public function markDropped(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'is_dropped' => 'required|boolean'
        ]);

        $renewal = OttRenewal::find($request->input('id'));
        if (!$renewal) {
            abort(404, 'Renewal not found');
        }

        $renewal->is_dropped = $request->input('is_dropped');
        // 如果标记为下车，自动设置为未付款
        if ($request->input('is_dropped')) {
            $renewal->is_paid = false;
        }
        $renewal->save();

        return response([
            'data' => $renewal
        ]);
    }

    public function drop(Request $request)
    {
        $request->validate([
            'id' => 'required|integer'
        ]);

        OttRenewal::destroy($request->input('id'));

        return response([
            'data' => true
        ]);
    }

    public function importCurrentUsers(Request $request)
    {
        $request->validate([
            'account_id' => 'required|integer',
            'target_year' => 'required|integer'
        ]);

        $account = OttAccount::find($request->input('account_id'));
        if (!$account) abort(500, 'Account not found');

        // Calculate next price per user
        $nextYearlyPrice = $account->next_price_yearly ?? ($account->price_yearly ?? 0);
        $nextSeats = $account->next_shared_seats ?? ($account->shared_seats ?? 1);
        $perUserPrice = round($nextYearlyPrice / $nextSeats, 2);

        $currentUsers = OttUser::where('account_id', $account->id)->get();

        foreach ($currentUsers as $user) {
            OttRenewal::firstOrCreate(
                [
                    'account_id' => $account->id,
                    'user_id' => $user->user_id,
                    'target_year' => $request->input('target_year')
                ],
                [
                    'price' => $perUserPrice,
                    'is_paid' => false,
                    'is_new' => false, // 导入的当前用户不是新用户
                    'sub_account_id' => $user->sub_account_id,
                    'sub_account_pin' => $user->sub_account_pin
                ]
            );
        }

        return response([
            'data' => true
        ]);
    }

    /**
     * 添加新用户到续费列表（预添加，用户可能还未绑定账户）
     */
    public function addNewUser(Request $request)
    {
        $request->validate([
            'account_id' => 'required|integer',
            'target_year' => 'required|integer',
            'user_email' => 'required|email',
            'sub_account_id' => 'nullable|string',
            'sub_account_pin' => 'nullable|string'
        ]);

        $account = OttAccount::find($request->input('account_id'));
        if (!$account) abort(500, 'Account not found');

        // 查找或创建用户
        $user = User::where('email', $request->input('user_email'))->first();
        if (!$user) {
            // 如果用户不存在，创建一个新用户（但不绑定到账户，等年初自动覆盖时再绑定）
            $user = User::create([
                'email' => $request->input('user_email'),
                'password' => bcrypt(str()->random(32)), // 随机密码，用户需要重置
                'is_ott' => true
            ]);
        }

        // Calculate next price per user
        $nextYearlyPrice = $account->next_price_yearly ?? ($account->price_yearly ?? 0);
        $nextSeats = $account->next_shared_seats ?? ($account->shared_seats ?? 1);
        $perUserPrice = round($nextYearlyPrice / $nextSeats, 2);

        // 创建续费记录，标记为新用户
        $renewal = OttRenewal::firstOrCreate(
            [
                'account_id' => $account->id,
                'user_id' => $user->id,
                'target_year' => $request->input('target_year')
            ],
            [
                'price' => $perUserPrice,
                'is_paid' => false,
                'is_new' => true, // 新添加的用户标记为新用户
                'sub_account_id' => $request->input('sub_account_id'),
                'sub_account_pin' => $request->input('sub_account_pin')
            ]
        );

        return response([
            'data' => $renewal
        ]);
    }

}
