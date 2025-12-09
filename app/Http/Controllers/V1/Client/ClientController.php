<?php

namespace App\Http\Controllers\V1\Client;

use App\Http\Controllers\Controller;
use App\Protocols\General;
use App\Protocols\Singbox\Singbox;
use App\Protocols\Singbox\SingboxOld;
use App\Protocols\ClashMeta;
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
        if (!$user['banned']) {
            $serverService = new ServerService();
            $servers = [];
            if ($userService->isAvailable($user)) {
                $servers = $serverService->getAvailableServers($user);
            }
            
            if (empty($servers)) {
                $servers = [$this->getDummyServer()];
            }

            if($flag) {
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
        }
    }

    private function getDummyServer()
    {
        return [
            'type' => 'shadowsocks',
            'name' => '官网地址：oiii.cloud',
            'host' => '127.0.0.1',
            'port' => 443,
            'cipher' => 'aes-128-gcm',
            'password' => 'dummy',
            'group_id' => [],
            'parent_id' => null,
            'route_id' => null,
            'tags' => [],
        ];
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
        if ($user['expired_at'] !== NULL && $user['expired_at'] < time()) {
            array_unshift($servers, array_merge($servers[0], [
                'name' => "请去往官网续费订阅",
            ]));
            array_unshift($servers, array_merge($servers[0], [
                'name' => "您的套餐已过期",
            ]));
        } else if (($user['u'] + $user['d']) >= $user['transfer_enable']) {
            array_unshift($servers, array_merge($servers[0], [
                'name' => "请去往官网重置流量",
            ]));
            array_unshift($servers, array_merge($servers[0], [
                'name' => "您的流量已耗尽",
            ]));
        }

        $serverService = new ServerService();
        $version = $serverService->getSubscriptionVersion($user);
        array_unshift($servers, array_merge($servers[0], [
            'name' => "当前订阅版本：{$version}",
        ]));
    }
}