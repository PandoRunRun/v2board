<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTT Renewal Management - V2Board</title>
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
                <h1 class="text-3xl font-bold text-white">OTT Renewal Management</h1>
                <div class="space-x-4">
                    <a href="/admin/ott" class="text-gray-400 hover:text-white">Back to Accounts</a>
                </div>
            </div>

            <!-- Auth Warning -->
            <div v-if="!token" class="bg-red-600 text-white p-4 rounded mb-6">
                ⚠️ Authentication Token not found. Please log in to the main Admin Panel first.
            </div>

            <div v-if="token">
                <!-- Account Selector -->
                <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Select Account</label>
                            <select v-model="selectedAccountId" @change="fetchRenewals" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                                <option v-for="acc in accounts" :value="acc.id">{{ acc.name }} ({{ acc.type }})</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Target Year</label>
                            <input v-model="targetYear" type="number" @change="fetchRenewals" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>
                        <div>
                            <button @click="importCurrentUsers" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded w-full">
                                Import Current Users
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats & Settings -->
                <div v-if="selectedAccount" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700">
                        <h3 class="text-xl font-bold text-white mb-4">Next Cycle Settings ({{ targetYear }})</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Next Yearly Price</label>
                                <input v-model="selectedAccount.next_price_yearly" type="number" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Next Shared Seats</label>
                                <input v-model="selectedAccount.next_shared_seats" type="number" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                            </div>
                            <div class="col-span-2">
                                <button @click="saveAccountSettings" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded w-full">
                                    Save Settings
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700">
                        <h3 class="text-xl font-bold text-white mb-4">Statistics</h3>
                        <div class="grid grid-cols-2 gap-4 text-center">
                            <div class="bg-gray-700 p-3 rounded">
                                <div class="text-gray-400 text-sm">Total Seats</div>
                                <div class="text-2xl font-bold">{{ selectedAccount.next_shared_seats || 1 }}</div>
                            </div>
                            <div class="bg-gray-700 p-3 rounded">
                                <div class="text-gray-400 text-sm">Occupied</div>
                                <div class="text-2xl font-bold text-blue-400">{{ renewals.length }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Renewal List -->
                <div v-if="selectedAccount" class="bg-gray-800 rounded-lg shadow-lg border border-gray-700 overflow-hidden">
                    <div class="p-4 border-b border-gray-700 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-white">Renewal List</h3>
                        <button @click="openAddModal" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">
                            + Add User
                        </button>
                    </div>
                    <table class="w-full text-left text-sm text-gray-300">
                        <thead class="bg-gray-900 text-gray-400 uppercase font-medium">
                            <tr>
                                <th class="px-4 py-3">User Email</th>
                                <th class="px-4 py-3">Profile</th>
                                <th class="px-4 py-3">PIN</th>
                                <th class="px-4 py-3">Price</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <tr v-for="item in renewals" :key="item.id" class="hover:bg-gray-700">
                                <td class="px-4 py-3">{{ item.user_email }}</td>
                                <td class="px-4 py-3">{{ item.sub_account_id || '-' }}</td>
                                <td class="px-4 py-3">{{ item.sub_account_pin || '-' }}</td>
                                <td class="px-4 py-3">{{ item.price }}</td>
                                <td class="px-4 py-3">
                                    <span :class="item.is_paid ? 'bg-green-900 text-green-300' : 'bg-red-900 text-red-300'" class="px-2 py-1 rounded text-xs">
                                        {{ item.is_paid ? 'PAID' : 'UNPAID' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <button @click="showReceipt(item)" class="text-yellow-400 hover:text-yellow-300">Receipt</button>
                                    <button @click="togglePaid(item)" class="text-blue-400 hover:text-blue-300">
                                        {{ item.is_paid ? 'Mark Unpaid' : 'Mark Paid' }}
                                    </button>
                                    <button @click="editRenewal(item)" class="text-gray-400 hover:text-white">Edit</button>
                                    <button @click="deleteRenewal(item.id)" class="text-red-400 hover:text-red-300">Remove</button>
                                </td>
                            </tr>
                            <tr v-if="renewals.length === 0">
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">No renewals found for this year.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add/Edit Modal -->
            <div v-if="showModal" class="fixed inset-0 modal flex items-center justify-center p-4 z-50">
                <div class="bg-gray-800 rounded-lg w-full max-w-md p-6 border border-gray-700">
                    <h2 class="text-xl font-bold text-white mb-4">{{ editingItem ? 'Edit Renewal' : 'Add Renewal User' }}</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">User Email</label>
                            <input v-model="form.user_email" :disabled="!!editingItem" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Price</label>
                            <input v-model="form.price" type="number" step="0.01" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Profile</label>
                                <input v-model="form.sub_account_id" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">PIN</label>
                                <input v-model="form.sub_account_pin" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <input type="checkbox" v-model="form.is_paid" class="form-checkbox text-green-600">
                            <span class="text-white">Is Paid</span>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-4 mt-6">
                        <button @click="showModal = false" class="px-4 py-2 text-gray-400 hover:text-white">Cancel</button>
                        <button @click="saveRenewal" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">Save</button>
                    </div>
                </div>
            </div>

            <!-- Receipt Modal -->
            <div v-if="showReceiptModal" class="fixed inset-0 modal flex items-center justify-center p-4 z-50">
                <div class="bg-white text-gray-900 rounded-lg w-full max-w-sm p-8 shadow-2xl relative">
                    <button @click="showReceiptModal = false" class="absolute top-2 right-2 text-gray-400 hover:text-gray-600">✕</button>
                    
                    <div class="text-center border-b-2 border-dashed border-gray-300 pb-6 mb-6">
                        <h2 class="text-2xl font-bold uppercase tracking-widest mb-1">Renewal Receipt</h2>
                        <p class="text-sm text-gray-500">{{ selectedAccount?.name }}</p>
                    </div>

                    <div class="space-y-4 mb-8">
                        <div class="flex justify-between">
                            <span class="text-gray-600">User</span>
                            <span class="font-bold">{{ receiptItem.user_email }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Cycle</span>
                            <span class="font-bold">{{ targetYear }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Profile</span>
                            <span class="font-bold">{{ receiptItem.sub_account_id || '-' }}</span>
                        </div>
                        <div class="border-t border-gray-200 my-2 pt-2 flex justify-between items-center">
                            <span class="text-lg font-bold">Total Due</span>
                            <span class="text-2xl font-bold text-blue-600">{{ receiptItem.price }}</span>
                        </div>
                    </div>

                    <div class="text-center">
                        <div class="inline-block px-4 py-1 rounded-full text-sm font-bold uppercase tracking-wide"
                             :class="receiptItem.is_paid ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'">
                            {{ receiptItem.is_paid ? 'PAID' : 'UNPAID' }}
                        </div>
                        <p class="text-xs text-gray-400 mt-4">Generated by V2Board OTT System</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const { createApp, ref, computed, onMounted, watch } = Vue;

        createApp({
            setup() {
                const token = ref(null);
                const accounts = ref([]);
                const selectedAccountId = ref(null);
                const targetYear = ref(new Date().getFullYear() + 1);
                const renewals = ref([]);
                const showModal = ref(false);
                const showReceiptModal = ref(false);
                const editingItem = ref(null);
                const receiptItem = ref({});
                
                const form = ref({
                    user_email: '', price: '', is_paid: false, sub_account_id: '', sub_account_pin: ''
                });

                const api = axios.create({ baseURL: '/api/v1/admin/ott' });

                const selectedAccount = computed(() => {
                    return accounts.value.find(a => a.id === selectedAccountId.value);
                });

                const availableSeats = computed(() => {
                    if (!selectedAccount.value) return 0;
                    const total = selectedAccount.value.next_shared_seats || selectedAccount.value.shared_seats || 1;
                    return total - renewals.value.length;
                });

                const totalRevenue = computed(() => {
                    return renewals.value.reduce((sum, item) => sum + parseFloat(item.price), 0).toFixed(2);
                });

                const findToken = () => {
                    let foundToken = localStorage.getItem('token');
                    if (!foundToken) {
                        // Try to find any key starting with eyJ
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

                const fetchAccounts = async () => {
                    try {
                        const res = await api.get('/account/fetch');
                        accounts.value = res.data.data;
                        if (accounts.value.length > 0 && !selectedAccountId.value) {
                            selectedAccountId.value = accounts.value[0].id;
                            fetchRenewals();
                        }
                    } catch (e) { console.error(e); }
                };

                const fetchRenewals = async () => {
                    if (!selectedAccountId.value) return;
                    try {
                        const res = await api.get('/renewal/fetch', {
                            params: { account_id: selectedAccountId.value, target_year: targetYear.value }
                        });
                        renewals.value = res.data.data;
                    } catch (e) { console.error(e); }
                };

                const saveAccountSettings = async () => {
                    if (!selectedAccount.value) return;
                    try {
                        await api.post('/account/save', selectedAccount.value);
                        alert('Settings saved!');
                    } catch (e) { alert('Failed to save settings'); }
                };

                const importCurrentUsers = async () => {
                    if (!confirm('Import current users to ' + targetYear.value + '? This will skip existing ones.')) return;
                    try {
                        await api.post('/renewal/import', {
                            account_id: selectedAccountId.value,
                            target_year: targetYear.value
                        });
                        fetchRenewals();
                    } catch (e) { alert('Import failed'); }
                };

                const openAddModal = () => {
                    editingItem.value = null;
                    // Auto-calc price
                    const acc = selectedAccount.value;
                    const price = acc.next_price_yearly ? (acc.next_price_yearly / (acc.next_shared_seats || 1)) : 0;
                    
                    form.value = {
                        user_email: '',
                        price: price.toFixed(2),
                        is_paid: false,
                        sub_account_id: '',
                        sub_account_pin: ''
                    };
                    showModal.value = true;
                };

                const editRenewal = (item) => {
                    editingItem.value = item;
                    form.value = { ...item };
                    showModal.value = true;
                };

                const saveRenewal = async () => {
                    try {
                        await api.post('/renewal/save', {
                            account_id: selectedAccountId.value,
                            target_year: targetYear.value,
                            ...form.value
                        });
                        showModal.value = false;
                        fetchRenewals();
                    } catch (e) { alert('Failed: ' + (e.response?.data?.message || e.message)); }
                };

                const deleteRenewal = async (id) => {
                    if (!confirm('Remove this user from renewal list?')) return;
                    try {
                        await api.post('/renewal/drop', { id });
                        fetchRenewals();
                    } catch (e) { alert('Failed to delete'); }
                };

                const showReceipt = (item) => {
                    receiptItem.value = item;
                    showReceiptModal.value = true;
                };

                const togglePaid = async (item) => {
                    try {
                        await api.post('/renewal/save', {
                            account_id: selectedAccountId.value,
                            target_year: targetYear.value,
                            user_email: item.user_email,
                            price: item.price,
                            is_paid: !item.is_paid,
                            sub_account_id: item.sub_account_id,
                            sub_account_pin: item.sub_account_pin
                        });
                        fetchRenewals();
                        // Update receipt if open
                        if (showReceiptModal.value && receiptItem.value.id === item.id) {
                            receiptItem.value.is_paid = !item.is_paid;
                        }
                    } catch (e) { alert('Failed to update status'); }
                };

                onMounted(() => {
                    token.value = findToken();
                    if (token.value) {
                        api.defaults.headers.common['Authorization'] = token.value;
                        fetchAccounts();
                    }
                });

                return {
                    token, accounts, selectedAccountId, targetYear, renewals, showModal, editingItem, form,
                    selectedAccount, showReceiptModal, receiptItem,
                    fetchRenewals, saveAccountSettings, importCurrentUsers,
                    openAddModal, editRenewal, saveRenewal, deleteRenewal, togglePaid, showReceipt
                };
            }
        }).mount('#app');
    </script>
</body>
</html>
