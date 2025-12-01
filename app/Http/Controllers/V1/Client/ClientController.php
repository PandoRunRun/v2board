<?php

namespace App\Http\Controllers\V1\Client;

use App\Http\Controllers\Controller;
use App\Protocols\General;
use App\Protocols\Singbox\Singbox;
use App\Protocols\Singbox\SingboxOld;
use App\Services\ServerService;
use App\Services\UserService;
use App\Utils\Helper;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function subscribe(Request $request)
    {
        $flag = $request->input('flag')
            ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $flag = strtolower($flag);
        $user = $request->user;

        $userService = new UserService();

        // ----------------------------------------------------------
        // 分支 1：账户状态正常 (未过期 且 流量充足)
        // ----------------------------------------------------------
        if ($userService->isAvailable($user)) {
            $serverService = new ServerService();
            $servers = $serverService->getAvailableServers($user);
            
            if ($flag) {
                // 处理非 Sing-box 客户端
                if (!strpos($flag, 'sing')) {
                    $this->setSubscribeInfoToServers($servers, $user);
                    foreach (array_reverse(glob(app_path('Protocols') . '/*.php')) as $file) {
                        $file = 'App\\Protocols\\' . basename($file, '.php');
                        $class = new $file($user, $servers);
                        if (strpos($flag, $class->flag) !== false) {
                            return $class->handle();
                        }
                    }
                }
                // 处理 Sing-box 客户端
                if (strpos($flag, 'sing') !== false) {
                    $version = null;
                    if (preg_match('/sing-box\s+([0-9.]+)/i', $flag, $matches)) {
                        $version = $matches[1];
                    }
                    if (!is_null($version) && $version >= '1.12.0') {
                        $class = new Singbox($user, $servers);
                    } else {
                        $class = new SingboxOld($user, $servers);
                    }
                    return $class->handle();
                }
            }
            // 默认通用订阅
            $class = new General($user, $servers);
            return $class->handle();

        // ----------------------------------------------------------
        // 分支 2：账户状态异常 (已过期 或 没流量)，但未被封禁
        // ----------------------------------------------------------
        } elseif (!$user['banned']) {
            
            // 【重点修改】初始化为空数组，绝不获取真实节点
            $servers = [];

            // 【重点修改】构造一个“假”的模板节点
            // 必须包含协议转换类所需的字段，否则会报错。这里使用 VMess 或 Trojan 结构。
            $templateServer = [
                'type' => 'vmess', // 使用常见协议以保证客户端兼容性
                'name' => '提示',
                'group_name' => '系统通知',
                'server' => '127.0.0.1', // 环回地址，确保无法连接
                'port' => 65535,
                'uuid' => '00000000-0000-0000-0000-000000000000',
                'alterId' => 0,
                'cipher' => 'auto',
                'network' => 'tcp',
                'tls' => 0,
                'tags' => [],
                // 如果是 Trojan 格式，可以解开下面注释并修改 type 为 trojan
                // 'password' => 'expired', 
            ];

            // 计算剩余流量
            $useTraffic = $user['u'] + $user['d'];
            $totalTraffic = $user['transfer_enable'];
            $remainingTrafficValue = $totalTraffic - $useTraffic;

            // --- 开始堆叠提示信息 (注意：unshift 是插入到数组开头) ---

            // 4. 官网提示 (最底部)
            array_unshift($servers, array_merge($templateServer, [
                'name' => "官网: https://潘多快跑.com",
            ]));

            // 3. 引导续费 (中间)
            array_unshift($servers, array_merge($templateServer, [
                'name' => "请去往官网重置流量或续费",
            ]));

            // 2. 流量耗尽提示 (如果满足条件)
            if ($remainingTrafficValue <= 0) {
                array_unshift($servers, array_merge($templateServer, [
                    'name' => "您的流量已用尽",
                ]));
            }

            // 1. 过期提示 (最顶部)
            if ($user['expired_at'] !== NULL && $user['expired_at'] <= time()) {
                array_unshift($servers, array_merge($templateServer, [
                    'name' => "您的订阅已过期",
                ]));
            }

            // 保底：如果上面都没命中（理论上不可能），给个默认提示
            if (count($servers) === 0) {
                array_unshift($servers, array_merge($templateServer, [
                    'name' => "账户暂停服务",
                ]));
            }

            // --- 下发假订阅信息 (逻辑同正常用户，只是数据是假的) ---
            
            if ($flag) {
                if (!strpos($flag, 'sing')) {
                    foreach (array_reverse(glob(app_path('Protocols') . '/*.php')) as $file) {
                        $file = 'App\\Protocols\\' . basename($file, '.php');
                        $class = new $file($user, $servers);
                        if (strpos($flag, $class->flag) !== false) {
                            return $class->handle();
                        }
                    }
                }
                if (strpos($flag, 'sing') !== false) {
                    $version = null;
                    if (preg_match('/sing-box\s+([0-9.]+)/i', $flag, $matches)) {
                        $version = $matches[1];
                    }
                    if (!is_null($version) && $version >= '1.12.0') {
                        $class = new Singbox($user, $servers);
                    } else {
                        $class = new SingboxOld($user, $servers);
                    }
                    return $class->handle();
                }
            }

            $class = new General($user, $servers);
            return $class->handle();
        }
    }

    private function setSubscribeInfoToServers(&$servers, $user)
    {
        if (!isset($servers[0])) return;
        if (!(int)config('v2board.show_info_to_server_enable', 0)) return;
        $useTraffic = $user['u'] + $user['d'];
        $totalTraffic = $user['transfer_enable'];
        $remainingTraffic = Helper::trafficConvert($totalTraffic - $useTraffic);
        $expiredDate = $user['expired_at'] ? date('Y-m-d', $user['expired_at']) : '长期有效';
        $userService = new UserService();
        $resetDay = $userService->getResetDay($user);
        array_unshift($servers, array_merge($servers[0], [
            'name' => "套餐到期：{$expiredDate}",
        ]));
        if ($resetDay) {
            array_unshift($servers, array_merge($servers[0], [
                'name' => "距离下次重置剩余：{$resetDay} 天",
            ]));
        }
        array_unshift($servers, array_merge($servers[0], [
            'name' => "剩余流量：{$remainingTraffic}",
        ]));
    }
}
