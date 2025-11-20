<?php

namespace App\Http\Controllers\V1\Server;

use App\Http\Controllers\Controller;
use App\Models\OttAccount;
use App\Models\OttMessage;
use Illuminate\Http\Request;

class OttController extends Controller
{
    public function webhook(Request $request)
    {
        $token = $request->input('token');
        if ($token !== config('v2board.server_token')) {
            abort(403, 'Invalid Token');
        }

        $sender = $request->input('sender');
        $recipient = $request->input('recipient');
        $subject = $request->input('subject');
        $content = $request->input('content'); // Body or Raw Content

        // Find matching account
        // 1. Match recipient (username or recipient_filter)
        // 2. Match sender (sender_filter)
        
        $accounts = OttAccount::where('is_active', 1)->get();
        $matchedAccount = null;

        foreach ($accounts as $account) {
            // Check Recipient
            $recipientMatch = false;
            if ($account->recipient_filter) {
                if (strpos($recipient, $account->recipient_filter) !== false) {
                    $recipientMatch = true;
                }
            } else {
                if ($recipient === $account->username) {
                    $recipientMatch = true;
                }
            }

            // Check Sender
            $senderMatch = false;
            if ($account->sender_filter) {
                if (strpos($sender, $account->sender_filter) !== false) {
                    $senderMatch = true;
                }
            } else {
                $senderMatch = true; // If no filter, assume match or rely on recipient
            }

            if ($recipientMatch && $senderMatch) {
                $matchedAccount = $account;
                break;
            }
        }

        if (!$matchedAccount) {
            \App\Models\OttLog::create([
                'type' => 'capture_fail',
                'status' => false,
                'message' => 'No matching account found',
                'data' => ['recipient' => $recipient, 'sender' => $sender, 'subject' => $subject]
            ]);
            return response([
                'data' => false,
                'message' => 'No matching account found'
            ]);
        }

        // Check Ignore Regex
        if ($matchedAccount->ignore_regex) {
            if (preg_match($matchedAccount->ignore_regex, $subject) || preg_match($matchedAccount->ignore_regex, $content)) {
                \App\Models\OttLog::create([
                    'account_id' => $matchedAccount->id,
                    'type' => 'capture_ignore',
                    'status' => true,
                    'message' => 'Ignored by regex',
                    'data' => ['subject' => $subject]
                ]);
                return response([
                    'data' => true,
                    'message' => 'Ignored by regex'
                ]);
            }
        }

        // Extract OTP if regex is present
        $finalContent = $content;
        if ($matchedAccount->subject_regex) {
            if (preg_match($matchedAccount->subject_regex, $content, $matches)) {
                $finalContent = isset($matches[1]) ? $matches[1] : $matches[0];
            } else if (preg_match($matchedAccount->subject_regex, $subject, $matches)) {
                 $finalContent = isset($matches[1]) ? $matches[1] : $matches[0];
            }
        }

        OttMessage::create([
            'account_id' => $matchedAccount->id,
            'subject' => $subject,
            'content' => $finalContent,
            'received_at' => time()
        ]);

        \App\Models\OttLog::create([
            'account_id' => $matchedAccount->id,
            'type' => 'capture_success',
            'status' => true,
            'message' => 'Message captured',
            'data' => ['subject' => $subject, 'content_preview' => substr($finalContent, 0, 50)]
        ]);

        // Cleanup old logs (simple probability based cleanup to avoid heavy load)
        if (rand(1, 100) <= 5) {
            \App\Models\OttLog::where('created_at', '<', now()->subDays(30))->delete();
        }

        return response([
            'data' => true
        ]);
    }
}
