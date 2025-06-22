<script setup lang="ts">
interface ServerStats {
    usedMemory: string;
    totalKeys: number;
    connectedClients: number;
    uptime: number;
}

interface Props {
    stats: ServerStats;
}

defineProps<Props>();

const formatNumber = (num: number) => num.toLocaleString();
</script>

<template>
    <div class="bg-white rounded-lg shadow-lg p-6">
        <h2 class="text-xl font-semibold mb-4">Server Statistics</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div class="stat-card">
                <div class="stat-icon bg-blue-100 text-blue-600">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"
                        ></path>
                    </svg>
                </div>
                <div class="stat-value">{{ stats.usedMemory }}</div>
                <div class="stat-label">Memory Used</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon bg-green-100 text-green-600">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"
                        ></path>
                    </svg>
                </div>
                <div class="stat-value">{{ formatNumber(stats.totalKeys) }}</div>
                <div class="stat-label">Total Keys</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon bg-purple-100 text-purple-600">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                        ></path>
                    </svg>
                </div>
                <div class="stat-value">{{ stats.connectedClients }}</div>
                <div class="stat-label">Connected Clients</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon bg-orange-100 text-orange-600">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                        ></path>
                    </svg>
                </div>
                <div class="stat-value">{{ stats.uptime }}</div>
                <div class="stat-label">Uptime (days)</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.stat-card {
    @apply text-center relative;
}

.stat-icon {
    @apply w-16 h-16 mx-auto mb-3 rounded-full flex items-center justify-center;
}

.stat-value {
    @apply text-2xl font-bold mb-1;
}

.stat-label {
    @apply text-sm text-gray-600;
}
</style>
