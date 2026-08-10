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
curl -fsSL --retry 3 --connect-timeout 15 --max-time 60 "$MEDIA_URL" -o "$WORK_DIR/region-check.sh"
chmod 700 "$WORK_DIR/region-check.sh"

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
echo "运行常用网站/CDN IPv4 TCP 443 探测。"

# 目标列表摘自 TCPQuality 的 INTERNATIONAL_SITE_TARGETS 和
# INTERNATIONAL_CDN_TARGETS。列表固定在代理中，避免每次运行下载并执行
# TCPQuality 的综合测速、回程识别和重传检测流程。
NETWORK_TARGETS=(
    '网站|Adobe Assets|assets.adobe.com'
    '网站|Amazon|www.amazon.com'
    '网站|Apple iCloud|www.icloud.com'
    '网站|AWS STS|sts.amazonaws.com'
    '网站|ChatGPT|chatgpt.com'
    '网站|Claude|claude.ai'
    '网站|Cloudflare Dashboard|dash.cloudflare.com'
    '网站|Discord Gateway|gateway.discord.gg'
    '网站|Dropbox API|api.dropboxapi.com'
    '网站|Facebook|www.facebook.com'
    '网站|GitHub API|api.github.com'
    '网站|GitLab|gitlab.com'
    '网站|Gmail|mail.google.com'
    '网站|Google Search|www.google.com'
    '网站|Google Static|www.gstatic.com'
    '网站|Instagram|www.instagram.com'
    '网站|Microsoft Login|login.microsoftonline.com'
    '网站|Netflix API|api-global.netflix.com'
    '网站|NodeSeek|www.nodeseek.com'
    '网站|Notion API|api.notion.com'
    '网站|OpenAI API|api.openai.com'
    '网站|PayPal API|api-m.paypal.com'
    '网站|Reddit OAuth|oauth.reddit.com'
    '网站|Slack App|app.slack.com'
    '网站|Spotify Web|open.spotify.com'
    '网站|Steam|store.steampowered.com'
    '网站|Telegram|telegram.org'
    '网站|Wikipedia|www.wikipedia.org'
    '网站|X|x.com'
    '网站|YouTube API|youtubei.googleapis.com'
    '网站|Zoom API|api.zoom.us'
    'CDN|Akamai Edge|www.akamai.com'
    'CDN|AWS Static|d1.awsstatic.com'
    'CDN|CacheFly|cachefly.cachefly.net'
    'CDN|CDN77 Demo|1906714720.rsc.cdn77.org'
    'CDN|Cloudflare CDNJS|cdnjs.cloudflare.com'
    'CDN|Fastly Demo|http-me.fastly.dev'
    'CDN|Google Fonts Static|fonts.gstatic.com'
    'CDN|Google Hosted Libraries|ajax.googleapis.com'
    'CDN|jsDelivr|cdn.jsdelivr.net'
    'CDN|Microsoft Ajax CDN|ajax.aspnetcdn.com'
    'CDN|QUANTIL Edge|www.quantil.com'
    'CDN|Tencent EdgeOne|edgeone.ai'
    'CDN|UNPKG|unpkg.com'
    'CDN|Vercel Edge|vercel.com'
)

run_network_probe() {
    local category="$1" name="$2" domain="$3"
    local prefix="site"
    [[ "$category" == "CDN" ]] && prefix="cdn"
    local result_file="$NETWORK_DIR/${prefix}-${domain//./_}.tsv"
    local ip="" raw="" summary="" sent=5 received=0 latency="" loss="100.00"

    ip="$(getent ahostsv4 "$domain" 2>/dev/null | awk 'NR == 1 {print $1}' || true)"
    if [[ -n "$ip" ]]; then
        raw="$(timeout 25 nping --tcp -p 443 --flags syn -c 5 --delay 100ms "$ip" 2>&1 || true)"
        summary="$(printf '%s\n' "$raw" | grep -m1 'Raw packets sent:' || true)"
        if [[ "$summary" =~ Raw[[:space:]]packets[[:space:]]sent:[[:space:]]([0-9]+).*Rcvd:[[:space:]]([0-9]+) ]]; then
            sent="${BASH_REMATCH[1]}"
            received="${BASH_REMATCH[2]}"
        fi
        latency="$(printf '%s\n' "$raw" | sed -n 's/.*Avg rtt: \([0-9.][0-9.]*\)ms.*/\1/p' | head -n 1)"
        if [[ "$sent" =~ ^[0-9]+$ && "$received" =~ ^[0-9]+$ && "$sent" -gt 0 ]]; then
            loss="$(awk -v sent="$sent" -v received="$received" 'BEGIN { printf "%.2f", (sent - received) * 100 / sent }')"
        fi
    fi
    [[ "$received" -gt 0 ]] && status="ok" || status="fail"
    printf '%s\t%s\t%s\t%s\t%s\t%s\n' "$category" "$name" "$domain" "$status" "$latency" "$loss" >"$result_file"
}

if command -v nping >/dev/null 2>&1 && command -v getent >/dev/null 2>&1; then
    running=0
    for target in "${NETWORK_TARGETS[@]}"; do
        IFS='|' read -r category name domain <<<"$target"
        run_network_probe "$category" "$name" "$domain" &
        ((running += 1))
        if (( running >= ${NETWORK_PARALLEL:-8} )); then
            wait -n || true
            ((running -= 1))
        fi
    done
    wait || true
    echo "常用网站/CDN 探测完成：${#NETWORK_TARGETS[@]} 个目标。"
else
    echo "未找到 nping 或 getent，跳过网站/CDN 探测。" >&2
fi

python3 - "$MEDIA_DIR" "$NETWORK_DIR" "$WORK_DIR/payload.json" "$NODE_TYPE" "$NODE_ID" <<'PY'
import json
import re
import sys
import time
from pathlib import Path

media_dir = Path(sys.argv[1])
network_dir = Path(sys.argv[2])
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
for result_file in sorted(network_dir.glob("*.tsv")):
    try:
        category, name, domain, raw_status, raw_latency, raw_loss = result_file.read_text(encoding="utf-8").strip().split("\t")
    except (OSError, ValueError):
        continue
    try:
        latency = int(round(float(raw_latency))) if raw_latency else None
        latency = max(0, latency) if latency is not None else None
    except ValueError:
        latency = None
    try:
        loss = round(max(0.0, min(100.0, float(raw_loss))), 2)
    except ValueError:
        loss = None
    item = {
        "id": ("cdn-" if category == "CDN" else "site-") + re.sub(r"[^a-zA-Z0-9_.-]", "", domain),
        "name": name[:128], "domain": domain[:160],
        "status": raw_status, "latency_ms": latency,
    }
    if loss is not None:
        item["loss"] = loss
    (cdns if category == "CDN" else sites).append(item)
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

echo "节点探测上报完成：${NODE_TYPE}/${NODE_ID}"
