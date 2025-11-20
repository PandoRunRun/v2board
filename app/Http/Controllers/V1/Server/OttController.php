<?php

namespace App\Http\Controllers\V1\Server;

use App\Http\Controllers\Controller;
use App\Models\OttAccount;
use App\Models\OttMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        Log::info('OTT Webhook received', [
            'sender' => $sender,
            'recipient' => $recipient,
            'subject' => $subject,
            'content_length' => strlen($content),
            'content_preview' => substr($content, 0, 200)
        ]);

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
                Log::info('OTT Account matched', [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'recipient_match' => $recipientMatch,
                    'sender_match' => $senderMatch
                ]);
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
            Log::warning('OTT Webhook - No matching account', [
                'recipient' => $recipient,
                'sender' => $sender,
                'available_accounts' => $accounts->pluck('username', 'id')->toArray()
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
                    'data' => ['subject' => $subject, 'regex' => $cleanIgnoreRegex]
                ]);
                Log::info('OTT Webhook - Message ignored by regex', [
                    'account_id' => $matchedAccount->id,
                    'subject' => $subject
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
            
            Log::info('OTT Webhook - Attempting regex extraction', [
                'account_id' => $matchedAccount->id,
                'original_regex' => $matchedAccount->subject_regex,
                'cleaned_regex' => $cleanRegex,
                'subject_preview' => substr($subject, 0, 100),
                'content_preview' => substr($content, 0, 100)
            ]);

            $matchFound = false;
            $matches = [];

            // Try matching content first
            if (preg_match($cleanRegex, $content, $matches)) {
                Log::info('OTT Webhook - Regex matched in content', [
                    'account_id' => $matchedAccount->id,
                    'matches' => $matches,
                    'match_count' => count($matches)
                ]);
                // Find first non-empty capture group
                for ($i = 1; $i < count($matches); $i++) {
                    if (!empty($matches[$i])) {
                        $finalContent = $matches[$i];
                        $matchFound = true;
                        break;
                    }
                }
                if (!$matchFound && !empty($matches[0])) {
                    $finalContent = $matches[0];
                    $matchFound = true;
                }
            } else if (preg_match($cleanRegex, $subject, $matches)) {
                Log::info('OTT Webhook - Regex matched in subject', [
                    'account_id' => $matchedAccount->id,
                    'matches' => $matches,
                    'match_count' => count($matches)
                ]);
                // Find first non-empty capture group
                for ($i = 1; $i < count($matches); $i++) {
                    if (!empty($matches[$i])) {
                        $finalContent = $matches[$i];
                        $matchFound = true;
                        break;
                    }
                }
                if (!$matchFound && !empty($matches[0])) {
                    $finalContent = $matches[0];
                    $matchFound = true;
                }
            } else {
                Log::warning('OTT Webhook - Regex did not match', [
                    'account_id' => $matchedAccount->id,
                    'regex' => $cleanRegex,
                    'subject' => $subject,
                    'content_length' => strlen($content)
                ]);
            }

            if ($matchFound) {
                Log::info('OTT Webhook - Extracted content', [
                    'account_id' => $matchedAccount->id,
                    'extracted_content' => $finalContent,
                    'original_length' => strlen($content),
                    'extracted_length' => strlen($finalContent)
                ]);
            }
        }

        $message = OttMessage::create([
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
            'data' => [
                'subject' => $subject, 
                'content_preview' => substr($finalContent, 0, 50),
                'message_id' => $message->id
            ]
        ]);

        Log::info('OTT Webhook - Message saved', [
            'account_id' => $matchedAccount->id,
            'message_id' => $message->id,
            'content' => $finalContent
        ]);

        // Cleanup old logs (simple probability based cleanup to avoid heavy load)
        if (rand(1, 100) <= 5) {
            \App\Models\OttLog::where('created_at', '<', now()->subDays(30))->delete();
        }

        return response([
            'data' => true,
            'message_id' => $message->id
        ]);
    }

    /**
     * 清理正则表达式格式
     * 移除 JavaScript 风格的分隔符和标志（如 /pattern/s -> pattern）
     * 处理转义字符
     */
    private function cleanRegex($regex)
    {
        if (empty($regex)) {
            return $regex;
        }

        // 移除前后可能的分隔符 /.../
        $regex = trim($regex);
        if (preg_match('#^/(.*)/([imsxADSUXuJ]*)$#', $regex, $m)) {
            $pattern = $m[1];
            $flags = $m[2] ?? '';
            
            // 保留有用的标志
            $phpFlags = '';
            if (strpos($flags, 'i') !== false) {
                $phpFlags .= 'i';
            }
            if (strpos($flags, 's') !== false) {
                // s 标志在 PHP 中不需要单独设置，但我们需要确保 . 匹配换行
                // 在模式中添加 (?s) 或使用 [\s\S]
            }
            if (strpos($flags, 'm') !== false) {
                $phpFlags .= 'm';
            }
            
            // 如果原来有 s 标志，在模式前添加 (?s) 让 . 匹配换行
            if (strpos($flags, 's') !== false) {
                $pattern = '(?s)' . $pattern;
            }
            
            // 修复转义：将 \/ 转换为 /
            $pattern = str_replace('\/', '/', $pattern);
            
            return $pattern;
        }

        // 如果没有分隔符，直接返回（可能已经是 PHP 格式）
        // 仍然修复可能的转义问题
        return str_replace('\/', '/', $regex);
    }
}
