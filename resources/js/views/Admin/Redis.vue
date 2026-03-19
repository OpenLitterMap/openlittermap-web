<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';

// Route
const route = useRoute();
const router = useRouter();

// Data
const data = ref(null);
const selectedUser = ref(null);
const loading = ref(false);
const error = ref(null);
const activeTab = ref('overview');

// Computed
const userId = computed(() => route.params.userId);

// Format numbers
const formatNumber = (num) => {
    return Number(num).toLocaleString();
};

// Format bytes
const formatBytes = (bytes) => {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

// Methods
const fetchData = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get('/api/redis-data');
        data.value = response.data;
        console.log('Redis data:', response.data);
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to fetch data';
    } finally {
        loading.value = false;
    }
};

const fetchUserData = async (id) => {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(`/api/redis-data/${id}`);
        selectedUser.value = response.data;
        console.log('User data:', response.data);
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to fetch user data';
    } finally {
        loading.value = false;
    }
};

const viewUser = (id) => {
    router.push(`/admin/redis/${id}`);
};

const goBack = () => {
    router.push('/admin/redis');
};

const refreshData = () => {
    if (userId.value) {
        fetchUserData(userId.value);
    } else {
        fetchData();
    }
};

// Calculate totals
const calculateTotals = (data) => {
    if (!data) return {};

    const totalUploads = data.users?.reduce((sum, user) => sum + user.uploads, 0) || 0;
    const totalXp = data.users?.reduce((sum, user) => sum + user.xp, 0) || 0;
    const totalItems = Object.values(data.global || {}).reduce((sum, metric) => sum + metric.total, 0);
    const uniqueItems = Object.values(data.global || {}).reduce((sum, metric) => sum + metric.unique, 0);

    return {
        totalUploads,
        totalXp,
        totalItems,
        uniqueItems,
        totalUsers: data.users?.length || 0,
    };
};

// Lifecycle
onMounted(() => {
    if (userId.value) {
        fetchUserData(userId.value);
    } else {
        fetchData();
    }
});
</script>

<template>
    <div class="p-6 bg-gray-50 min-h-screen">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 v-if="!userId" class="text-3xl font-bold text-gray-800">Redis Data Viewer</h1>
                    <div v-else>
                        <button @click="goBack" class="text-blue-600 hover:text-blue-800 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 19l-7-7 7-7"
                                ></path>
                            </svg>
                            Back to Overview
                        </button>
                        <h1 class="text-2xl font-bold text-gray-800">User Details</h1>
                    </div>
                </div>
                <button
                    @click="refreshData"
                    :disabled="loading"
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 flex items-center"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                        ></path>
                    </svg>
                    {{ loading ? 'Loading...' : 'Refresh' }}
                </button>
            </div>
        </div>

        <!-- Error -->
        <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <div class="flex justify-between items-center">
                <span>{{ error }}</span>
                <button @click="error = null" class="text-red-700 hover:text-red-900">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd"
                        ></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading && !data && !selectedUser" class="text-center py-12">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
            <p class="mt-4 text-gray-600">Loading Redis data...</p>
        </div>

        <!-- User Detail View -->
        <div v-else-if="userId && selectedUser" class="space-y-6">
            <!-- User Info Card -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">{{ selectedUser.user.name }}</h2>
                        <p class="text-gray-600">{{ selectedUser.user.email }}</p>
                        <p class="text-sm text-gray-500 mt-1">User ID: {{ selectedUser.user.id }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Member since</p>
                        <p class="text-lg font-semibold">
                            {{ new Date(selectedUser.user.created_at || Date.now()).toLocaleDateString() }}
                        </p>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-3 gap-6">
                    <div class="bg-blue-50 rounded-lg p-4 text-center">
                        <div class="text-3xl font-bold text-blue-600">{{ formatNumber(selectedUser.raw.uploads) }}</div>
                        <div class="text-sm text-gray-600 mt-1">Total Uploads</div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4 text-center">
                        <div class="text-3xl font-bold text-green-600">
                            {{ formatNumber(Math.round(selectedUser.raw.xp)) }}
                        </div>
                        <div class="text-sm text-gray-600 mt-1">Total XP</div>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-4 text-center">
                        <div class="text-3xl font-bold text-purple-600">
                            {{ formatNumber(selectedUser.raw.streak) }}
                        </div>
                        <div class="text-sm text-gray-600 mt-1">Current Streak</div>
                    </div>
                </div>
            </div>

            <!-- User Metrics Tabs -->
            <div class="bg-white rounded-lg shadow-lg">
                <div class="border-b">
                    <div class="flex space-x-8 px-6">
                        <button
                            v-for="category in Object.keys(selectedUser.metrics || {})"
                            :key="category"
                            @click="activeTab = category"
                            :class="[
                                'py-3 border-b-2 font-medium text-sm capitalize transition-colors',
                                activeTab === category
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700',
                            ]"
                        >
                            {{ category.replace('_', ' ') }}
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    <div v-for="(items, category) in selectedUser.metrics" :key="category">
                        <div v-if="activeTab === category && Object.keys(items).length > 0">
                            <h3 class="text-lg font-semibold mb-4 capitalize">
                                {{ category.replace('_', ' ') }} Details
                            </h3>
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                <div
                                    v-for="(count, item) in items"
                                    :key="item"
                                    class="bg-gray-50 border border-gray-200 rounded-lg p-3 hover:shadow-md transition-shadow"
                                >
                                    <div class="font-medium text-gray-800">{{ item }}</div>
                                    <div class="text-lg font-semibold text-blue-600 mt-1">
                                        {{ formatNumber(count) }}
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 text-sm text-gray-600">
                                Total {{ category }}:
                                <span class="font-semibold">{{
                                    formatNumber(Object.values(items).reduce((a, b) => a + b, 0))
                                }}</span>
                                | Unique: <span class="font-semibold">{{ Object.keys(items).length }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Overview -->
        <div v-else-if="!loading && data">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                <div
                    v-for="(value, label) in calculateTotals(data)"
                    :key="label"
                    class="bg-white rounded-lg shadow p-4"
                >
                    <div class="text-sm text-gray-600 capitalize">{{ label.replace(/([A-Z])/g, ' $1').trim() }}</div>
                    <div class="text-2xl font-bold mt-1">{{ formatNumber(value) }}</div>
                </div>
            </div>

            <!-- Server Stats -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4 text-gray-800">Redis Server Statistics</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div class="text-center">
                        <div class="text-3xl font-bold text-blue-600">{{ data.stats.usedMemory }}</div>
                        <div class="text-sm text-gray-600 mt-1">Memory Used</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-green-600">{{ formatNumber(data.stats.totalKeys) }}</div>
                        <div class="text-sm text-gray-600 mt-1">Total Keys</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-purple-600">{{ data.stats.connectedClients }}</div>
                        <div class="text-sm text-gray-600 mt-1">Connected Clients</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-orange-600">{{ data.stats.uptime }}</div>
                        <div class="text-sm text-gray-600 mt-1">Uptime (days)</div>
                    </div>
                </div>
            </div>

            <!-- Global Metrics -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4 text-gray-800">Global Metrics Overview</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div
                        v-for="(metric, type) in data.global"
                        :key="type"
                        class="border border-gray-200 rounded-lg p-4"
                    >
                        <h3 class="font-semibold text-lg capitalize mb-3">{{ type }}</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Total:</span>
                                <span class="font-semibold">{{ formatNumber(metric.total) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Unique:</span>
                                <span class="font-semibold">{{ formatNumber(metric.unique) }}</span>
                            </div>
                            <div class="mt-3 pt-3 border-t">
                                <div class="text-sm font-medium text-gray-700 mb-2">Top 5:</div>
                                <div
                                    v-for="(count, item) in Object.fromEntries(
                                        Object.entries(metric.top10).slice(0, 5)
                                    )"
                                    :key="item"
                                    class="text-xs flex justify-between py-1"
                                >
                                    <span class="text-gray-600 truncate mr-2">{{ item }}</span>
                                    <span class="font-medium">{{ formatNumber(count) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Geographic Distribution -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4 text-gray-800">Geographic Distribution</h2>
                <div class="grid grid-cols-3 gap-6">
                    <div v-for="(geo, type) in data.geo" :key="type" class="text-center">
                        <div class="text-4xl font-bold text-teal-600">{{ formatNumber(geo.locations) }}</div>
                        <div class="text-sm text-gray-600 mt-1 capitalize">{{ type }}</div>
                        <div class="text-xs text-gray-500 mt-1">{{ formatNumber(geo.totalPhotos) }} photos</div>
                    </div>
                </div>
            </div>

            <!-- Time Series -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4 text-gray-800">Monthly Activity Trends</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 font-medium text-gray-700">Month</th>
                                <th class="text-right py-3 px-4 font-medium text-gray-700">Photos</th>
                                <th class="text-right py-3 px-4 font-medium text-gray-700">XP</th>
                                <th class="text-right py-3 px-4 font-medium text-gray-700">Avg XP/Photo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(stats, month) in data.timeSeries"
                                :key="month"
                                class="border-b border-gray-100 hover:bg-gray-50"
                            >
                                <td class="py-3 px-4">{{ month }}</td>
                                <td class="text-right py-3 px-4">{{ formatNumber(stats.photos) }}</td>
                                <td class="text-right py-3 px-4">{{ formatNumber(Math.round(stats.xp)) }}</td>
                                <td class="text-right py-3 px-4">
                                    {{ stats.photos > 0 ? (stats.xp / stats.photos).toFixed(1) : '0' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Users Table -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-4 text-gray-800">Top Contributors</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 font-medium text-gray-700">User</th>
                                <th class="text-right py-3 px-4 font-medium text-gray-700">Uploads</th>
                                <th class="text-right py-3 px-4 font-medium text-gray-700">XP</th>
                                <th class="text-right py-3 px-4 font-medium text-gray-700">Streak</th>
                                <th class="text-right py-3 px-4 font-medium text-gray-700">Avg XP</th>
                                <th class="text-center py-3 px-4 font-medium text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(user, index) in data.users"
                                :key="user.id"
                                class="border-b border-gray-100 hover:bg-gray-50"
                            >
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div
                                            class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center mr-3 text-sm font-medium"
                                        >
                                            {{ index + 1 }}
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900">{{ user.name }}</div>
                                            <div class="text-sm text-gray-500">{{ user.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-right py-3 px-4 font-medium">{{ formatNumber(user.uploads) }}</td>
                                <td class="text-right py-3 px-4 font-medium">
                                    {{ formatNumber(Math.round(user.xp)) }}
                                </td>
                                <td class="text-right py-3 px-4">
                                    <span
                                        :class="[
                                            'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium',
                                            user.streak > 7
                                                ? 'bg-green-100 text-green-800'
                                                : user.streak > 0
                                                  ? 'bg-blue-100 text-blue-800'
                                                  : 'bg-gray-100 text-gray-800',
                                        ]"
                                    >
                                        {{ user.streak }} days
                                    </span>
                                </td>
                                <td class="text-right py-3 px-4">
                                    {{ user.uploads > 0 ? (user.xp / user.uploads).toFixed(1) : '0' }}
                                </td>
                                <td class="text-center py-3 px-4">
                                    <button
                                        @click="viewUser(user.id)"
                                        class="text-blue-600 hover:text-blue-800 font-medium"
                                    >
                                        View Details
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Custom scrollbar */
.overflow-x-auto::-webkit-scrollbar {
    height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>
