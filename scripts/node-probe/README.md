# v2board 节点出口探测代理

这个代理每次执行时从上游拉取最新的两个脚本：

- [RegionRestrictionCheck](https://raw.githubusercontent.com/1-stream/RegionRestrictionCheck/main/check.sh)：IPv4 的 Netflix、Disney+、HBO Max、YouTube Premium、ChatGPT、Gemini。
- [TcpQuality](https://raw.githubusercontent.com/ibsgss/TcpQuality/main/runTcpQuality.sh)：常用网站和国际 CDN 的 IPv4/TCP 443 连通性、丢包率和延迟。

结果通过现有的 `server_token` 上报到 `POST /api/v1/server/probe/report`。后端从 `node_type + node_id` 对应的实际节点记录读取 `parent_id`，所以同一父节点的所有子节点共享一份状态历史。安装时填写父节点 ID 最直观；填写子节点 ID 也会自动归并。

## 安装

在包含本目录的 v2board 工作副本中，以 root 执行：

```bash
bash scripts/node-probe/install.sh \
  --api-url https://panel.example.com/api/v1 \
  --token '你的 server_token' \
  --node-type vmess \
  --node-id 1 \
  --interval 60
```

`--api-url` 应填写节点端 API 根地址，也可以直接填写完整的 `/api/v1/server/probe/report` 地址。本项目节点端使用 `https://api.pandorun.run`，代理最终会请求 `https://api.pandorun.run/api/v1/server/probe/report`。最短间隔为 30 分钟；默认 60 分钟，保留 48 条记录后约有两天历史。

安装脚本会创建 `/etc/v2board-node-probe.env`、`/usr/local/libexec/v2board-node-probe` 和 systemd 定时器。检查命令：

```bash
systemctl status v2board-node-probe.timer
journalctl -u v2board-node-probe.service -n 100 --no-pager
systemctl start v2board-node-probe.service
```

TCPQuality 使用 `--no-rootfs --intl -v4 --no-rank-upload`，只运行国际互联的网站/CDN部分，不上传其公共排名报告。探测脚本仍会把整理后的结果上报到自己的 v2board 后端。

## 安全边界

- 代理只执行脚本内置目标，不接受面板下发的任意 shell 或 URL。
- 上游脚本每次执行前重新下载；执行结束后清理临时目录。
- 后端不会信任请求中的 `parent_id`，也不会把任意请求字段原样写入 Redis。
- 该接口沿用现有节点通信 token；若未来需要更细的隔离，再增加每个父节点独立 token 即可。
