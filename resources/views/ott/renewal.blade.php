<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>OTT 续费管理</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        body { background-color: #1a202c; color: #e2e8f0; }
        .modal { background-color: rgba(0, 0, 0, 0.5); }
        .tab-active { border-bottom: 2px solid #3b82f6; color: #3b82f6; }
        .tab-inactive { color: #9ca3af; }
    </style>
</head>
<body>
    <div id="app" class="min-h-screen p-6">
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-white">OTT 续费管理</h1>
                <div class="space-x-4">
                    <a href="/<?php echo config('v2board.secure_path', config('v2board.frontend_admin_path', hash('crc32b', config('app.key')))); ?>/ott" class="text-gray-400 hover:text-white">返回账号列表</a>
                </div>
            </div>

            <!-- Auth Warning -->
            <div v-if="!token" class="bg-red-600 text-white p-4 rounded mb-6">
                ⚠️ 未找到身份令牌。请先登录后台管理面板。
            </div>

            <div v-if="token">
                <!-- Global Controls -->
                <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700 mb-6">
                    <div class="flex items-end gap-4">
                        <div class="w-32">
                            <label class="block text-sm text-gray-400 mb-1">目标年份</label>
                            <input v-model="targetYear" type="number" @change="fetchData" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>
                        <div class="flex-grow">
                            <!-- Tabs -->
                            <div class="flex space-x-6 border-b border-gray-700">
                                <button @click="activeTab = 'settings'" :class="activeTab === 'settings' ? 'tab-active' : 'tab-inactive'" class="pb-2 font-medium transition">
                                    账号配置
                                </button>
                                <button @click="activeTab = 'bills'" :class="activeTab === 'bills' ? 'tab-active' : 'tab-inactive'" class="pb-2 font-medium transition">
                                    账单管理
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Account Settings -->
                <div v-if="activeTab === 'settings'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="acc in accounts" :key="acc.id" class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700">
                        <h3 class="text-xl font-bold text-white mb-2">@{{ acc.name }}</h3>
                        <p class="text-sm text-gray-400 mb-4">@{{ acc.type }}</p>
                        
                        <!-- Statistics -->
                        <div class="mb-4 p-3 bg-gray-900 rounded border border-gray-700">
                            <div class="text-xs text-gray-400 mb-2">@{{ targetYear }} 年续费统计</div>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                                    <span class="text-gray-300">已付款:</span>
                                    <span class="text-green-400 font-bold">@{{ getAccountStats(acc.id).paid }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                    <span class="text-gray-300">未付款:</span>
                                    <span class="text-red-400 font-bold">@{{ getAccountStats(acc.id).unpaid }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-orange-500"></span>
                                    <span class="text-gray-300">要下车:</span>
                                    <span class="text-orange-400 font-bold">@{{ getAccountStats(acc.id).dropped }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                    <span class="text-gray-300">新上车:</span>
                                    <span class="text-blue-400 font-bold">@{{ getAccountStats(acc.id).new }}</span>
                                </div>
                            </div>
                            <div class="mt-2 pt-2 border-t border-gray-700 text-xs">
                                <div class="flex justify-between text-gray-400">
                                    <span>总人数:</span>
                                    <span class="text-white font-bold">@{{ getAccountStats(acc.id).total }}</span>
                                </div>
                                <div class="flex justify-between text-gray-400">
                                    <span>席位数:</span>
                                    <span class="text-white font-bold">@{{ acc.next_shared_seats || acc.shared_seats || 1 }}</span>
                                </div>
                                <div class="flex justify-between text-gray-400 mt-1">
                                    <span>状态:</span>
                                    <span :class="getAccountStats(acc.id).total >= (acc.next_shared_seats || acc.shared_seats || 1) ? 'text-red-400' : 'text-green-400'" class="font-bold">
                                        @{{ getAccountStats(acc.id).total >= (acc.next_shared_seats || acc.shared_seats || 1) ? '已满员' : '可继续售卖' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">下一周期年费</label>
                                <input v-model="acc.next_price_yearly" type="number" class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-white text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">下一周期席位数</label>
                                <input v-model="acc.next_shared_seats" type="number" class="w-full bg-gray-700 border border-gray-600 rounded px-2 py-1 text-white text-sm">
                            </div>
                            <div class="pt-2">
                                <button @click="saveAccountSettings(acc)" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-sm">
                                    保存配置
                                </button>
                            </div>
                            <div class="pt-2 border-t border-gray-700">
                                <button @click="importCurrentUsers(acc)" class="w-full bg-gray-700 hover:bg-gray-600 text-white px-3 py-1.5 rounded text-sm mb-2">
                                    导入当前用户
                                </button>
                                <button @click="openAddUserModal(acc)" class="w-full bg-green-700 hover:bg-green-600 text-white px-3 py-1.5 rounded text-sm">
                                    + 预添加新用户
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Bills Management -->
                <div v-if="activeTab === 'bills'">
                    <div class="bg-gray-800 rounded-lg shadow-lg border border-gray-700 overflow-hidden mb-4">
                        <div class="p-4 border-b border-gray-700 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                            <h3 class="text-lg font-bold text-white">用户账单列表 (@{{ targetYear }})</h3>
                            <div class="text-sm text-gray-400">
                                总计应收: <span class="text-white font-bold">@{{ totalReceivable }}</span> | 
                                已收: <span class="text-green-400 font-bold">@{{ totalReceived }}</span>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm text-gray-300 min-w-full">
                                <thead class="bg-gray-900 text-gray-400 uppercase font-medium">
                                    <tr>
                                        <th class="px-4 py-3">用户邮箱</th>
                                        <th class="px-4 py-3">订阅项目数</th>
                                        <th class="px-4 py-3">总金额</th>
                                        <th class="px-4 py-3">状态</th>
                                        <th class="px-4 py-3 text-right">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-700">
                                    <tr v-for="user in userBills" :key="user.email" class="hover:bg-gray-700">
                                        <td class="px-4 py-3 font-medium text-white">@{{ user.email }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap gap-1 overflow-x-auto">
                                                <span v-for="item in user.items" class="px-1.5 py-0.5 bg-gray-600 rounded text-xs whitespace-nowrap">
                                                    @{{ item.account_name }}
                                                </span>
                                            </div>
                                        </td>
                                    <td class="px-4 py-3 font-bold text-blue-400">@{{ user.total.toFixed(2) }}</td>
                                    <td class="px-4 py-3">
                                        <span :class="user.is_fully_paid ? 'bg-green-900 text-green-300' : 'bg-yellow-900 text-yellow-300'" class="px-2 py-1 rounded text-xs">
                                            @{{ user.is_fully_paid ? '已结清' : '未结清' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-2">
                                        <button @click="showReceipt(user)" class="text-blue-400 hover:text-blue-300">查看小票</button>
                                    </td>
                                </tr>
                                    <tr v-if="userBills.length === 0">
                                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">暂无账单数据。</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add User Modal -->
            <div v-if="showAddUserModal" class="fixed inset-0 modal flex items-center justify-center p-4 z-50 overflow-y-auto">
                <div class="bg-gray-800 rounded-lg w-full max-w-md p-6 border border-gray-700 my-4">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-white">预添加新用户</h2>
                        <button @click="showAddUserModal = false" class="text-gray-400 hover:text-white">✕</button>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">账号</label>
                            <div class="text-white font-medium">@{{ currentAddUserAccount ? currentAddUserAccount.name : '' }}</div>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">用户邮箱</label>
                            <input v-model="addUserForm.email" type="email" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white" placeholder="user@example.com">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">车位/子账号 (可选)</label>
                            <input v-model="addUserForm.sub_account_id" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white" placeholder="例如: Kids">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">PIN码 (可选)</label>
                            <input v-model="addUserForm.sub_account_pin" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white" placeholder="例如: 1234">
                        </div>
                        <div class="pt-4 border-t border-gray-700 flex justify-end space-x-4">
                            <button @click="showAddUserModal = false" class="px-4 py-2 text-gray-400 hover:text-white">取消</button>
                            <button @click="addNewUser" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded">添加</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Receipt Modal -->
            <div v-if="showReceiptModal" class="fixed inset-0 modal flex items-center justify-center p-4 z-50 overflow-y-auto">
                <div class="bg-white text-gray-900 rounded-lg w-full max-w-md p-8 shadow-2xl relative my-4 max-h-[90vh] overflow-y-auto">
                    <button @click="showReceiptModal = false" class="sticky top-0 right-0 float-right text-gray-400 hover:text-gray-600 bg-white z-10 p-2 rounded-full hover:bg-gray-100">✕</button>
                    
                    <div class="text-center border-b-2 border-dashed border-gray-300 pb-6 mb-6">
                        <h2 class="text-2xl font-bold uppercase tracking-widest mb-1">续费账单</h2>
                        <p class="text-sm text-gray-500">年份: @{{ targetYear }}</p>
                    </div>

                    <div class="mb-6">
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-600">用户:</span>
                            <span class="font-bold">@{{ currentReceiptUser.email }}</span>
                        </div>
                    </div>

                    <div class="space-y-3 mb-6">
                        <div v-for="item in currentReceiptUser.items" :key="item.id" class="flex justify-between items-center text-sm" :class="item.is_dropped ? 'opacity-50' : ''">
                            <div>
                                <div class="font-bold">
                                    @{{ item.account_name }}
                                    <span v-if="item.is_dropped" class="text-xs text-gray-500 ml-2">(已下车)</span>
                                    <span v-if="item.is_new && !item.is_dropped" class="text-xs text-blue-500 ml-2">(新上车)</span>
                                </div>
                                <div class="text-xs text-gray-500">@{{ item.sub_account_id || '标准位' }}</div>
                            </div>
                            <div class="text-right">
                                <div v-if="!item.is_dropped">@{{ item.price }}</div>
                                <div v-else class="text-gray-400 line-through">@{{ item.price }}</div>
                                <div class="flex gap-2 justify-end mt-1">
                                    <button v-if="!item.is_paid && !item.is_dropped" @click="markDropped(item)" class="text-xs underline text-orange-600 hover:text-orange-700">
                                        下车
                                    </button>
                                    <button v-if="item.is_dropped" @click="markDropped(item)" class="text-xs underline text-blue-600 hover:text-blue-700">
                                        恢复
                                    </button>
                                    <button @click="togglePaid(item)" class="text-xs underline" :class="item.is_paid ? 'text-green-600' : 'text-red-500'" :disabled="item.is_dropped">
                                        @{{ item.is_paid ? '已付' : '未付' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4 flex justify-between items-center">
                        <span class="text-lg font-bold">总计应付</span>
                        <span class="text-2xl font-bold text-blue-600">@{{ currentReceiptUser.total.toFixed(2) }}</span>
                    </div>
                    
                    <div class="mt-2 text-right text-sm" :class="currentReceiptUser.is_fully_paid ? 'text-green-600' : 'text-red-500'">
                        (@{{ currentReceiptUser.is_fully_paid ? '已全部结清' : '尚未结清' }})
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const { createApp, ref, computed, onMounted } = Vue;

        createApp({
            setup() {
                const token = ref(null);
                const activeTab = ref('settings');
                const targetYear = ref(new Date().getFullYear() + 1);
                const accounts = ref([]);
                const allRenewals = ref([]);
                
                const showReceiptModal = ref(false);
                const currentReceiptUser = ref({});
                
                const showAddUserModal = ref(false);
                const currentAddUserAccount = ref(null);
                const addUserForm = ref({
                    email: '',
                    sub_account_id: '',
                    sub_account_pin: ''
                });

                const api = axios.create({ baseURL: '/api/v1/admin/ott' });

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

                const fetchData = async () => {
                    try {
                        // Fetch accounts
                        const accRes = await api.get('/account/fetch');
                        accounts.value = accRes.data.data;

                        // Fetch all renewals for the year (no account_id filter)
                        const renRes = await api.get('/renewal/fetch', {
                            params: { target_year: targetYear.value }
                        });
                        allRenewals.value = renRes.data.data;
                    } catch (e) {
                        console.error(e);
                        alert('数据加载失败');
                    }
                };

                const userBills = computed(() => {
                    const bills = {};
                    allRenewals.value.forEach(item => {
                        if (!bills[item.user_email]) {
                            bills[item.user_email] = {
                                email: item.user_email,
                                items: [],
                                total: 0,
                                paid_total: 0
                            };
                        }
                        bills[item.user_email].items.push(item);
                        // 只计算未下车的项目
                        if (!item.is_dropped) {
                            bills[item.user_email].total += parseFloat(item.price);
                            if (item.is_paid) {
                                bills[item.user_email].paid_total += parseFloat(item.price);
                            }
                        }
                    });

                    return Object.values(bills).map(bill => ({
                        ...bill,
                        is_fully_paid: bill.total > 0 && bill.paid_total >= bill.total
                    }));
                });

                const totalReceivable = computed(() => {
                    return userBills.value.reduce((sum, user) => sum + user.total, 0).toFixed(2);
                });

                const totalReceived = computed(() => {
                    return userBills.value.reduce((sum, user) => sum + user.paid_total, 0).toFixed(2);
                });

                const getAccountStats = (accountId) => {
                    const accountRenewals = allRenewals.value.filter(r => r.account_id === accountId);
                    return {
                        paid: accountRenewals.filter(r => r.is_paid && !r.is_dropped).length,
                        unpaid: accountRenewals.filter(r => !r.is_paid && !r.is_dropped).length,
                        dropped: accountRenewals.filter(r => r.is_dropped).length,
                        new: accountRenewals.filter(r => r.is_new).length,
                        total: accountRenewals.filter(r => !r.is_dropped).length
                    };
                };

                const saveAccountSettings = async (account) => {
                    try {
                        await api.post('/account/save', account);
                        alert('配置已保存');
                    } catch (e) { alert('保存失败'); }
                };

                const importCurrentUsers = async (account) => {
                    if (!confirm(`确定要导入 ${account.name} 的当前用户到 ${targetYear.value} 年吗？`)) return;
                    try {
                        await api.post('/renewal/import', {
                            account_id: account.id,
                            target_year: targetYear.value
                        });
                        fetchData();
                        alert('导入成功');
                    } catch (e) { alert('导入失败'); }
                };

                const openAddUserModal = (account) => {
                    currentAddUserAccount.value = account;
                    addUserForm.value = {
                        email: '',
                        sub_account_id: '',
                        sub_account_pin: ''
                    };
                    showAddUserModal.value = true;
                };

                const addNewUser = async () => {
                    if (!addUserForm.value.email) {
                        alert('请填写用户邮箱');
                        return;
                    }
                    try {
                        await api.post('/renewal/add-user', {
                            account_id: currentAddUserAccount.value.id,
                            target_year: targetYear.value,
                            user_email: addUserForm.value.email,
                            sub_account_id: addUserForm.value.sub_account_id,
                            sub_account_pin: addUserForm.value.sub_account_pin
                        });
                        showAddUserModal.value = false;
                        fetchData();
                        alert('添加成功');
                    } catch (e) {
                        console.error(e);
                        alert('添加失败: ' + (e.response?.data?.message || e.message));
                    }
                };


                const showReceipt = (user) => {
                    currentReceiptUser.value = user;
                    showReceiptModal.value = true;
                };

                const togglePaid = async (item) => {
                    if (item.is_dropped) return; // 已下车的项目不能切换付款状态
                    try {
                        await api.post('/renewal/save', {
                            id: item.id,
                            account_id: item.account_id,
                            target_year: targetYear.value,
                            user_email: item.user_email,
                            price: item.price,
                            is_paid: !item.is_paid,
                            is_dropped: item.is_dropped || false,
                            sub_account_id: item.sub_account_id,
                            sub_account_pin: item.sub_account_pin
                        });
                        
                        // Update local state immediately
                        const renewal = allRenewals.value.find(r => r.id === item.id);
                        if (renewal) {
                            renewal.is_paid = !renewal.is_paid;
                        }
                        // Refresh current user object
                        const updatedUser = userBills.value.find(u => u.email === item.user_email);
                        if (updatedUser) {
                            currentReceiptUser.value = updatedUser;
                        }

                    } catch (e) { 
                        console.error(e);
                        alert('状态更新失败'); 
                    }
                };

                const markDropped = async (item) => {
                    const action = item.is_dropped ? '恢复' : '下车';
                    if (!confirm(`确定要将 ${item.account_name} ${action}吗？`)) return;
                    try {
                        const res = await api.post('/renewal/mark-dropped', {
                            id: item.id,
                            is_dropped: !item.is_dropped
                        });
                        
                        // Update local state immediately
                        const renewal = allRenewals.value.find(r => r.id === item.id);
                        if (renewal) {
                            renewal.is_dropped = !renewal.is_dropped;
                            if (renewal.is_dropped) {
                                renewal.is_paid = false; // 下车时自动设为未付款
                            }
                        }
                        // Refresh current user object
                        const updatedUser = userBills.value.find(u => u.email === item.user_email);
                        if (updatedUser) {
                            currentReceiptUser.value = updatedUser;
                        }
                    } catch (e) {
                        console.error(e);
                        alert(`${action}失败: ` + (e.response?.data?.message || e.message));
                    }
                };

                onMounted(() => {
                    token.value = findToken();
                    if (token.value) {
                        api.defaults.headers.common['Authorization'] = token.value;
                        fetchData();
                    }
                });

                return {
                    token, activeTab, targetYear, accounts, 
                    userBills, totalReceivable, totalReceived,
                    showReceiptModal, currentReceiptUser,
                    showAddUserModal, currentAddUserAccount, addUserForm,
                    fetchData, saveAccountSettings, importCurrentUsers, 
                    openAddUserModal, addNewUser,
                    showReceipt, togglePaid, markDropped, getAccountStats
                };
            }
        }).mount('#app');
    </script>
</body>
</html>
