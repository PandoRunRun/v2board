<?php

namespace App\Http\Controllers\V1\Server;

use App\Http\Controllers\Controller;
use App\Services\ServerService;
use App\Utils\CacheKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * 节点出口探测结果上报接口。
 *
 * 该接口沿用 v2board 现有的 server_token 节点通信鉴权，不接受客户端
 * 直接指定 parent_id；父节点关系始终从实际节点记录的 parent_id 推导。
 */
class ProbeController extends Controller
{
    private const HISTORY_LIMIT = 48;
    private const HISTORY_TTL = 259200; // 3 days
    private const CURRENT_TTL = 10800; // 3 hours
    private const MAX_BODY_BYTES = 262144; // 256 KiB

    private const NODE_TYPES = [
        'shadowsocks',
        'vmess',
        'trojan',
        'tuic',
        'hysteria',
        'vless',
        'anytls',
        'v2node',
    ];

    private $nodeType;
    private $parentId;

    public function __construct(Request $request)
    {
        $token = (string)$request->input('token', '');
        $serverToken = (string)config('v2board.server_token', '');

        if ($token === '' || $serverToken === '' || !hash_equals($serverToken, $token)) {
            abort(403, 'token is error');
        }

        $nodeType = strtolower((string)$request->input('node_type', ''));
        if ($nodeType === 'v2ray') $nodeType = 'vmess';
        if ($nodeType === 'hysteria2') $nodeType = 'hysteria';

        $nodeId = (int)$request->input('node_id', 0);
        if ($nodeId <= 0 || !in_array($nodeType, self::NODE_TYPES, true)) {
            abort(400, 'invalid node identity');
        }

        $nodeInfo = (new ServerService())->getServer($nodeId, $nodeType);
        if (!$nodeInfo) {
            abort(404, 'server is not exist');
        }

        $this->nodeType = $nodeType;
        $this->parentId = (int)($nodeInfo->parent_id ?: $nodeInfo->id);
    }

    /**
     * POST /api/v1/server/probe/report
     */
    public function report(Request $request)
    {
        if (!$request->isMethod('post')) {
            return response(['message' => 'method not allowed'], 405);
        }

        if (strlen((string)$request->getContent()) > self::MAX_BODY_BYTES) {
            return response(['message' => 'probe payload too large'], 413);
        }

        $payload = $request->json()->all();
        if (!is_array($payload)) {
            return response(['message' => 'invalid probe payload'], 400);
        }

        $media = $this->normalizeRows($payload['media'] ?? [], true);
        $network = [
            'sites' => $this->normalizeRows($payload['network']['sites'] ?? [], false),
            'cdns' => $this->normalizeRows($payload['network']['cdns'] ?? [], false),
        ];

        if (count($media) === 0 && count($network['sites']) === 0 && count($network['cdns']) === 0) {
            return response(['message' => 'probe result is empty'], 422);
        }

        $now = time();
        $agentCheckedAt = (int)($payload['checked_at'] ?? 0);
        if ($agentCheckedAt <= 0 || abs($now - $agentCheckedAt) > 3600) {
            $agentCheckedAt = $now;
        }

        $snapshot = [
            'version' => 1,
            'node_type' => $this->nodeType,
            'parent_id' => $this->parentId,
            'checked_at' => $agentCheckedAt,
            'reported_at' => $now,
            'media' => $media,
            'network' => $network,
        ];

        $cacheId = $this->cacheId();
        $historyKey = CacheKey::get('SERVER_PROBE_HISTORY', $cacheId);
        $currentKey = CacheKey::get('SERVER_PROBE_CURRENT', $cacheId);
        $history = Cache::get($historyKey, []);
        if (!is_array($history)) $history = [];

        array_unshift($history, $snapshot);
        $history = array_slice($history, 0, self::HISTORY_LIMIT);

        Cache::put($currentKey, $snapshot, self::CURRENT_TTL);
        Cache::put($historyKey, $history, self::HISTORY_TTL);

        return response([
            'data' => true,
            'parent_id' => $this->parentId,
            'checked_at' => $agentCheckedAt,
            'history_count' => count($history),
        ]);
    }

    private function cacheId(): string
    {
        return sprintf('%s_%d', $this->nodeType, $this->parentId);
    }

    /**
     * 只保存前端需要的有限字段，避免把任意请求内容写入 Redis。
     */
    private function normalizeRows($rows, bool $isMedia): array
    {
        if (!is_array($rows)) return [];

        $result = [];
        foreach ($rows as $key => $row) {
            if (count($result) >= 64 || !is_array($row)) continue;

            $id = preg_replace('/[^a-zA-Z0-9_.-]/', '', (string)($row['id'] ?? $key));
            if ($id === '') continue;

            $name = trim((string)($row['name'] ?? $id));
            $status = trim((string)($row['status'] ?? 'unknown'));
            if ($name === '') $name = $id;
            if ($status === '') $status = 'unknown';

            $item = [
                'id' => substr($id, 0, 64),
                'name' => substr($name, 0, 128),
                'status' => substr($status, 0, 48),
            ];

            foreach (['region', 'detail', 'domain'] as $field) {
                if (isset($row[$field]) && is_scalar($row[$field])) {
                    $item[$field] = substr(trim((string)$row[$field]), 0, 160);
                }
            }

            if (!$isMedia) {
                $latency = $row['latency_ms'] ?? null;
                $item['latency_ms'] = is_numeric($latency)
                    ? max(0, min(600000, (int)round((float)$latency)))
                    : null;

                if (isset($row['loss']) && is_numeric($row['loss'])) {
                    $item['loss'] = max(0, min(100, round((float)$row['loss'], 2)));
                }
            }

            $result[] = $item;
        }

        return $result;
    }
}
