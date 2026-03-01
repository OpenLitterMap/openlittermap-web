<template>
    <div class="min-h-screen bg-gray-900 text-white">
        <!-- Header -->
        <div class="bg-gray-800 border-b border-gray-700 px-6 py-4">
            <div class="max-w-7xl mx-auto flex items-center justify-between">
                <h1 class="text-xl font-semibold">User Management</h1>
                <div class="text-sm text-gray-400">
                    {{ adminStore.users.total }} users total
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-6 py-6">
            <!-- Stats cards -->
            <div v-if="!adminStore.statsLoading" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                <div class="bg-gray-800 rounded-lg px-4 py-3 border border-gray-700">
                    <div class="text-xs text-gray-400 uppercase tracking-wider">Queue</div>
                    <div class="text-2xl font-bold text-amber-400">{{ adminStore.stats.queue_total }}</div>
                    <div class="text-xs text-gray-500">{{ adminStore.stats.queue_today }} today</div>
                </div>
                <div class="bg-gray-800 rounded-lg px-4 py-3 border border-gray-700">
                    <div class="text-xs text-gray-400 uppercase tracking-wider">Users</div>
                    <div class="text-2xl font-bold text-blue-400">{{ (adminStore.stats.total_users ?? 0).toLocaleString() }}</div>
                    <div class="text-xs text-gray-500">{{ adminStore.stats.users_today }} today</div>
                </div>
                <div class="bg-gray-800 rounded-lg px-4 py-3 border border-gray-700">
                    <div class="text-xs text-gray-400 uppercase tracking-wider">Flagged Names</div>
                    <div class="text-2xl font-bold" :class="adminStore.stats.flagged_usernames > 0 ? 'text-amber-400' : 'text-gray-500'">
                        {{ adminStore.stats.flagged_usernames }}
                    </div>
                    <div class="text-xs text-gray-500">needs review</div>
                </div>
                <div class="bg-gray-800 rounded-lg px-4 py-3 border border-gray-700">
                    <div class="text-xs text-gray-400 uppercase tracking-wider">Top Country</div>
                    <div class="text-lg font-bold text-green-400 truncate">
                        {{ topCountry.name || '-' }}
                    </div>
                    <div class="text-xs text-gray-500">{{ topCountry.count }} pending</div>
                </div>
            </div>

            <!-- Country breakdown (collapsible) -->
            <div v-if="Object.keys(adminStore.stats.by_country).length > 0" class="mb-6">
                <button
                    @click="showCountries = !showCountries"
                    class="text-xs text-gray-400 hover:text-white transition-colors"
                >
                    {{ showCountries ? 'Hide' : 'Show' }} country breakdown ({{ Object.keys(adminStore.stats.by_country).length }})
                </button>
                <div v-if="showCountries" class="mt-2 flex flex-wrap gap-2">
                    <span
                        v-for="(count, country) in adminStore.stats.by_country"
                        :key="country"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs bg-gray-800 border border-gray-700 rounded-full text-gray-300"
                    >
                        {{ country }}
                        <span class="text-amber-400 font-semibold">{{ count }}</span>
                    </span>
                </div>
            </div>

            <!-- Filters bar -->
            <div class="flex flex-wrap items-center gap-4 mb-6">
                <!-- Search -->
                <div class="flex-1 min-w-[250px]">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search name, username, or email..."
                        class="w-full px-4 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-blue-500"
                        @input="debouncedFetch"
                    />
                </div>

                <!-- Trust filter -->
                <select
                    v-model="trustFilter"
                    class="px-4 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-blue-500"
                    @change="fetchPage(1)"
                >
                    <option value="all">All Users</option>
                    <option value="trusted">Trusted</option>
                    <option value="untrusted">Untrusted</option>
                </select>

                <!-- Flagged filter -->
                <button
                    @click="toggleFlagged"
                    :class="flaggedOnly
                        ? 'bg-amber-900/40 border-amber-600 text-amber-400'
                        : 'bg-gray-800 border-gray-600 text-gray-400 hover:text-white'"
                    class="px-4 py-2 border rounded-lg text-sm transition-colors"
                >
                    Flagged Usernames
                </button>

                <!-- Sort -->
                <select
                    v-model="sortBy"
                    class="px-4 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-blue-500"
                    @change="fetchPage(1)"
                >
                    <option value="created_at">Joined</option>
                    <option value="photos_count">Photos</option>
                    <option value="xp">XP</option>
                </select>

                <button
                    @click="toggleSortDir"
                    class="px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white hover:bg-gray-700 transition-colors"
                    :title="sortDir === 'desc' ? 'Descending' : 'Ascending'"
                >
                    {{ sortDir === 'desc' ? '↓' : '↑' }}
                </button>
            </div>

            <!-- Loading -->
            <div v-if="adminStore.usersLoading" class="text-center py-12 text-gray-400">
                Loading users...
            </div>

            <!-- Table -->
            <div v-else-if="adminStore.users.data.length > 0" class="overflow-x-auto rounded-lg border border-gray-700">
                <table class="w-full">
                    <thead class="bg-gray-800 text-xs text-gray-400 uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3 text-left cursor-pointer hover:text-white" @click="sortColumn('created_at')">
                                User
                                <span v-if="sortBy === 'created_at'" class="ml-1">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
                            </th>
                            <th class="px-4 py-3 text-left">Email</th>
                            <th class="px-4 py-3 text-left">Joined</th>
                            <th class="px-4 py-3 text-right cursor-pointer hover:text-white" @click="sortColumn('photos_count')">
                                Photos
                                <span v-if="sortBy === 'photos_count'" class="ml-1">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
                            </th>
                            <th class="px-4 py-3 text-right cursor-pointer hover:text-white" @click="sortColumn('xp')">
                                XP
                                <span v-if="sortBy === 'xp'" class="ml-1">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
                            </th>
                            <th class="px-4 py-3 text-center">Trust</th>
                            <th class="px-4 py-3 text-center">Pending</th>
                            <th class="px-4 py-3 text-left">Roles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <UserRow
                            v-for="user in adminStore.users.data"
                            :key="user.id"
                            :user="user"
                            @refresh="fetchPage(adminStore.users.current_page)"
                        />
                    </tbody>
                </table>
            </div>

            <!-- Empty state -->
            <div v-else class="text-center py-12 text-gray-500">
                No users found.
            </div>

            <!-- Pagination -->
            <div
                v-if="adminStore.users.last_page > 1"
                class="flex items-center justify-between mt-4"
            >
                <div class="text-sm text-gray-400">
                    Page {{ adminStore.users.current_page }} of {{ adminStore.users.last_page }}
                </div>
                <div class="flex gap-2">
                    <button
                        :disabled="adminStore.users.current_page <= 1"
                        @click="fetchPage(adminStore.users.current_page - 1)"
                        class="px-3 py-1.5 text-sm bg-gray-700 text-white rounded hover:bg-gray-600 disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        Previous
                    </button>
                    <button
                        :disabled="adminStore.users.current_page >= adminStore.users.last_page"
                        @click="fetchPage(adminStore.users.current_page + 1)"
                        class="px-3 py-1.5 text-sm bg-gray-700 text-white rounded hover:bg-gray-600 disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAdminStore } from '@stores/admin.js';
import UserRow from './components/UserRow.vue';

const adminStore = useAdminStore();

const search = ref('');
const trustFilter = ref('all');
const flaggedOnly = ref(false);
const sortBy = ref('created_at');
const sortDir = ref('desc');
const showCountries = ref(false);

const topCountry = computed(() => {
    const countries = adminStore.stats.by_country;
    const entries = Object.entries(countries);
    if (entries.length === 0) return { name: null, count: 0 };
    const [name, count] = entries[0]; // already sorted desc by backend
    return { name, count };
});

let debounceTimer = null;

const buildParams = (page = 1) => ({
    page,
    per_page: 25,
    search: search.value || undefined,
    trust_filter: trustFilter.value !== 'all' ? trustFilter.value : undefined,
    flagged: flaggedOnly.value ? true : undefined,
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
});

const fetchPage = (page = 1) => {
    adminStore.fetchUsers(buildParams(page));
};

const debouncedFetch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchPage(1), 300);
};

const sortColumn = (column) => {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc';
    } else {
        sortBy.value = column;
        sortDir.value = 'desc';
    }
    fetchPage(1);
};

const toggleFlagged = () => {
    flaggedOnly.value = !flaggedOnly.value;
    fetchPage(1);
};

const toggleSortDir = () => {
    sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc';
    fetchPage(1);
};

onMounted(() => {
    adminStore.fetchStats();
    fetchPage(1);
});
</script>
