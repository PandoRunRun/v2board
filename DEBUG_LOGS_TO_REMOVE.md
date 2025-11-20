# 需要清理的调试日志

## 文件：app/Http/Controllers/V1/Server/OttController.php

### 1. Webhook 调试文件日志（第14-27行）
**位置：** `webhook()` 方法开始处
**代码：**
```php
// 最简单的测试：无论token是否正确，先记录请求
// 使用绝对路径确保日志能写入
$logFile = storage_path('logs/webhook_debug.log');
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
@file_put_contents($logFile, 
    date('Y-m-d H:i:s') . " - Webhook received\n" .
    "Method: " . $request->method() . "\n" .
    "Headers: " . json_encode($request->headers->all(), JSON_UNESCAPED_UNICODE) . "\n" .
    "Body: " . $request->getContent() . "\n" .
    "Input: " . json_encode($request->all(), JSON_UNESCAPED_UNICODE) . "\n" .
    "Token: " . ($request->input('token') ?? 'null') . "\n" .
    "Expected: " . (config('v2board.server_token') ?? 'null') . "\n\n",
    FILE_APPEND | LOCK_EX
);
```
**目的：** 验证 webhook 是否收到请求
**清理时删除：** 整个代码块（第14-27行）

## 清理计划
- [ ] 确认 webhook 正常工作后，删除上述调试代码
- [ ] 删除生成的日志文件：`storage/logs/webhook_debug.log`（如果存在）
- [ ] 删除本记录文件

## 提交信息模板
```
清理调试日志：移除webhook调试文件日志
```

