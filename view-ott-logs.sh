#!/bin/bash
# 查看 OTT 相关日志的便捷脚本

LOG_FILE="storage/logs/laravel.log"

if [ ! -f "$LOG_FILE" ]; then
    echo "日志文件不存在: $LOG_FILE"
    echo "查找最近的日志文件..."
    find storage/logs -name "*.log" -type f 2>/dev/null | head -5
    exit 1
fi

echo "=== 查看 OTT 相关日志 ==="
echo ""

# 查看最后 50 行中与 OTT 相关的内容
echo "1. 最近的 OTT 日志（最后 50 行）:"
echo "-----------------------------------"
tail -n 50 "$LOG_FILE" | grep -i "ott" || echo "未找到 OTT 相关内容"

echo ""
echo "2. OTT fetchAccount 相关日志:"
echo "-----------------------------------"
grep -A 10 "OTT fetchAccount" "$LOG_FILE" | tail -n 50 || echo "未找到 fetchAccount 相关内容"

echo ""
echo "3. 查看实时日志（按 Ctrl+C 退出）:"
echo "-----------------------------------"
echo "运行命令: tail -f $LOG_FILE | grep -i ott"
