#!/usr/bin/env bash
set -Eeuo pipefail

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
    echo "请使用 root 执行此安装脚本，或使用 sudo。" >&2
    exit 1
fi

API_DEFAULT="https://api.pandorun.run"
AGENT_URL="${V2BOARD_NODE_PROBE_AGENT_URL:-https://github.com/PandoRunRun/v2board/raw/refs/heads/master/scripts/node-probe/probe-agent.sh}"
API_URL=""
API_TOKEN=""
NODE_TYPE="vless"
NODE_IDS=""
MEDIA_IP_VERSION="4"
INTERVAL_MINUTES=60

usage() {
    cat <<'EOF'
用法：
  bash install.sh [选项]

不带选项时会进入文字交互。默认节点类型为 vless，默认探测间隔为 60 分钟。

选项：
  --api-url URL       节点端 API 根地址，默认 https://api.pandorun.run
  --token TOKEN       v2board server_token；不填写时交互输入
  --node-type TYPE    节点类型，默认 vless
  --node-id IDS       节点 ID；支持英文逗号分隔多个同类型节点
  --ip-version VER    流媒体探测 IP 版本，只能是 4 或 6，默认 4
  --interval MIN      探测间隔，默认 60 分钟
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --api-url) API_URL="${2:-}"; shift 2 ;;
        --token) API_TOKEN="${2:-}"; shift 2 ;;
        --node-type) NODE_TYPE="${2:-vless}"; shift 2 ;;
        --node-id) NODE_IDS="${2:-}"; shift 2 ;;
        --ip-version) MEDIA_IP_VERSION="${2:-4}"; shift 2 ;;
        --interval) INTERVAL_MINUTES="${2:-60}"; shift 2 ;;
        -h|--help) usage; exit 0 ;;
        *) echo "未知参数：$1" >&2; usage >&2; exit 2 ;;
    esac
done

if [[ -t 0 ]]; then
    if [[ -z "$API_URL" ]]; then
        read -r -p "节点端 API 地址 [${API_DEFAULT}]: " input_api_url
        API_URL="${input_api_url:-$API_DEFAULT}"
    fi
    if [[ -z "$API_TOKEN" ]]; then
        read -r -s -p "v2board server_token: " API_TOKEN
        echo
    fi
    if [[ -z "$NODE_IDS" ]]; then
        read -r -p "节点 ID（可用英文逗号分隔多个同类型节点，推荐填写父节点 ID）: " NODE_IDS
    fi
    if [[ "$NODE_TYPE" == "vless" ]]; then
        read -r -p "节点类型 [vless]: " input_node_type
        NODE_TYPE="${input_node_type:-vless}"
    fi
    if [[ "$INTERVAL_MINUTES" == "60" ]]; then
        read -r -p "探测间隔，分钟 [60]: " input_interval
        INTERVAL_MINUTES="${input_interval:-60}"
    fi
    if [[ "$MEDIA_IP_VERSION" == "4" ]]; then
        read -r -p "流媒体探测 IP 版本 [4]（输入 6 使用 IPv6）: " input_ip_version
        MEDIA_IP_VERSION="${input_ip_version:-4}"
    fi
else
    API_URL="${API_URL:-$API_DEFAULT}"
fi

if [[ -z "$API_URL" || -z "$API_TOKEN" || -z "$NODE_IDS" ]]; then
    echo "API 地址、server_token 和节点 ID 不能为空。" >&2
    exit 2
fi
NODE_IDS="$(printf '%s' "$NODE_IDS" | tr -d '[:space:]')"
IFS=',' read -r -a NODE_ID_LIST <<< "$NODE_IDS"
if [[ "${#NODE_ID_LIST[@]}" -eq 0 ]]; then
    echo "至少需要填写一个节点 ID。" >&2
    exit 2
fi
for node_id in "${NODE_ID_LIST[@]}"; do
    if [[ ! "$node_id" =~ ^[1-9][0-9]*$ ]]; then
        echo "节点 ID 必须是英文逗号分隔的正整数：$NODE_IDS" >&2
        exit 2
    fi
done
if [[ ! "$INTERVAL_MINUTES" =~ ^[1-9][0-9]*$ || "$INTERVAL_MINUTES" -lt 30 ]]; then
    echo "探测间隔至少为 30 分钟。" >&2
    exit 2
fi
if [[ "$MEDIA_IP_VERSION" != "4" && "$MEDIA_IP_VERSION" != "6" ]]; then
    echo "流媒体探测 IP 版本只能是 4 或 6。" >&2
    exit 2
fi
case "$NODE_TYPE" in
    shadowsocks|vmess|trojan|tuic|hysteria|vless|anytls|v2node) ;;
    v2ray) NODE_TYPE="vmess" ;;
    hysteria2) NODE_TYPE="hysteria" ;;
    *) echo "不支持的节点类型：$NODE_TYPE" >&2; exit 2 ;;
esac

API_URL="${API_URL%/}"
case "$API_URL" in
    */api/v1/server/probe/report) ;;
    */api/v1) API_URL="$API_URL/server/probe/report" ;;
    *) API_URL="$API_URL/api/v1/server/probe/report" ;;
esac

if command -v apt-get >/dev/null 2>&1; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    apt-get install -y --no-install-recommends bash ca-certificates curl coreutils jq nmap python3 util-linux
else
    echo "当前系统没有 apt-get，请先安装 bash、curl、python3、nmap、util-linux。" >&2
    exit 1
fi

agent_tmp="$(mktemp)"
trap 'rm -f -- "$agent_tmp"' EXIT
curl -fsSL --retry 3 --connect-timeout 15 --max-time 60 "$AGENT_URL" -o "$agent_tmp"
grep -q 'python3 - "$MEDIA_DIR"' "$agent_tmp" || {
    echo "下载到的探测代理版本校验失败，已停止安装。" >&2
    exit 1
}

install -d -m 0750 /usr/local/libexec /var/lib/v2board-node-probe /var/lock
install -m 0750 "$agent_tmp" /usr/local/libexec/v2board-node-probe
umask 077
cat > /etc/v2board-node-probe.env <<EOF
API_URL=$(printf '%q' "$API_URL")
API_TOKEN=$(printf '%q' "$API_TOKEN")
NODE_TYPE=$(printf '%q' "$NODE_TYPE")
NODE_IDS=$(printf '%q' "$NODE_IDS")
MEDIA_IP_VERSION=$(printf '%q' "$MEDIA_IP_VERSION")
MEDIA_TIMEOUT_SECONDS=240
EOF

cat > /etc/systemd/system/v2board-node-probe.service <<'EOF'
[Unit]
Description=v2board node media and international connectivity probe
Wants=network-online.target
After=network-online.target

[Service]
Type=oneshot
ExecStart=/usr/local/libexec/v2board-node-probe
EOF

cat > /etc/systemd/system/v2board-node-probe.timer <<EOF
[Unit]
Description=Run v2board node probe periodically

[Timer]
OnBootSec=5min
OnUnitActiveSec=${INTERVAL_MINUTES}min
Persistent=true

[Install]
WantedBy=timers.target
EOF

systemctl daemon-reload
systemctl enable --now v2board-node-probe.timer
systemctl start v2board-node-probe.service

echo
echo "安装完成。"
echo "API：$API_URL"
echo "节点：${NODE_TYPE}/${NODE_IDS}"
echo "流媒体探测：IPv${MEDIA_IP_VERSION}"
echo "查看日志：journalctl -u v2board-node-probe.service -n 100 --no-pager"
