#!/usr/bin/env bash
set -Eeuo pipefail

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
    echo "请使用 root 执行此安装脚本。" >&2
    exit 1
fi

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
API_URL=""; API_TOKEN=""; NODE_TYPE=""; NODE_ID=""; INTERVAL_MINUTES=60
usage() {
    cat <<'EOF'
用法：
  bash install.sh --api-url https://panel.example.com --token SERVER_TOKEN \
    --node-type vmess --node-id 1 [--interval 60]

--node-id 推荐填写父节点 ID；填写子节点 ID 也会由后端自动归并到父节点。
EOF
}
while [[ $# -gt 0 ]]; do
    case "$1" in
        --api-url) API_URL="${2:-}"; shift 2 ;;
        --token) API_TOKEN="${2:-}"; shift 2 ;;
        --node-type) NODE_TYPE="${2:-}"; shift 2 ;;
        --node-id) NODE_ID="${2:-}"; shift 2 ;;
        --interval) INTERVAL_MINUTES="${2:-}"; shift 2 ;;
        -h|--help) usage; exit 0 ;;
        *) echo "未知参数：$1" >&2; usage >&2; exit 2 ;;
    esac
done
if [[ -z "$API_URL" || -z "$API_TOKEN" || -z "$NODE_TYPE" || -z "$NODE_ID" ]]; then usage >&2; exit 2; fi
if [[ ! "$NODE_ID" =~ ^[1-9][0-9]*$ ]]; then echo "--node-id 必须是正整数。" >&2; exit 2; fi
if [[ ! "$INTERVAL_MINUTES" =~ ^[1-9][0-9]*$ || "$INTERVAL_MINUTES" -lt 30 ]]; then echo "--interval 至少为 30 分钟。" >&2; exit 2; fi

API_URL="${API_URL%/}"
if [[ "$API_URL" != */api/v1/server/probe/report ]]; then API_URL="$API_URL/api/v1/server/probe/report"; fi

if command -v apt-get >/dev/null 2>&1; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    apt-get install -y --no-install-recommends bash ca-certificates curl coreutils jq nmap python3 util-linux
else
    echo "当前系统没有 apt-get。请先手动安装 bash、curl、python3、nmap、util-linux 后再运行。" >&2
    exit 1
fi

install -d -m 0750 /usr/local/libexec /var/lib/v2board-node-probe /var/lock
install -m 0750 "$SCRIPT_DIR/probe-agent.sh" /usr/local/libexec/v2board-node-probe
umask 077
cat > /etc/v2board-node-probe.env <<EOF
API_URL=$(printf '%q' "$API_URL")
API_TOKEN=$(printf '%q' "$API_TOKEN")
NODE_TYPE=$(printf '%q' "$NODE_TYPE")
NODE_ID=$(printf '%q' "$NODE_ID")
MEDIA_TIMEOUT_SECONDS=240
NETWORK_TIMEOUT_SECONDS=900
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
echo "查看定时器：systemctl status v2board-node-probe.timer"
echo "查看最近日志：journalctl -u v2board-node-probe.service -n 100 --no-pager"
