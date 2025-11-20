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
                'sub_account_id' => $request->input('sub_account_id'),
                'sub_account_pin' => $request->input('sub_account_pin')
            ]
        );

        return response([
            'data' => true
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
                    'sub_account_id' => $user->sub_account_id,
                    'sub_account_pin' => $user->sub_account_pin
                ]
            );
        }

        return response([
            'data' => true
        ]);
    }
}
