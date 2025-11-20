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
            $cleanIgnoreRegex = $this->cleanRegex($matchedAccount->ignore_regex);
            if (preg_match($cleanIgnoreRegex, $subject) || preg_match($cleanIgnoreRegex, $content)) {
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
            $cleanRegex = $this->cleanRegex($matchedAccount->subject_regex);
            if (preg_match($cleanRegex, $content, $matches)) {
                // Find first non-empty capture group
                $matchFound = false;
                for ($i = 1; $i < count($matches); $i++) {
                    if (!empty($matches[$i])) {
                        $finalContent = $matches[$i];
                        $matchFound = true;
                        break;
                    }
                }
                if (!$matchFound) {
                    $finalContent = $matches[0];
                }
            } else if (preg_match($cleanRegex, $subject, $matches)) {
                 // Find first non-empty capture group
                $matchFound = false;
                for ($i = 1; $i < count($matches); $i++) {
                    if (!empty($matches[$i])) {
                        $finalContent = $matches[$i];
                        $matchFound = true;
                        break;
                    }
                }
                if (!$matchFound) {
                    $finalContent = $matches[0];
                }
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

    /**
     * 清理正则表达式格式
     * 将 JavaScript 风格的正则表达式（如 /pattern/s）转换为 PHP 格式
     */
    private function cleanRegex($regex)
    {
        if (empty($regex)) {
            return $regex;
        }

        $regex = trim($regex);
        
        // 如果正则表达式包含分隔符 /.../，移除它们
        if (preg_match('#^/(.*)/([imsxADSUXuJ]*)$#', $regex, $m)) {
            $pattern = $m[1];
            $flags = $m[2] ?? '';
            
            // 如果原来有 s 标志，在模式前添加 (?s) 让 . 匹配换行
            if (strpos($flags, 's') !== false) {
                $pattern = '(?s)' . $pattern;
            }
            
            // 修复转义：将 \/ 转换为 /
            $pattern = str_replace('\/', '/', $pattern);
            
            return $pattern;
        }

        // 如果没有分隔符，仍然修复可能的转义问题
        return str_replace('\/', '/', $regex);
    }
}
