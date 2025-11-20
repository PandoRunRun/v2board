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
        // 最简单的测试：无论token是否正确，先记录请求
        file_put_contents(storage_path('logs/webhook_debug.log'), 
            date('Y-m-d H:i:s') . " - Webhook received\n" .
            "Method: " . $request->method() . "\n" .
            "Headers: " . json_encode($request->headers->all(), JSON_UNESCAPED_UNICODE) . "\n" .
            "Body: " . $request->getContent() . "\n" .
            "Input: " . json_encode($request->all(), JSON_UNESCAPED_UNICODE) . "\n" .
            "Token: " . ($request->input('token') ?? 'null') . "\n" .
            "Expected: " . (config('v2board.server_token') ?? 'null') . "\n\n",
            FILE_APPEND
        );

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
            if (@preg_match($cleanIgnoreRegex, $subject) || @preg_match($cleanIgnoreRegex, $content)) {
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
        $regexExtractionResult = [
            'has_regex' => false,
            'original_regex' => null,
            'cleaned_regex' => null,
            'extraction_method' => 'original_content',
            'extraction_success' => false,
            'extracted_content' => null,
            'failure_reason' => null
        ];

        if ($matchedAccount->subject_regex) {
            $regexExtractionResult['has_regex'] = true;
            $regexExtractionResult['original_regex'] = $matchedAccount->subject_regex;
            $cleanRegex = $this->cleanRegex($matchedAccount->subject_regex);
            $regexExtractionResult['cleaned_regex'] = $cleanRegex;

            // Test regex on content first
            $matchFound = false;
            $matches = [];
            $extractionSource = null;

            // Check if regex is valid
            $regexError = null;
            set_error_handler(function($errno, $errstr) use (&$regexError) {
                $regexError = $errstr;
            }, E_WARNING | E_NOTICE);

            try {
                if (@preg_match($cleanRegex, $content, $matches)) {
                    restore_error_handler();
                    // Find first non-empty capture group
                    for ($i = 1; $i < count($matches); $i++) {
                        if (!empty($matches[$i])) {
                            $finalContent = $matches[$i];
                            $matchFound = true;
                            $extractionSource = 'content';
                            break;
                        }
                    }
                    if (!$matchFound && !empty($matches[0])) {
                        $finalContent = $matches[0];
                        $matchFound = true;
                        $extractionSource = 'content';
                    }
                } else {
                    $contentError = $regexError;
                    $regexError = null;
                    restore_error_handler();
                    
                    if ($contentError) {
                        $regexExtractionResult['failure_reason'] = '正则表达式格式错误: ' . $contentError;
                    } else {
                        // Try matching subject
                        set_error_handler(function($errno, $errstr) use (&$regexError) {
                            $regexError = $errstr;
                        }, E_WARNING | E_NOTICE);
                        
                        if (@preg_match($cleanRegex, $subject, $matches)) {
                            restore_error_handler();
                            // Find first non-empty capture group
                            for ($i = 1; $i < count($matches); $i++) {
                                if (!empty($matches[$i])) {
                                    $finalContent = $matches[$i];
                                    $matchFound = true;
                                    $extractionSource = 'subject';
                                    break;
                                }
                            }
                            if (!$matchFound && !empty($matches[0])) {
                                $finalContent = $matches[0];
                                $matchFound = true;
                                $extractionSource = 'subject';
                            }
                        } else {
                            restore_error_handler();
                            if ($regexError) {
                                $regexExtractionResult['failure_reason'] = '正则表达式格式错误: ' . $regexError;
                            } else {
                                $regexExtractionResult['failure_reason'] = '正则表达式在内容和主题中均未匹配';
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                restore_error_handler();
                $regexExtractionResult['failure_reason'] = '正则表达式处理异常: ' . $e->getMessage();
            }

            if ($matchFound) {
                $regexExtractionResult['extraction_method'] = 'regex_' . $extractionSource;
                $regexExtractionResult['extraction_success'] = true;
                $regexExtractionResult['extracted_content'] = $finalContent;
            } else {
                $regexExtractionResult['extraction_method'] = 'regex_failed';
                // Keep original content as fallback
            }
        }

        try {
            $message = OttMessage::create([
                'account_id' => $matchedAccount->id,
                'subject' => $subject,
                'content' => $finalContent,
                'received_at' => time()
            ]);

            \App\Models\OttLog::create([
                'account_id' => $matchedAccount->id,
                'type' => $regexExtractionResult['extraction_success'] || !$regexExtractionResult['has_regex'] ? 'capture_success' : 'capture_fail',
                'status' => $regexExtractionResult['extraction_success'] || !$regexExtractionResult['has_regex'],
                'message' => $regexExtractionResult['has_regex'] 
                    ? ($regexExtractionResult['extraction_success'] 
                        ? 'OTP提取成功 (通过正则表达式: ' . $regexExtractionResult['extraction_method'] . ')'
                        : 'OTP提取失败: ' . ($regexExtractionResult['failure_reason'] ?? '未知原因'))
                    : 'Message captured (未使用正则表达式)',
                'data' => array_merge([
                    'subject' => $subject,
                    'content_preview' => is_string($finalContent) ? substr($finalContent, 0, 50) : (string)$finalContent,
                    'message_id' => $message->id ?? null
                ], $regexExtractionResult)
            ]);

            // Cleanup old logs (simple probability based cleanup to avoid heavy load)
            if (rand(1, 100) <= 5) {
                \App\Models\OttLog::where('created_at', '<', now()->subDays(30))->delete();
            }

            return response([
                'data' => true
            ]);
        } catch (\Exception $e) {
            // 确保即使日志创建失败，webhook 也返回成功，避免 CF Worker 重试
            try {
                \App\Models\OttLog::create([
                    'account_id' => $matchedAccount->id ?? null,
                    'type' => 'capture_fail',
                    'status' => false,
                    'message' => '日志创建失败: ' . $e->getMessage(),
                    'data' => [
                        'subject' => $subject,
                        'error' => $e->getMessage(),
                        'trace' => substr($e->getTraceAsString(), 0, 500)
                    ]
                ]);
            } catch (\Exception $logException) {
                // 如果日志创建也失败，忽略
            }
            
            return response([
                'data' => true,
                'message' => 'Message processed but log creation failed'
            ]);
        }
    }

    /**
     * 清理正则表达式格式
     * 将 JavaScript 风格的正则表达式（如 /pattern/is）转换为 PHP 格式
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
            
            $phpFlags = '';
            
            // 处理 i 标志（大小写不敏感）
            if (strpos($flags, 'i') !== false) {
                $phpFlags .= 'i';
            }
            
            // 处理 s 标志（让 . 匹配换行）
            if (strpos($flags, 's') !== false) {
                $pattern = '(?s)' . $pattern;
            }
            
            // 处理 m 标志（多行模式）
            if (strpos($flags, 'm') !== false) {
                $phpFlags .= 'm';
            }
            
            // 修复转义：将 \/ 转换为 /
            $pattern = str_replace('\/', '/', $pattern);
            
            // 如果有 PHP 标志，在模式后添加分隔符
            if (!empty($phpFlags)) {
                return '/' . $pattern . '/' . $phpFlags;
            }
            
            return $pattern;
        }

        // 如果没有分隔符，仍然修复可能的转义问题
        return str_replace('\/', '/', $regex);
    }
}
