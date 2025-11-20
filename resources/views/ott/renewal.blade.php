<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                                <button @click="importCurrentUsers(acc)" class="w-full bg-gray-700 hover:bg-gray-600 text-white px-3 py-1.5 rounded text-sm">
                                    导入当前用户
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Bills Management -->
                <div v-if="activeTab === 'bills'">
                    <div class="bg-gray-800 rounded-lg shadow-lg border border-gray-700 overflow-hidden">
                        <div class="p-4 border-b border-gray-700 flex justify-between items-center">
                            <h3 class="text-lg font-bold text-white">用户账单列表 (@{{ targetYear }})</h3>
                            <div class="text-sm text-gray-400">
                                总计应收: <span class="text-white font-bold">@{{ totalReceivable }}</span> | 
                                已收: <span class="text-green-400 font-bold">@{{ totalReceived }}</span>
                            </div>
                        </div>
                        <table class="w-full text-left text-sm text-gray-300">
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
                                        <div class="flex flex-wrap gap-1">
                                            <span v-for="item in user.items" class="px-1.5 py-0.5 bg-gray-600 rounded text-xs">
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

            <!-- Receipt Modal -->
            <div v-if="showReceiptModal" class="fixed inset-0 modal flex items-center justify-center p-4 z-50">
                <div class="bg-white text-gray-900 rounded-lg w-full max-w-md p-8 shadow-2xl relative">
                    <button @click="showReceiptModal = false" class="absolute top-2 right-2 text-gray-400 hover:text-gray-600">✕</button>
                    
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
                        <div v-for="item in currentReceiptUser.items" :key="item.id" class="flex justify-between items-center text-sm">
                            <div>
                                <div class="font-bold">@{{ item.account_name }}</div>
                                <div class="text-xs text-gray-500">@{{ item.sub_account_id || '标准位' }}</div>
                            </div>
                            <div class="text-right">
                                <div>@{{ item.price }}</div>
                                <button @click="togglePaid(item)" class="text-xs underline" :class="item.is_paid ? 'text-green-600' : 'text-red-500'">
                                    @{{ item.is_paid ? '已付' : '未付' }}
                                </button>
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
                        bills[item.user_email].total += parseFloat(item.price);
                        if (item.is_paid) {
                            bills[item.user_email].paid_total += parseFloat(item.price);
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

                const showReceipt = (user) => {
                    currentReceiptUser.value = user;
                    showReceiptModal.value = true;
                };

                const togglePaid = async (item) => {
                    try {
                        await api.post('/renewal/save', {
                            id: item.id, // Assuming save supports update by ID or we send full payload
                            account_id: item.account_id,
                            target_year: targetYear.value,
                            user_email: item.user_email,
                            price: item.price,
                            is_paid: !item.is_paid,
                            sub_account_id: item.sub_account_id,
                            sub_account_pin: item.sub_account_pin
                        });
                        
                        // Update local state immediately
                        const renewal = allRenewals.value.find(r => r.id === item.id);
                        if (renewal) {
                            renewal.is_paid = !renewal.is_paid;
                        }
                        // Force re-compute of current user for modal update
                        // (Since currentReceiptUser is a ref to the computed object, we might need to refresh it)
                        // Actually, since we modified the source `allRenewals`, the computed `userBills` will update,
                        // but `currentReceiptUser` holds a reference to the OLD object from the list.
                        // We need to find the updated user object.
                        const updatedUser = userBills.value.find(u => u.email === item.user_email);
                        if (updatedUser) {
                            currentReceiptUser.value = updatedUser;
                        }

                    } catch (e) { 
                        console.error(e);
                        alert('状态更新失败'); 
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
                    fetchData, saveAccountSettings, importCurrentUsers, showReceipt, togglePaid
                };
            }
        }).mount('#app');
    </script>
</body>
</html>
