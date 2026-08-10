# v2board 节点出口探测代理

这个代理每次执行时从上游拉取最新的两个脚本：

- [RegionRestrictionCheck](https://raw.githubusercontent.com/1-stream/RegionRestrictionCheck/main/check.sh)：IPv4 的 Netflix、Disney+、HBO Max、YouTube Premium、ChatGPT、Gemini。
- TCPQuality 的国际网站/CDN 目标列表：代理内置目标，不在节点运行时下载或执行 TCPQuality。

结果通过现有的 `server_token` 上报到 `POST /api/v1/server/probe/report`。后端从 `node_type + node_id` 对应的实际节点记录读取 `parent_id`，所以同一父节点的所有子节点共享一份状态历史。安装时填写父节点 ID 最直观；填写子节点 ID 也会自动归并。

## 安装

在节点 VPS 上以 root 执行下面的一键命令，脚本会自动下载探测代理并进行文字交互：

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/PandoRunRun/v2board/master/scripts/node-probe/install.sh)
```

交互默认使用 `https://api.pandorun.run`、`vless` 和 60 分钟间隔。节点 ID 推荐填写父节点 ID；填写子节点 ID 也会由后端自动归并到父节点。最短间隔为 30 分钟，保留 48 条记录后约有两天历史。

也支持无交互参数模式：

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/PandoRunRun/v2board/master/scripts/node-probe/install.sh) \
  --api-url https://api.pandorun.run --token '你的 server_token' \
  --node-type vless --node-id 6 --interval 60
```

安装脚本会创建 `/etc/v2board-node-probe.env`、`/usr/local/libexec/v2board-node-probe` 和 systemd 定时器。检查命令：

```bash
systemctl status v2board-node-probe.timer
journalctl -u v2board-node-probe.service -n 100 --no-pager
systemctl start v2board-node-probe.service
```

网站/CDN 部分使用 `nping` 对内置目标执行 IPv4 TCP 443 SYN 探测，计算可达状态、平均延迟和丢包率；不会执行 TCPQuality 的延迟重传、回程识别或测速流程。流媒体/AI 部分仍然每次从 [RegionRestrictionCheck](https://raw.githubusercontent.com/1-stream/RegionRestrictionCheck/main/check.sh) 拉取最新脚本。

## 安全边界

- 代理只执行脚本内置目标，不接受面板下发的任意 shell 或 URL。
- 上游脚本每次执行前重新下载；执行结束后清理临时目录。
- 后端不会信任请求中的 `parent_id`，也不会把任意请求字段原样写入 Redis。
- 该接口沿用现有节点通信 token；若未来需要更细的隔离，再增加每个父节点独立 token 即可。
