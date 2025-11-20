<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTT Management - V2Board</title>
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
                <h1 class="text-3xl font-bold text-white">OTT Management</h1>
                <div class="space-x-4">
                    <!-- Tabs removed, single view now -->
                </div>
            </div>

            <!-- Auth Warning -->
            <div v-if="!token" class="bg-red-600 text-white p-4 rounded mb-6">
                ⚠️ Authentication Token not found. Please log in to the main Admin Panel first, then refresh this page.
            </div>

            <!-- Accounts List -->
            <div v-if="token">
                <div class="flex justify-end mb-4">
                    <button @click="openAccountModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                        + Add Account
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
                                <button @click="openAccountModal(account)" class="text-blue-400 hover:text-blue-300">Edit</button>
                                <button @click="deleteAccount(account.id)" class="text-red-400 hover:text-red-300">Delete</button>
                            </div>
                        </div>
                        <div class="space-y-2 text-sm text-gray-300 flex-grow">
                            <p><span class="text-gray-500">Username:</span> @{{ account.username }}</p>
                            <p><span class="text-gray-500">OTP:</span> @{{ account.has_otp ? 'Yes' : 'No' }}</p>
                            <p><span class="text-gray-500">Shared:</span> @{{ account.is_shared_credentials ? 'Yes' : 'No' }}</p>
                            <p><span class="text-gray-500">Seats:</span> @{{ account.shared_seats }}</p>
                            <p><span class="text-gray-500">Cost/User/Year:</span> <span class="text-yellow-400">@{{ calculateCost(account) }}</span></p>
                            <p><span class="text-gray-500">Status:</span> 
                                <span :class="account.is_active ? 'text-green-400' : 'text-red-400'">
                                    @{{ account.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-gray-700">
                            <button @click="openUsersModal(account)" class="w-full bg-gray-700 hover:bg-gray-600 text-white py-2 rounded transition flex justify-center items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                </svg>
                                Manage Users
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Modal -->
            <div v-if="showAccountModal" class="fixed inset-0 modal flex items-center justify-center p-4 z-50">
                <div class="bg-gray-800 rounded-lg w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 border border-gray-700">
                    <h2 class="text-2xl font-bold text-white mb-6">{{ editingAccount ? 'Edit Account' : 'New Account' }}</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm text-gray-400 mb-1">Name</label>
                            <input v-model="accountForm.name" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Type</label>
                            <input v-model="accountForm.type" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white" placeholder="netflix">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Group ID (Optional)</label>
                            <input v-model="accountForm.group_id" type="number" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Username</label>
                            <input v-model="accountForm.username" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Password</label>
                            <input v-model="accountForm.password" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>
                        
                        <div class="col-span-2 border-t border-gray-700 pt-4 mt-2">
                            <h3 class="text-lg font-semibold text-white mb-3">Email & OTP Settings</h3>
                        </div>

                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Sender Filter (Regex)</label>
                            <input v-model="accountForm.sender_filter" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white" placeholder="/netflix/i">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Recipient Filter (Regex)</label>
                            <input v-model="accountForm.recipient_filter" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm text-gray-400 mb-1">Subject/Content OTP Regex</label>
                            <input v-model="accountForm.subject_regex" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white" placeholder="/code is (\d+)/">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm text-gray-400 mb-1">Ignore Regex (Don't store if matches)</label>
                            <input v-model="accountForm.ignore_regex" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white" placeholder="/reset password/i">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">OTP Validity (Minutes)</label>
                            <input v-model="accountForm.otp_validity_minutes" type="number" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>

                        <div class="col-span-2 border-t border-gray-700 pt-4 mt-2">
                            <h3 class="text-lg font-semibold text-white mb-3">Pricing & Seats</h3>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Monthly Price (Total)</label>
                            <input v-model="accountForm.price_monthly" type="number" step="0.01" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Yearly Price (Total)</label>
                            <input v-model="accountForm.price_yearly" type="number" step="0.01" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Shared Seats</label>
                            <input v-model="accountForm.shared_seats" type="number" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>
                        <div class="flex items-end pb-2">
                            <span class="text-gray-400 text-sm">Calc Cost/User/Year: <span class="text-yellow-400 font-bold">{{ calculateFormCost() }}</span></span>
                        </div>

                        <div class="col-span-2 flex gap-6 mt-2 border-t border-gray-700 pt-4">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" v-model="accountForm.has_otp" class="form-checkbox text-blue-600">
                                <span class="text-white">Has OTP</span>
                            </label>
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" v-model="accountForm.is_shared_credentials" class="form-checkbox text-blue-600">
                                <span class="text-white">Shared Credentials</span>
                            </label>
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" v-model="accountForm.is_active" class="form-checkbox text-green-600">
                                <span class="text-white">Active</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4 mt-8">
                        <button @click="showAccountModal = false" class="px-4 py-2 text-gray-400 hover:text-white">Cancel</button>
                        <button @click="saveAccount" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">Save</button>
                    </div>
                </div>
            </div>

            <!-- Users Modal -->
            <div v-if="showUsersModal" class="fixed inset-0 modal flex items-center justify-center p-4 z-50">
                <div class="bg-gray-800 rounded-lg w-full max-w-3xl max-h-[90vh] overflow-y-auto p-6 border border-gray-700">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-white">Users for {{ currentAccount ? currentAccount.name : '' }}</h2>
                        <button @click="showUsersModal = false" class="text-gray-400 hover:text-white">✕</button>
                    </div>

                    <!-- Add User Form -->
                    <div class="bg-gray-700 p-4 rounded mb-6">
                        <h3 class="text-lg font-semibold text-white mb-3">Add / Update User Binding</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div class="col-span-1 md:col-span-2">
                                <input v-model="bindForm.email" type="email" class="w-full bg-gray-600 border border-gray-500 rounded px-3 py-2 text-white text-sm" placeholder="User Email">
                            </div>
                            <div>
                                <input v-model="bindForm.expired_at" type="date" class="w-full bg-gray-600 border border-gray-500 rounded px-3 py-2 text-white text-sm">
                            </div>
                            <div class="row-start-2 col-span-1 md:col-span-2">
                                <input v-model="bindForm.sub_account_id" class="w-full bg-gray-600 border border-gray-500 rounded px-3 py-2 text-white text-sm" placeholder="Profile Name (e.g. Kids)">
                            </div>
                            <div class="row-start-2">
                                <input v-model="bindForm.sub_account_pin" class="w-full bg-gray-600 border border-gray-500 rounded px-3 py-2 text-white text-sm" placeholder="PIN (e.g. 1234)">
                            </div>
                            <div class="row-start-2">
                                <button @click="bindUser" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm">Bind</button>
                            </div>
                        </div>
                    </div>

                    <!-- Users List -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-300">
                            <thead class="bg-gray-900 text-gray-400 uppercase font-medium">
                                <tr>
                                    <th class="px-4 py-3">User Email</th>
                                    <th class="px-4 py-3">Profile</th>
                                    <th class="px-4 py-3">PIN</th>
                                    <th class="px-4 py-3">Expires</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <tr v-for="user in accountUsers" :key="user.id" class="hover:bg-gray-700">
                                    <td class="px-4 py-3">{{ user.user_email }}</td>
                                    <td class="px-4 py-3">{{ user.sub_account_id || '-' }}</td>
                                    <td class="px-4 py-3">{{ user.sub_account_pin || '-' }}</td>
                                    <td class="px-4 py-3" :class="isExpired(user.expired_at) ? 'text-red-400' : 'text-green-400'">
                                        {{ formatDate(user.expired_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-2">
                                        <button @click="editUser(user)" class="text-blue-400 hover:text-blue-300">Edit</button>
                                        <button @click="unbindUser(user.user_id)" class="text-red-400 hover:text-red-300">Unbind</button>
                                    </td>
                                </tr>
                                <tr v-if="accountUsers.length === 0">
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">No users bound to this account.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
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
                const editingAccount = ref(null);
                const currentAccount = ref(null);
                const accountUsers = ref([]);
                
                const accountForm = ref({
                    name: '', type: '', username: '', password: '', 
                    has_otp: false, is_shared_credentials: true, is_active: true,
                    sender_filter: '', recipient_filter: '', subject_regex: '',
                    ignore_regex: '', otp_validity_minutes: 10, group_id: null,
                    price_monthly: null, price_yearly: null, shared_seats: 1
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
                        alert('Failed to fetch accounts');
                    }
                };

                const fetchAccountUsers = async (accountId) => {
                    try {
                        const res = await api.get('/user/fetch', { params: { account_id: accountId } });
                        accountUsers.value = res.data.data;
                    } catch (e) {
                        console.error(e);
                        alert('Failed to fetch users');
                    }
                };

                const openAccountModal = (account = null) => {
                    if (account) {
                        editingAccount.value = account;
                        accountForm.value = { ...account };
                    } else {
                        editingAccount.value = null;
                        accountForm.value = {
                            name: '', type: '', username: '', password: '', 
                            has_otp: false, is_shared_credentials: true, is_active: true,
                            sender_filter: '', recipient_filter: '', subject_regex: '',
                            ignore_regex: '', otp_validity_minutes: 10, group_id: null,
                            price_monthly: null, price_yearly: null, shared_seats: 1
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
                        alert('Failed to save account');
                    }
                };

                const deleteAccount = async (id) => {
                    if (!confirm('Are you sure?')) return;
                    try {
                        await api.post('/account/drop', { id });
                        fetchAccounts();
                    } catch (e) {
                        console.error(e);
                        alert('Failed to delete account');
                    }
                };

                const bindUser = async () => {
                    if (!bindForm.value.email || !bindForm.value.expired_at) {
                        alert('Please fill email and expiry date');
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
                        alert('Failed to bind user: ' + (e.response && e.response.data && e.response.data.message ? e.response.data.message : e.message));
                    }
                };

                const unbindUser = async (userId) => {
                    if (!confirm('Unbind this user?')) return;
                    try {
                        await api.post('/user/unbind', {
                            user_id: userId,
                            account_id: currentAccount.value.id
                        });
                        fetchAccountUsers(currentAccount.value.id);
                    } catch (e) {
                        console.error(e);
                        alert('Failed to unbind user');
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
                    token, accounts, showAccountModal, showUsersModal, editingAccount, currentAccount, accountUsers,
                    accountForm, bindForm,
                    openAccountModal, openUsersModal, saveAccount, deleteAccount, bindUser, unbindUser, editUser,
                    formatDate, isExpired, calculateCost, calculateFormCost
                };
            }
        }).mount('#app');
    </script>
</body>
</html>
