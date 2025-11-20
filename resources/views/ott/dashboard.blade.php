<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTT 账号管理</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        body { background-color: #1a202c; color: #e2e8f0; }
        .modal { background-color: rgba(0, 0, 0, 0.5); }
    </style>
</head>
<body>
    <div id="app" class="min-h-screen p-6">
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-white">OTT 账号管理</h1>
                <div class="space-x-4">
                    <a href="/<?php echo config('v2board.secure_path', config('v2board.frontend_admin_path', hash('crc32b', config('app.key')))); ?>/ott/renewal" class="text-gray-400 hover:text-white">续费管理</a>
                </div>
            </div>

            <!-- Auth Warning -->
            <div v-if="!token" class="bg-red-600 text-white p-4 rounded mb-6">
                ⚠️ 未找到身份令牌。请先登录后台管理面板，然后刷新此页面。
            </div>

            <!-- Accounts List -->
            <div v-if="token">
                <div class="flex justify-end mb-4">
                    <button @click="openAccountModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                        + 添加账号
                    </button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="account in accounts" :key="account.id" class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700 flex flex-col">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-xl font-bold text-white">@{{ account.name }}</h3>
                                <span class="text-sm text-gray-400 bg-gray-900 px-2 py-1 rounded">@{{ account.type }}</span>
                            </div>
                            <div class="flex space-x-2">
                                <button @click="openAccountModal(account)" class="text-blue-400 hover:text-blue-300">编辑</button>
                                <button @click="deleteAccount(account.id)" class="text-red-400 hover:text-red-300">删除</button>
                            </div>
                        </div>
                        <div class="space-y-2 text-sm text-gray-300 flex-grow">
                            <p><span class="text-gray-500">用户名:</span> @{{ account.username }}</p>
                            <p><span class="text-gray-500">OTP:</span> @{{ account.has_otp ? 'Yes' : 'No' }}</p>
                            <p><span class="text-gray-500">共享:</span> @{{ account.is_shared_credentials ? 'Yes' : 'No' }}</p>
                            <p><span class="text-gray-500">席位:</span> @{{ account.shared_seats }}</p>
                            <p><span class="text-gray-500">每用户年费:</span> <span class="text-yellow-400">@{{ calculateCost(account) }}</span></p>
                            <p><span class="text-gray-500">状态:</span> 
                                <span :class="account.is_active ? 'text-green-400' : 'text-red-400'">
                                    @{{ account.is_active ? '启用' : '停用' }}
                                </span>
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-gray-700">
                            <div class="flex gap-2">
                                <button @click="openUsersModal(account)" class="flex-1 bg-gray-700 hover:bg-gray-600 text-white py-2 rounded transition flex justify-center items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                    </svg>
                                    管理用户
                                </button>
                                <button @click="openLogsModal(account)" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded transition" title="调试日志">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Modal -->
            <div v-if="showAccountModal" class="fixed inset-0 modal flex items-center justify-center p-4 z-50">
                <div class="bg-gray-800 rounded-lg w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 border border-gray-700">
                    <h2 class="text-2xl font-bold text-white mb-6">@{{ editingAccount ? '编辑账号' : '新建账号' }}</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm text-gray-400 mb-1">名称</label>
                            <input v-model="accountForm.name" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">类型</label>
                            <input v-model="accountForm.type" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white" placeholder="例如: netflix">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">用户组 ID (可选)</label>
                            <input v-model="accountForm.group_id" type="number" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Username</label>
                            <input v-model="accountForm.username" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">密码</label>
                            <input v-model="accountForm.password" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>
                        
                        <div class="col-span-2 border-t border-gray-700 pt-4 mt-2">
                            <h3 class="text-lg font-semibold text-white mb-3">邮件和OTP设置</h3>
                        </div>

                        <div>
                            <label class="block text-sm text-gray-400 mb-1">发件人过滤 (正则)</label>
                            <input v-model="accountForm.sender_filter" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white" placeholder="例如: /netflix/i">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">收件人过滤 (正则)</label>
                            <input v-model="accountForm.recipient_filter" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm text-gray-400 mb-1">主题/内容 OTP 正则</label>
                            <input v-model="accountForm.subject_regex" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white" placeholder="/code is (\d+)/">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm text-gray-400 mb-1">忽略正则 (匹配时不存储)</label>
                            <input v-model="accountForm.ignore_regex" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white" placeholder="例如: /重置密码/i">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">OTP 有效期 (分钟)</label>
                            <input v-model="accountForm.otp_validity_minutes" type="number" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>

                        <div class="col-span-2 border-t border-gray-700 pt-4 mt-2">
                            <h3 class="text-lg font-semibold text-white mb-3">价格与席位</h3>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">月费 (总计)</label>
                            <input v-model="accountForm.price_monthly" type="number" step="0.01" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">年费 (总计)</label>
                            <input v-model="accountForm.price_yearly" type="number" step="0.01" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">共享席位数</label>
                            <input v-model="accountForm.shared_seats" type="number" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>
                        <div class="flex items-end pb-2">
                            <span class="text-gray-400 text-sm">预估单人年费成本: <span class="text-yellow-400 font-bold">@{{ calculateFormCost() }}</span></span>
                        </div>

                        <div class="col-span-2 flex gap-6 mt-2 border-t border-gray-700 pt-4">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" v-model="accountForm.has_otp" class="form-checkbox text-blue-600">
                                <span class="text-white">需要验证码</span>
                            </label>
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" v-model="accountForm.is_shared_credentials" class="form-checkbox text-blue-600">
                                <span class="text-white">共享账号密码</span>
                            </label>
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" v-model="accountForm.is_active" class="form-checkbox text-green-600">
                                <span class="text-white">启用</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4 mt-8">
                        <button @click="showAccountModal = false" class="px-4 py-2 text-gray-400 hover:text-white">取消</button>
                        <button @click="saveAccount" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">保存</button>
                    </div>
                </div>
            </div>

            <!-- Users Modal -->
            <div v-if="showUsersModal" class="fixed inset-0 modal flex items-center justify-center p-4 z-50">
                <div class="bg-gray-800 rounded-lg w-full max-w-3xl max-h-[90vh] overflow-y-auto p-6 border border-gray-700">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-white">管理用户绑定: @{{ currentAccount ? currentAccount.name : '' }}</h2>
                        <button @click="showUsersModal = false" class="text-gray-400 hover:text-white">✕</button>
                    </div>

                    <!-- Add User Form -->
                    <div class="bg-gray-700 p-4 rounded mb-6">
                        <h3 class="text-lg font-semibold text-white mb-3">添加/更新用户绑定</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div class="col-span-1 md:col-span-2">
                                <input v-model="bindForm.email" type="email" class="w-full bg-gray-600 border border-gray-500 rounded px-3 py-2 text-white text-sm" placeholder="绑定邮箱">
                            </div>
                            <div>
                                <input v-model="bindForm.expired_at" type="date" class="w-full bg-gray-600 border border-gray-500 rounded px-3 py-2 text-white text-sm">
                            </div>
                            <div class="row-start-2 col-span-1 md:col-span-2">
                                <input v-model="bindForm.sub_account_id" class="w-full bg-gray-600 border border-gray-500 rounded px-3 py-2 text-white text-sm" placeholder="车位名称 (如: Kids)">
                            </div>
                            <div class="row-start-2">
                                <input v-model="bindForm.sub_account_pin" class="w-full bg-gray-600 border border-gray-500 rounded px-3 py-2 text-white text-sm" placeholder="PIN码 (例如: 1234)">
                            </div>
                            <div class="row-start-2">
                                <button @click="bindUser" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm">绑定</button>
                            </div>
                        </div>
                    </div>

                    <!-- Users List -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-300">
                            <thead class="bg-gray-900 text-gray-400 uppercase font-medium">
                                <tr>
                                    <th class="px-4 py-3">绑定邮箱</th>
                                    <th class="px-4 py-3">车位/子账号</th>
                                    <th class="px-4 py-3">PIN</th>
                                    <th class="px-4 py-3">过期时间</th>
                                    <th class="px-4 py-3 text-right">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <tr v-for="user in accountUsers" :key="user.id" class="hover:bg-gray-700">
                                    <td class="px-4 py-3">@{{ user.user_email }}</td>
                                    <td class="px-4 py-3">@{{ user.sub_account_id || '-' }}</td>
                                    <td class="px-4 py-3">@{{ user.sub_account_pin || '-' }}</td>
                                    <td class="px-4 py-3" :class="isExpired(user.expired_at) ? 'text-red-400' : 'text-green-400'">
                                        @{{ formatDate(user.expired_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-2">
                                        <button @click="editUser(user)" class="text-blue-400 hover:text-blue-300">编辑</button>
                                        <button @click="unbindUser(user.user_id)" class="text-red-400 hover:text-red-300">解绑</button>
                                    </td>
                                </tr>
                                <tr v-if="accountUsers.length === 0">
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">此账号暂无绑定用户。</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
            <!-- Logs Modal -->
            <div v-if="showLogsModal" class="fixed inset-0 modal flex items-center justify-center p-4 z-50">
                <div class="bg-gray-800 rounded-lg w-full max-w-4xl max-h-[90vh] overflow-y-auto p-6 border border-gray-700">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-white">调试日志: @{{ currentAccount ? currentAccount.name : '' }}</h2>
                        <button @click="showLogsModal = false" class="text-gray-400 hover:text-white">✕</button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-300">
                            <thead class="bg-gray-900 text-gray-400 uppercase font-medium">
                                <tr>
                                    <th class="px-4 py-3">时间</th>
                                    <th class="px-4 py-3">类型</th>
                                    <th class="px-4 py-3">状态</th>
                                    <th class="px-4 py-3">消息</th>
                                    <th class="px-4 py-3">详情</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <tr v-for="log in accountLogs" :key="log.id" class="hover:bg-gray-700">
                                    <td class="px-4 py-3 whitespace-nowrap">@{{ new Date(log.created_at).toLocaleString() }}</td>
                                    <td class="px-4 py-3">@{{ log.type }}</td>
                                    <td class="px-4 py-3">
                                        <span :class="log.status ? 'text-green-400' : 'text-red-400'">
                                            @{{ log.status ? '成功' : '失败/忽略' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">@{{ log.message }}</td>
                                    <td class="px-4 py-3 max-w-xs truncate">
                                        <div v-if="log.data && log.data.content_preview && log.data.content_preview.startsWith('http')">
                                            <a :href="log.data.content_preview" target="_blank" class="text-blue-400 hover:underline">点击打开链接</a>
                                        </div>
                                        <div v-else :title="JSON.stringify(log.data)">
                                            @{{ log.data ? JSON.stringify(log.data) : '-' }}
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="accountLogs.length === 0">
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">暂无日志记录。</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

    <script>
        const { createApp, ref, onMounted } = Vue;

        createApp({
            setup() {
                const token = ref(null);
                const accounts = ref([]);
                const showAccountModal = ref(false);
                const showUsersModal = ref(false);
                const showLogsModal = ref(false);
                const editingAccount = ref(null);
                const currentAccount = ref(null);
                const accountUsers = ref([]);
                const accountLogs = ref([]);
                
                const accountForm = ref({
                    name: '', type: 'Netflix', username: '', password: '', 
                    has_otp: false, is_shared_credentials: true, is_active: true,
                    sender_filter: '', recipient_filter: '', subject_regex: '',
                    ignore_regex: '', otp_validity_minutes: 0, group_id: null,
                    price_monthly: 0, price_yearly: 0, shared_seats: 1
                });

                const bindForm = ref({
                    email: '', account_id: '', expired_at: '', sub_account_id: '', sub_account_pin: ''
                });

                const findToken = () => {
                    let foundToken = localStorage.getItem('token');
                    if (!foundToken) {
                        for (let i = 0; i < localStorage.length; i++) {
                            const key = localStorage.key(i);
                            const val = localStorage.getItem(key);
                            if (val && val.startsWith('eyJ')) {
                                foundToken = val;
                                break;
                            }
                        }
                    }
                    return foundToken;
                };

                const api = axios.create({
                    baseURL: '/api/v1/admin/ott',
                });

                const fetchAccounts = async () => {
                    try {
                        const res = await api.get('/account/fetch');
                        accounts.value = res.data.data;
                    } catch (e) {
                        console.error(e);
                        alert('获取账号列表失败');
                    }
                };

                const fetchAccountUsers = async (accountId) => {
                    try {
                        const res = await api.get('/user/fetch', { params: { account_id: accountId } });
                        accountUsers.value = res.data.data;
                    } catch (e) {
                        console.error(e);
                        alert('获取用户列表失败');
                    }
                };

                const fetchAccountLogs = async (accountId) => {
                    try {
                        const res = await api.post('/account/logs', { account_id: accountId });
                        accountLogs.value = res.data.data;
                    } catch (e) {
                        console.error(e);
                        alert('获取日志失败');
                    }
                };

                const openAccountModal = (account = null) => {
                    if (account) {
                        editingAccount.value = account;
                        accountForm.value = { ...account };
                    } else {
                        editingAccount.value = null;
                        accountForm.value = {
                            name: '', type: 'Netflix', username: '', password: '', 
                            has_otp: false, is_shared_credentials: true, is_active: true,
                            sender_filter: '', recipient_filter: '', subject_regex: '',
                            ignore_regex: '', otp_validity_minutes: 0, group_id: null,
                            price_monthly: 0, price_yearly: 0, shared_seats: 1
                        };
                    }
                    showAccountModal.value = true;
                };

                const openUsersModal = (account) => {
                    currentAccount.value = account;
                    bindForm.value = { email: '', account_id: account.id, expired_at: '', sub_account_id: '', sub_account_pin: '' };
                    fetchAccountUsers(account.id);
                    showUsersModal.value = true;
                };

                const openLogsModal = (account) => {
                    currentAccount.value = account;
                    fetchAccountLogs(account.id);
                    showLogsModal.value = true;
                };

                const saveAccount = async () => {
                    try {
                        const payload = { ...accountForm.value };
                        if (editingAccount.value) {
                            payload.id = editingAccount.value.id;
                        }
                        await api.post('/account/save', payload);
                        showAccountModal.value = false;
                        fetchAccounts();
                    } catch (e) {
                        console.error(e);
                        alert('保存账号失败');
                    }
                };

                const deleteAccount = async (id) => {
                    if (!confirm('确定要删除吗？')) return;
                    try {
                        await api.post('/account/drop', { id });
                        fetchAccounts();
                    } catch (e) {
                        console.error(e);
                        alert('删除账号失败');
                    }
                };

                const bindUser = async () => {
                    if (!bindForm.value.email || !bindForm.value.expired_at) {
                        alert('请填写邮箱和过期时间');
                        return;
                    }
                    try {
                        const timestamp = new Date(bindForm.value.expired_at).getTime() / 1000;
                        await api.post('/user/bind', {
                            email: bindForm.value.email,
                            account_id: currentAccount.value.id,
                            expired_at: timestamp,
                            sub_account_id: bindForm.value.sub_account_id,
                            sub_account_pin: bindForm.value.sub_account_pin
                        });
                        // Refresh list
                        fetchAccountUsers(currentAccount.value.id);
                        // Clear form but keep account_id
                        bindForm.value.email = '';
                        bindForm.value.sub_account_id = '';
                        bindForm.value.sub_account_pin = '';
                    } catch (e) {
                        console.error(e);
                        alert('绑定用户失败: ' + (e.response && e.response.data && e.response.data.message ? e.response.data.message : e.message));
                    }
                };

                const unbindUser = async (userId) => {
                    if (!confirm('确定要解绑此用户吗？')) return;
                    try {
                        await api.post('/user/unbind', {
                            user_id: userId,
                            account_id: currentAccount.value.id
                        });
                        fetchAccountUsers(currentAccount.value.id);
                    } catch (e) {
                        console.error(e);
                        alert('解绑用户失败');
                    }
                };

                const editUser = (user) => {
                    bindForm.value.email = user.user_email;
                    // Convert timestamp to YYYY-MM-DD
                    const date = new Date(user.expired_at * 1000);
                    bindForm.value.expired_at = date.toISOString().split('T')[0];
                    bindForm.value.sub_account_id = user.sub_account_id;
                    bindForm.value.sub_account_pin = user.sub_account_pin;
                };

                const formatDate = (ts) => {
                    return new Date(ts * 1000).toLocaleDateString();
                };

                const isExpired = (ts) => {
                    return ts * 1000 < Date.now();
                };

                const calculateCost = (acc) => {
                    let yearly = acc.price_yearly ? parseFloat(acc.price_yearly) : (acc.price_monthly ? parseFloat(acc.price_monthly) * 12 : 0);
                    let seats = acc.shared_seats > 0 ? parseInt(acc.shared_seats) : 1;
                    return (yearly / seats).toFixed(2);
                };

                const calculateFormCost = () => {
                    let yearly = accountForm.value.price_yearly ? parseFloat(accountForm.value.price_yearly) : (accountForm.value.price_monthly ? parseFloat(accountForm.value.price_monthly) * 12 : 0);
                    let seats = accountForm.value.shared_seats > 0 ? parseInt(accountForm.value.shared_seats) : 1;
                    return (yearly / seats).toFixed(2);
                };

                onMounted(() => {
                    token.value = findToken();
                    if (token.value) {
                        api.defaults.headers.common['Authorization'] = token.value;
                        fetchAccounts();
                    }
                });

                return {
                    token, accounts, showAccountModal, showUsersModal, showLogsModal, editingAccount, currentAccount, accountUsers, accountLogs,
                    accountForm, bindForm,
                    openAccountModal, openUsersModal, openLogsModal, saveAccount, deleteAccount, bindUser, unbindUser, editUser,
                    formatDate, isExpired, calculateCost, calculateFormCost
                };
            }
        }).mount('#app');
    </script>
</body>
</html>
