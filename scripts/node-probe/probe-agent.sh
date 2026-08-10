#!/usr/bin/env bash
set -Eeuo pipefail

CONFIG_FILE="${V2BOARD_NODE_PROBE_CONFIG:-/etc/v2board-node-probe.env}"
STATE_DIR="${V2BOARD_NODE_PROBE_STATE_DIR:-/var/lib/v2board-node-probe}"
LOCK_FILE="${V2BOARD_NODE_PROBE_LOCK_FILE:-/var/lock/v2board-node-probe.lock}"

if [[ ! -r "$CONFIG_FILE" ]]; then
    echo "配置文件不存在：$CONFIG_FILE" >&2
    exit 1
fi
# shellcheck disable=SC1090
source "$CONFIG_FILE"
: "${API_URL:?API_URL is required}"
: "${API_TOKEN:?API_TOKEN is required}"
: "${NODE_TYPE:?NODE_TYPE is required}"
: "${NODE_ID:?NODE_ID is required}"

mkdir -p "$STATE_DIR" && chmod 700 "$STATE_DIR"
mkdir -p "$(dirname "$LOCK_FILE")"
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    echo "已有探测任务运行，跳过本次执行。"
    exit 0
fi

WORK_DIR="$(mktemp -d "$STATE_DIR/run.XXXXXX")"
trap 'rm -rf -- "$WORK_DIR"' EXIT
MEDIA_URL="https://raw.githubusercontent.com/1-stream/RegionRestrictionCheck/main/check.sh"
TCP_QUALITY_URL="https://raw.githubusercontent.com/ibsgss/TcpQuality/main/runTcpQuality.sh"
curl -fsSL --retry 3 --connect-timeout 15 --max-time 60 "$MEDIA_URL" -o "$WORK_DIR/region-check.sh"
curl -fsSL --retry 3 --connect-timeout 15 --max-time 60 "$TCP_QUALITY_URL" -o "$WORK_DIR/tcp-quality.sh"
chmod 700 "$WORK_DIR/region-check.sh" "$WORK_DIR/tcp-quality.sh"

# 上游 -F 模式会同时调用 IPv4/IPv6；临时改写下载副本，只保留 IPv4 分支。
python3 - "$WORK_DIR/region-check.sh" "$WORK_DIR/region-check-v4.sh" <<'PY'
from pathlib import Path
import sys

source_path, output_path = map(Path, sys.argv[1:])
source = source_path.read_text(encoding="utf-8")
marker = 'if [ -n "$func" ]; then'
start = source.find(marker)
end_marker = "    exit\nfi"
end = source.find(end_marker, start)
if start < 0 or end < 0:
    raise SystemExit("无法定位 RegionRestrictionCheck 的 -F 分支，拒绝执行未知版本")
end += len(end_marker)
replacement = '''if [ -n "$func" ]; then
    echo -e "${Font_Green}IPv4:${Font_Suffix}"
    $func 4
    exit
fi'''
output_path.write_text(source[:start] + replacement + source[end:], encoding="utf-8")
PY
chmod 700 "$WORK_DIR/region-check-v4.sh"

MEDIA_DIR="$WORK_DIR/media"
mkdir -p "$MEDIA_DIR"
run_media_test() {
    local id="$1" name="$2" function_name="$3"
    echo "运行流媒体/AI 探测：$name"
    if ! timeout "${MEDIA_TIMEOUT_SECONDS:-240}" bash "$WORK_DIR/region-check-v4.sh" -M 4 -F "$function_name" >"$MEDIA_DIR/$id.txt" 2>&1; then
        echo "流媒体探测失败：$name" >&2
    fi
}
run_media_test "netflix" "Netflix" "MediaUnlockTest_Netflix"
run_media_test "disney_plus" "Disney+" "MediaUnlockTest_DisneyPlus"
run_media_test "hbo_max" "HBO Max" "MediaUnlockTest_HBOMax"
run_media_test "youtube_premium" "YouTube Premium" "MediaUnlockTest_YouTube_Premium"
run_media_test "chatgpt" "ChatGPT" "MediaUnlockTest_ChatGPT"
run_media_test "gemini" "Gemini" "AIUnlockTest_Gemini_location"

NETWORK_DIR="$WORK_DIR/network"
mkdir -p "$NETWORK_DIR"
CSV_MARKER="$WORK_DIR/network-csv.marker"
touch "$CSV_MARKER"
echo "运行常用网站/CDN 国际互联探测。"
if ! timeout "${NETWORK_TIMEOUT_SECONDS:-900}" bash "$WORK_DIR/tcp-quality.sh" --no-rootfs --intl -v4 --no-rank-upload >"$NETWORK_DIR/tcp-quality.log" 2>&1; then
    echo "TCPQuality 探测失败，将只上报可用的流媒体结果。" >&2
fi

NETWORK_CSV=""
while IFS= read -r candidate; do
    NETWORK_CSV="$candidate"
    break
done < <(find /tmp -maxdepth 1 -type f -name 'zstatic_nping_*.csv' -newer "$CSV_MARKER" -print 2>/dev/null | sort -r)

python3 - "$MEDIA_DIR" "$NETWORK_CSV" "$WORK_DIR/payload.json" "$NODE_TYPE" "$NODE_ID" <<'PY'
import csv
import json
import re
import sys
import time
from pathlib import Path

media_dir = Path(sys.argv[1])
network_csv = sys.argv[2]
payload_path = Path(sys.argv[3])
node_type = sys.argv[4]
node_id = int(sys.argv[5])
ansi = re.compile(r"\x1B(?:[@-Z\\-_]|\[[0-?]*[ -/]*[@-~])")
media_specs = [("netflix", "Netflix"), ("disney_plus", "Disney+"), ("hbo_max", "HBO Max"), ("youtube_premium", "YouTube Premium"), ("chatgpt", "ChatGPT"), ("gemini", "Gemini")]

def clean(value):
    return ansi.sub("", value or "").replace("\r", "").strip()

def parse_media(path, item_id, name):
    try:
        text = ansi.sub("", path.read_text(encoding="utf-8", errors="replace"))
    except OSError as exc:
        return {"id": item_id, "name": name, "status": "unknown", "detail": str(exc)}
    ipv4 = text.split("IPv4", 1)[1] if "IPv4" in text else text
    lines = [clean(line) for line in ipv4.split("IPv6", 1)[0].splitlines() if clean(line)]
    line = next((line for line in lines if name.lower() in line.lower()), "")
    if not line and item_id == "gemini":
        line = next((line for line in lines if "Gemini" in line), "")
    lowered = line.lower()
    if "originals only" in lowered:
        status = "originals_only"
    elif re.search(r"\byes\b", lowered):
        status = "yes"
    elif re.search(r"\bno\b", lowered):
        status = "no"
    elif "failed" in lowered or "error" in lowered:
        status = "error"
    else:
        status = "unknown"
    result = {"id": item_id, "name": name, "status": status}
    region = re.search(r"region\s*:\s*([A-Za-z0-9_-]+)", line, re.I)
    if region:
        result["region"] = region.group(1).upper()
    if line:
        result["detail"] = line[:160]
    return result

media = [parse_media(media_dir / f"{item_id}.txt", item_id, name) for item_id, name in media_specs]
sites, cdns = [], []
if network_csv:
    try:
        with open(network_csv, "r", encoding="utf-8-sig", newline="") as stream:
            for row in csv.DictReader(stream):
                domain = clean(row.get("域名", ""))
                name = clean(row.get("省份", "")) or domain
                category = clean(row.get("运营商", ""))
                if not domain or category not in {"网站", "CDN"}:
                    continue
                raw_status = clean(row.get("状态", "")).upper()
                try:
                    latency = int(round(float(clean(row.get("平均延迟ms", "")))))
                    latency = max(0, latency) if latency >= 0 else None
                except (TypeError, ValueError):
                    latency = None
                try:
                    loss = round(max(0.0, min(100.0, float(clean(row.get("丢包率(%)", ""))))), 2)
                except (TypeError, ValueError):
                    loss = None
                item = {
                    "id": ("cdn-" if category == "CDN" else "site-") + re.sub(r"[^a-zA-Z0-9_.-]", "", domain),
                    "name": name[:128], "domain": domain[:160],
                    "status": "ok" if raw_status == "OK" else "fail", "latency_ms": latency,
                }
                if loss is not None:
                    item["loss"] = loss
                (cdns if category == "CDN" else sites).append(item)
    except OSError:
        pass
payload = {"version": 1, "node_type": node_type, "node_id": node_id, "checked_at": int(time.time()), "media": media, "network": {"sites": sites[:64], "cdns": cdns[:64]}}
payload_path.write_text(json.dumps(payload, ensure_ascii=False), encoding="utf-8")
PY

python3 - "$WORK_DIR/payload.json" "$API_TOKEN" <<'PY' | curl -fsS --retry 3 --connect-timeout 15 --max-time 60 -H 'Content-Type: application/json' --data-binary @- "$API_URL"
from pathlib import Path
import json
import sys
payload = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
payload["token"] = sys.argv[2]
print(json.dumps(payload, ensure_ascii=False))
PY

if [[ -n "$NETWORK_CSV" && -f "$NETWORK_CSV" ]]; then
    rm -f -- "$NETWORK_CSV"
fi
echo "节点探测上报完成：${NODE_TYPE}/${NODE_ID}"
