public function subscribe(Request $request)
    {
        $flag = $request->input('flag')
            ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $flag = strtolower($flag);
        $user = $request->user;
        
        // account not expired and is not banned.
        $userService = new UserService();
        if ($userService->isAvailable($user)) {
            // ... (正常用户的逻辑保持不变) ...
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
            
        } elseif (!$user['banned']) { // === 这里开始是修改的重点 ===
            
            // 1. 初始化 servers 为空数组，确保不包含任何真实节点
            $servers = [];

            // 2. 定义一个通用的模板节点
            // 必须包含协议转换所需的字段，否则 Protocol 类会报错
            // 这里使用 Trojan 协议，因为它在大多数客户端上显示文本比较友好
            $templateServer = [
                'name' => '提示信息',
                'group_name' => '系统通知',
                'server' => '127.0.0.1', // 假 IP
                'port' => 443,
                'type' => 'trojan',      // 类型
                'password' => 'expired', // 随意填写
                'cipher' => 'auto',      // 随意填写
                'tags' => [],
                // 如果你的 v2board 版本较新，可能还需要 udp, allow_insecure 等字段，视情况补充
            ];

            // 3. 计算流量数据
            $useTraffic = $user['u'] + $user['d'];
            $totalTraffic = $user['transfer_enable'];
            $remainingTrafficValue = $totalTraffic - $useTraffic;

            // 4. 开始构建提示节点列表 (注意顺序，后添加的 unshift 会排在最前面)

            // D. 最底部的提示：官网地址
            array_unshift($servers, array_merge($templateServer, [
                'name' => "官网: https://潘多快跑.com",
            ]));

            // C. 引导续费提示
            array_unshift($servers, array_merge($templateServer, [
                'name' => "请去往官网重置流量或续费",
            ]));

            // B. 流量耗尽提示
            if ($remainingTrafficValue <= 0) {
                array_unshift($servers, array_merge($templateServer, [
                    'name' => "套餐流量已用尽",
                ]));
            }

            // A. 过期提示 (最重要，放在最上面)
            if ($user['expired_at'] !== NULL && $user['expired_at'] <= time()) {
                array_unshift($servers, array_merge($templateServer, [
                    'name' => "您的订阅已过期",
                ]));
            }
            
            // 如果没有任何特殊状态（理论上进到这里肯定是有问题的），给一个默认提示
            if (empty($servers)) {
                 array_unshift($servers, array_merge($templateServer, [
                    'name' => "账户状态异常",
                ]));
            }

            // 5. 下发订阅逻辑
            if ($flag) {
                foreach (array_reverse(glob(app_path('Protocols') . '/*.php')) as $file) {
                    $file = 'App\\Protocols\\' . basename($file, '.php');
                    $class = new $file($user, $servers);
                    if (strpos($flag, $class->flag) !== false) {
                        return $class->handle();
                    }
                }
            }
            // 默认 General
            $class = new General($user, $servers);
            return $class->handle();
        }
    }
