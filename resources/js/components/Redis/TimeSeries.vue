<script setup lang="ts">
import { ref, onMounted, computed, watch, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';

// Type definitions
interface User {
    id: number;
    name: string;
    email: string;
    uploads: number;
    xp: number;
    streak: number;
}

interface GlobalMetrics {
    [key: string]: {
        total: number;
        unique: number;
        top10: Record<string, number>;
    };
}

interface TimeSeriesData {
    [month: string]: {
        photos: number;
        xp: number;
    };
}

interface GeoData {
    countries: { locations: number; totalPhotos: number };
    states: { locations: number; totalPhotos: number };
    cities: { locations: number; totalPhotos: number };
}

interface ServerStats {
    usedMemory: string;
    totalKeys: number;
    connectedClients: number;
    uptime: number;
}

interface RedisData {
    users: User[];
    global: GlobalMetrics;
    timeSeries: TimeSeriesData;
    geo: GeoData;
    stats: ServerStats;
}

interface UserDetailData {
    user: {
        id: number;
        name: string;
        email: string;
    };
    metrics: Record<string, any>;
    raw: {
        uploads: number;
        xp: number;
        streak: number;
        categories: Record<string, number>;
        objects: Record<string, number>;
        materials: Record<string, number>;
        brands: Record<string, number>;
        custom_tags: Record<string, number>;
    };
}

// Composables
const route = useRoute();
const router = useRouter();

// State
const data = ref<RedisData | null>(null);
const selectedUser = ref<UserDetailData | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
const lastUpdated = ref<Date | null>(null);
const autoRefresh = ref(false);
const refreshInterval = ref<number | null>(null);

// Computed
const userId = computed(() => route.params.userId as string | undefined);
const isUserDetail = computed(() => !!userId.value);

// Methods
const fetchData = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await fetch('/api/redis-data', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) throw new Error('Failed to fetch data');

        data.value = await response.json();
        lastUpdated.value = new Date();
    } catch (err: any) {
        error.value = err.message || 'Failed to fetch Redis data';
    } finally {
        loading.value = false;
    }
};

const fetchUserDetail = async (id: string) => {
    loading.value = true;
    error.value = null;

    try {
        const response = await fetch(`/api/redis-data/${id}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) throw new Error('Failed to fetch user data');

        selectedUser.value = await response.json();
    } catch (err: any) {
        error.value = err.message || 'Failed to fetch user details';
    } finally {
        loading.value = false;
    }
};

const toggleAutoRefresh = () => {
    autoRefresh.value = !autoRefresh.value;

    if (autoRefresh.value) {
        refreshInterval.value = window.setInterval(() => {
            if (!isUserDetail.value) {
                fetchData();
            }
        }, 10000); // Refresh every 10 seconds
    } else if (refreshInterval.value) {
        clearInterval(refreshInterval.value);
        refreshInterval.value = null;
    }
};

const formatTime = (date: Date) => {
    return date.toLocaleTimeString();
};

const formatNumber = (num: number) => {
    return num.toLocaleString();
};

const navigateToUser = (id: number) => {
    router.push(`/admin/redis/${id}`);
};

const goBack = () => {
    router.push('/admin/redis');
};

// Lifecycle
watch(userId, (newUserId) => {
    if (newUserId) {
        fetchUserDetail(newUserId);
    } else {
        selectedUser.value = null;
        fetchData();
    }
});

onMounted(() => {
    if (userId.value) {
        fetchUserDetail(userId.value);
    } else {
        fetchData();
    }
});

onUnmounted(() => {
    if (refreshInterval.value) {
        clearInterval(refreshInterval.value);
    }
});
</script>

<template>
    <div class="redis-viewer min-h-screen bg-gray-100 py-8">
        <div class="container mx-auto px-4 max-w-7xl">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-4">
                        <h1 v-if="!isUserDetail" class="text-3xl font-bold text-gray-800">Redis Data Viewer</h1>
                        <div v-else class="flex items-center gap-4">
                            <button
                                @click="goBack"
                                class="text-blue-600 hover:text-blue-800 flex items-center gap-2 transition-colors"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15 19l-7-7 7-7"
                                    ></path>
                                </svg>
                                Back to Overview
                            </button>
                            <h1 class="text-2xl font-bold text-gray-800">User Redis Data</h1>
                        </div>
                    </div>

                    <div v-if="!isUserDetail" class="flex items-center gap-4">
                        <button
                            @click="fetchData"
                            :disabled="loading"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors flex items-center gap-2"
                        >
                            <svg v-if="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                ></path>
                            </svg>
                            <span>{{ loading ? 'Loading...' : 'Refresh' }}</span>
                        </button>

                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="autoRefresh"
                                @change="toggleAutoRefresh"
                                class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                            />
                            <span class="text-sm text-gray-700">Auto-refresh</span>
                        </label>
                    </div>
                </div>

                <div v-if="lastUpdated && !isUserDetail" class="text-sm text-gray-600">
                    Last updated: {{ formatTime(lastUpdated) }}
                </div>
            </div>

            <!-- Error Alert -->
            <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <div class="flex items-center justify-between">
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

            <!-- Loading State -->
            <div v-if="loading && !data && !selectedUser" class="flex justify-center items-center h-64">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            </div>

            <!-- User Detail View -->
            <div v-else-if="isUserDetail && selectedUser" class="space-y-6">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-semibold mb-4">{{ selectedUser.user.name }}</h2>
                    <p class="text-gray-600 mb-6">{{ selectedUser.user.email }}</p>

                    <!-- Basic Stats -->
                    <div class="grid grid-cols-3 gap-6 mb-8">
                        <div class="text-center">
                            <div class="text-3xl font-bold text-blue-600">{{ selectedUser.raw.uploads }}</div>
                            <div class="text-sm text-gray-600">Uploads</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-green-600">{{ Math.round(selectedUser.raw.xp) }}</div>
                            <div class="text-sm text-gray-600">XP</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-purple-600">{{ selectedUser.raw.streak }}</div>
                            <div class="text-sm text-gray-600">Current Streak</div>
                        </div>
                    </div>
                </div>

                <!-- Metrics Sections -->
                <div
                    v-for="(items, category) in selectedUser.metrics"
                    :key="category"
                    v-if="typeof items === 'object' && Object.keys(items).length > 0"
                    class="bg-white rounded-lg shadow-lg p-6"
                >
                    <h3 class="text-lg font-semibold mb-4 capitalize">{{ category.replace('_', ' ') }}</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                        <div
                            v-for="(count, item) in items"
                            :key="item"
                            class="border border-gray-200 rounded-lg p-3 hover:shadow-md transition-shadow"
                        >
                            <div class="font-medium text-gray-800">{{ item }}</div>
                            <div class="text-sm text-gray-600">{{ formatNumber(count) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Dashboard -->
            <div v-else-if="data" class="space-y-6">
                <!-- Server Stats -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-semibold mb-4">Server Statistics</h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ data.stats.usedMemory }}</div>
                            <div class="text-sm text-gray-600">Memory Used</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">
                                {{ formatNumber(data.stats.totalKeys) }}
                            </div>
                            <div class="text-sm text-gray-600">Total Keys</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600">{{ data.stats.connectedClients }}</div>
                            <div class="text-sm text-gray-600">Connected Clients</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-orange-600">{{ data.stats.uptime }}</div>
                            <div class="text-sm text-gray-600">Uptime (days)</div>
                        </div>
                    </div>
                </div>

                <!-- Global Metrics -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-semibold mb-4">Global Metrics</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div
                            v-for="(metrics, type) in data.global"
                            :key="type"
                            class="border border-gray-200 rounded-lg p-4"
                        >
                            <h3 class="font-semibold capitalize mb-3">{{ type }}</h3>
                            <div class="space-y-1 text-sm">
                                <div>
                                    Total: <span class="font-medium">{{ formatNumber(metrics.total) }}</span>
                                </div>
                                <div>
                                    Unique: <span class="font-medium">{{ metrics.unique }}</span>
                                </div>
                                <div class="mt-3">
                                    <div class="font-medium mb-1">Top 5:</div>
                                    <div
                                        v-for="([item, count], index) in Object.entries(metrics.top10).slice(0, 5)"
                                        :key="item"
                                        class="text-xs text-gray-600"
                                    >
                                        {{ item }}: {{ formatNumber(count) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Geographic Distribution -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-semibold mb-4">Geographic Distribution</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div v-for="(stats, type) in data.geo" :key="type" class="text-center">
                            <div class="text-3xl font-bold text-green-600">{{ stats.locations }}</div>
                            <div class="text-sm text-gray-600 capitalize mb-1">{{ type }}</div>
                            <div class="text-xs text-gray-500">{{ formatNumber(stats.totalPhotos) }} photos</div>
                        </div>
                    </div>
                </div>

                <!-- Time Series -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-semibold mb-4">Monthly Activity</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Month</th>
                                    <th class="px-4 py-3 text-right text-sm font-medium text-gray-700">Photos</th>
                                    <th class="px-4 py-3 text-right text-sm font-medium text-gray-700">XP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(stats, month) in data.timeSeries"
                                    :key="month"
                                    class="border-b border-gray-100 hover:bg-gray-50"
                                >
                                    <td class="px-4 py-3 text-sm">{{ month }}</td>
                                    <td class="px-4 py-3 text-sm text-right">{{ formatNumber(stats.photos) }}</td>
                                    <td class="px-4 py-3 text-sm text-right">
                                        {{ formatNumber(Math.round(stats.xp)) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Users List -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-semibold mb-4">Top Users</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">User</th>
                                    <th class="px-4 py-3 text-right text-sm font-medium text-gray-700">Uploads</th>
                                    <th class="px-4 py-3 text-right text-sm font-medium text-gray-700">XP</th>
                                    <th class="px-4 py-3 text-right text-sm font-medium text-gray-700">Streak</th>
                                    <th class="px-4 py-3 text-center text-sm font-medium text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="user in data.users"
                                    :key="user.id"
                                    class="border-b border-gray-100 hover:bg-gray-50"
                                >
                                    <td class="px-4 py-3">
                                        <div class="text-sm font-medium text-gray-900">{{ user.name }}</div>
                                        <div class="text-xs text-gray-500">{{ user.email }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right">{{ formatNumber(user.uploads) }}</td>
                                    <td class="px-4 py-3 text-sm text-right">
                                        {{ formatNumber(Math.round(user.xp)) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right">{{ user.streak }}</td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        <button
                                            @click="navigateToUser(user.id)"
                                            class="text-blue-600 hover:text-blue-800 font-medium transition-colors"
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
    </div>
</template>

<style scoped>
/* Add any additional styles here if needed */
.redis-viewer {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
}

/* Smooth transitions for interactive elements */
button,
a {
    transition: all 0.2s ease;
}

/* Custom scrollbar for tables */
.overflow-x-auto::-webkit-scrollbar {
    height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>
