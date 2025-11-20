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
        // account not expired and is not banned.
        $userService = new UserService();
        if ($userService->isAvailable($user)) {
            $serverService = new ServerService();
            $servers = $serverService->getAvailableServers($user);
            if ($flag) {
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
        } elseif (!$user['banned']) { // 这里是正确的位置
            $serverService = new ServerService();
            $servers = $serverService->getAvailableServers($user);

            // 如果账户不可用，通常 getAvailableServers 可能返回空，需要处理这种情况以避免报错
            // 这里假设即使不可用也能获取到服务器列表用于展示提示节点。
            // 如果 $servers 为空，下面的 array_merge($servers[0], ...) 会报错。
            // 建议添加一个基本的检查，或者构造一个虚拟的服务器节点用于承载提示信息。
            if (empty($servers)) {
                 // 构造一个假的 server 数组结构，避免 $servers[0] 不存在导致的错误
                 // 具体的结构需要参考你的 ServerService 返回的真实结构
                 $servers = [['name' => '提示', 'server' => '127.0.0.1', 'port' => 0, 'protocol' => 'trojan']]; 
            }


            $useTraffic = $user['u'] + $user['d'];
            $totalTraffic = $user['transfer_enable'];
            // 注意：Helper::trafficConvert 可能返回带有单位的字符串，比较时可能需要注意
            $remainingTrafficValue = $totalTraffic - $useTraffic;
            
            array_unshift($servers, array_merge($servers[0], [
                'name' => "https://潘多快跑.com",
            ]));
            array_unshift($servers, array_merge($servers[0], [
                'name' => "请去往官网重置流量或续费",
            ]));
            if ($remainingTrafficValue <= 0) {
                array_unshift($servers, array_merge($servers[0], [
                    'name' => "您的流量已用尽",
                ]));
            }

            if ($user['expired_at'] !== NULL && $user['expired_at'] <= time()) {
                 array_unshift($servers, array_merge($servers[0], [
                    'name' => "您的订阅已过期",
                ]));
            }

            if ($flag) {
                foreach (array_reverse(glob(app_path('Protocols') . '/*.php')) as $file) {
                    $file = 'App\\Protocols\\' . basename($file, '.php');
                    $class = new $file($user, $servers);
                    if (strpos($flag, $class->flag) !== false) {
                        return $class->handle();
                    }
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
