<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-blue-900 to-emerald-900 relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -top-40 -left-40 w-[500px] h-[500px] rounded-full bg-teal-500/[0.07] blur-3xl"></div>
            <div class="absolute top-1/3 -right-32 w-[400px] h-[400px] rounded-full bg-blue-500/[0.08] blur-3xl"></div>
        </div>

        <div class="relative container mx-auto px-4 py-8 max-w-4xl">
            <!-- Loading -->
            <div v-if="loading" class="flex justify-center items-center py-32">
                <div class="animate-spin rounded-full h-10 w-10 border-2 border-white/20 border-t-emerald-400"></div>
            </div>

            <!-- Private profile -->
            <div v-else-if="!profile?.public" class="text-center py-32">
                <svg class="w-16 h-16 mx-auto mb-4 text-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <p class="text-white/50 text-lg">This user's profile is private.</p>
                <router-link to="/leaderboard" class="inline-block mt-4 text-emerald-400 hover:text-emerald-300 transition">
                    &larr; Back to Leaderboard
                </router-link>
            </div>

            <!-- Public profile -->
            <template v-else>
                <!-- Header -->
                <div class="flex items-center gap-4 mb-8">
                    <div
                        class="w-16 h-16 rounded-full bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400 text-2xl font-bold"
                    >
                        {{ userInitial }}
                    </div>
                    <div>
                        <h1 class="text-white text-2xl font-bold">{{ displayName }}</h1>
                        <p v-if="profile.user.username" class="text-white/50 text-sm">@{{ profile.user.username }}</p>
                        <p v-if="profile.user.member_since" class="text-white/30 text-xs mt-0.5">
                            Member since {{ profile.user.member_since }}
                        </p>
                    </div>
                </div>

                <!-- Level -->
                <div class="bg-white/5 border border-white/10 rounded-xl p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">Level</div>
                            <div class="text-white text-4xl font-bold">{{ profile.level.level }}</div>
                            <div class="text-emerald-400 text-sm font-medium">{{ profile.level.title }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">Total XP</div>
                            <div class="text-white text-3xl font-bold tabular-nums">{{ profile.stats.xp?.toLocaleString() }}</div>
                        </div>
                    </div>
                    <div class="w-full h-3 bg-white/10 rounded-full overflow-hidden">
                        <div
                            class="h-full bg-gradient-to-r from-emerald-500 to-teal-400 rounded-full transition-all duration-500"
                            :style="{ width: profile.level.progress_percent + '%' }"
                        ></div>
                    </div>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div class="bg-white/5 border border-white/10 rounded-xl px-5 py-4">
                        <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">Uploads</div>
                        <div class="text-white text-2xl font-bold tabular-nums">{{ profile.stats.uploads?.toLocaleString() }}</div>
                    </div>
                    <div class="bg-white/5 border border-white/10 rounded-xl px-5 py-4">
                        <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">Litter Tagged</div>
                        <div class="text-white text-2xl font-bold tabular-nums">{{ profile.stats.litter?.toLocaleString() }}</div>
                    </div>
                    <div class="bg-white/5 border border-white/10 rounded-xl px-5 py-4">
                        <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">XP</div>
                        <div class="text-white text-2xl font-bold tabular-nums">{{ profile.stats.xp?.toLocaleString() }}</div>
                    </div>
                </div>

                <!-- Rank -->
                <div class="mb-6">
                    <div class="bg-white/5 border border-white/10 rounded-xl p-6">
                        <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-3">Global Rank</div>
                        <div class="text-white text-3xl font-bold tabular-nums">
                            #{{ profile.rank.global_position?.toLocaleString() }}
                        </div>
                        <div class="text-white/40 text-sm mt-1">
                            of {{ profile.rank.global_total?.toLocaleString() }} users
                            <span v-if="profile.rank.percentile > 0" class="text-emerald-400">
                                &middot; Top {{ profile.rank.percentile }}%
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Locations -->
                <div class="bg-white/5 border border-white/10 rounded-xl p-6 mb-6">
                    <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-4">Impact</div>
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div>
                            <div class="text-white text-2xl font-bold tabular-nums">{{ profile.locations.countries }}</div>
                            <div class="text-white/40 text-sm">Countries</div>
                        </div>
                        <div>
                            <div class="text-white text-2xl font-bold tabular-nums">{{ profile.locations.states }}</div>
                            <div class="text-white/40 text-sm">States</div>
                        </div>
                        <div>
                            <div class="text-white text-2xl font-bold tabular-nums">{{ profile.locations.cities }}</div>
                            <div class="text-white/40 text-sm">Cities</div>
                        </div>
                    </div>
                </div>

                <div class="text-center">
                    <router-link to="/leaderboard" class="text-emerald-400 hover:text-emerald-300 transition text-sm">
                        &larr; Back to Leaderboard
                    </router-link>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
const loading = ref(true);
const profile = ref(null);

const displayName = computed(() => {
    if (!profile.value?.user) return 'User';
    return profile.value.user.name || profile.value.user.username || 'User';
});

const userInitial = computed(() => {
    const name = displayName.value;
    return name.charAt(0).toUpperCase();
});

onMounted(async () => {
    try {
        const { data } = await axios.get(`/api/user/profile/${route.params.id}`);
        profile.value = data;
    } catch {
        profile.value = { public: false };
    } finally {
        loading.value = false;
    }
});
</script>
