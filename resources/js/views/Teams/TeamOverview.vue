<template>
    <div class="space-y-8">
        <!-- Getting Started Checklist (shown until first photo is approved) -->
        <div v-if="showGettingStarted" class="bg-white/5 border border-white/10 rounded-xl p-6">
            <h2 class="text-lg font-semibold text-white mb-4">{{ $t('Getting Started') }}</h2>

            <div class="space-y-3">
                <!-- 1. Create team (always done) -->
                <div class="flex items-center gap-3">
                    <div class="w-6 h-6 rounded-full bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <span class="text-sm text-white/60 line-through">{{ $t('Create your team') }}</span>
                </div>

                <!-- 2. Share join code OR set up sessions -->
                <div class="flex items-center gap-3">
                    <div
                        class="w-6 h-6 rounded-full flex items-center justify-center shrink-0"
                        :class="onboardingDone
                            ? 'bg-emerald-500/20 border border-emerald-500/40'
                            : 'bg-white/5 border border-white/20'"
                    >
                        <svg v-if="onboardingDone" class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                        <span v-else class="w-2 h-2 rounded-full bg-white/20"></span>
                    </div>
                    <span class="text-sm" :class="onboardingDone ? 'text-white/60 line-through' : 'text-white'">
                        {{ team?.participant_sessions_enabled
                            ? $t('Set up participant sessions')
                            : $t('Share your join code with students')
                        }}
                    </span>
                    <button
                        v-if="!onboardingDone && team?.participant_sessions_enabled"
                        class="ml-auto text-xs text-emerald-400 hover:text-emerald-300 transition-colors"
                        @click="$emit('switch-tab', 'participants')"
                    >
                        {{ $t('Set up') }} &rarr;
                    </button>
                </div>

                <!-- 3. First photo uploaded -->
                <div class="flex items-center gap-3">
                    <div
                        class="w-6 h-6 rounded-full flex items-center justify-center shrink-0"
                        :class="hasPhotos
                            ? 'bg-emerald-500/20 border border-emerald-500/40'
                            : 'bg-white/5 border border-white/20'"
                    >
                        <svg v-if="hasPhotos" class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                        <span v-else class="w-2 h-2 rounded-full bg-white/20"></span>
                    </div>
                    <span class="text-sm" :class="hasPhotos ? 'text-white/60 line-through' : 'text-white'">
                        {{ $t('First photo uploaded') }}
                    </span>
                </div>

                <!-- 4. First photo approved (school only) -->
                <div v-if="isSchoolTeam" class="flex items-center gap-3">
                    <div
                        class="w-6 h-6 rounded-full flex items-center justify-center shrink-0"
                        :class="hasApproved
                            ? 'bg-emerald-500/20 border border-emerald-500/40'
                            : 'bg-white/5 border border-white/20'"
                    >
                        <svg v-if="hasApproved" class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                        <span v-else class="w-2 h-2 rounded-full bg-white/20"></span>
                    </div>
                    <span class="text-sm" :class="hasApproved ? 'text-white/60 line-through' : 'text-white'">
                        {{ $t('First photo approved') }}
                    </span>
                    <button
                        v-if="hasPhotos && !hasApproved"
                        class="ml-auto text-xs text-emerald-400 hover:text-emerald-300 transition-colors"
                        @click="$emit('switch-tab', 'approval')"
                    >
                        {{ $t('Review') }} &rarr;
                    </button>
                </div>
            </div>

            <!-- Test your setup hint -->
            <div v-if="onboardingDone && !hasPhotos" class="mt-4 bg-blue-500/10 border border-blue-500/20 rounded-lg px-4 py-3">
                <p class="text-sm text-blue-300">
                    {{ $t('Try it yourself — upload a test photo as a student would, then approve it here. This takes 2 minutes and you\'ll see exactly how the review process works.') }}
                </p>
            </div>

            <!-- Join code block -->
            <div v-if="!team?.participant_sessions_enabled" class="mt-6 pt-5 border-t border-white/10">
                <p class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-2">{{ $t('Your join code') }}</p>
                <div class="flex items-center gap-3">
                    <code class="text-lg font-mono font-bold text-white bg-white/5 border border-white/10 px-4 py-2 rounded-lg tracking-wider">
                        {{ team?.identifier }}
                    </code>
                    <button
                        class="px-3 py-2 text-sm font-medium text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 rounded-lg hover:bg-emerald-500/20 transition-colors"
                        @click="copyIdentifier"
                    >
                        {{ $t('Copy') }}
                    </button>
                </div>
                <p class="text-white/30 text-xs mt-2">
                    {{ $t('Share this code with students so they can join your team after creating an account.') }}
                </p>
            </div>

            <!-- Onboarding paths explainer (school teams) -->
            <div v-if="isSchoolTeam && !onboardingDone" class="mt-6 pt-5 border-t border-white/10">
                <p class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-3">{{ $t('Two ways to onboard students') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="bg-white/5 border border-white/10 rounded-lg p-4">
                        <p class="text-sm font-medium text-white mb-1">{{ $t('Join code') }}</p>
                        <p class="text-xs text-white/40">
                            {{ $t('Students create their own OpenLitterMap accounts and join your team. Best for older students or ongoing programs.') }}
                        </p>
                    </div>
                    <div class="bg-white/5 border border-white/10 rounded-lg p-4">
                        <p class="text-sm font-medium text-white mb-1">{{ $t('Participant sessions') }}</p>
                        <p class="text-xs text-white/40">
                            {{ $t('You create numbered slots. Students enter a code — no accounts needed. Best for younger students or one-off workshops.') }}
                        </p>
                        <button
                            class="text-xs text-emerald-400 hover:text-emerald-300 mt-2 transition-colors"
                            @click="$emit('switch-tab', 'settings')"
                        >
                            {{ $t('Enable in settings') }} &rarr;
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Team Info -->
        <div class="bg-white/5 border border-white/10 rounded-xl p-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div>
                    <p class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">{{ $t('Team Identifier') }}</p>
                    <div class="flex items-center gap-2">
                        <code class="text-sm font-mono bg-white/5 border border-white/10 px-2 py-1 rounded text-white">{{ team?.identifier }}</code>
                        <button
                            class="p-1 text-white/30 hover:text-white/60 transition-colors"
                            :title="$t('Copy identifier')"
                            @click="copyIdentifier"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </button>
                    </div>
                    <p class="text-xs text-white/30 mt-1">{{ $t('Share this code so others can join.') }}</p>
                </div>
                <div>
                    <p class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">{{ $t('Created') }}</p>
                    <p class="text-sm text-white/70">{{ formatDate(team?.created_at) }}</p>
                </div>
                <div>
                    <p class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">{{ $t('Members') }}</p>
                    <p class="text-sm text-white/70">{{ team?.members ?? 0 }}</p>
                </div>
            </div>
        </div>

        <!-- All My Teams -->
        <div v-if="teams.length > 1">
            <h2 class="text-lg font-semibold text-white mb-3">{{ $t('All My Teams') }}</h2>

            <div class="bg-white/5 border border-white/10 rounded-xl overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-white/10">
                        <tr>
                            <th
                                class="px-4 py-3 font-medium text-left text-[11px] uppercase tracking-widest cursor-pointer select-none transition-colors"
                                :class="sortKey === 'name' ? 'text-emerald-400' : 'text-white/50 hover:text-white/70'"
                                @click="toggleSort('name')"
                            >
                                {{ $t('Name') }}
                                <span v-if="sortKey === 'name'" class="ml-0.5">{{ sortAsc ? '↑' : '↓' }}</span>
                            </th>
                            <th class="px-4 py-3 font-medium text-white/50 text-left text-[11px] uppercase tracking-widest">{{ $t('Type') }}</th>
                            <th
                                class="px-4 py-3 font-medium text-right text-[11px] uppercase tracking-widest cursor-pointer select-none transition-colors"
                                :class="sortKey === 'members' ? 'text-emerald-400' : 'text-white/50 hover:text-white/70'"
                                @click="toggleSort('members')"
                            >
                                {{ $t('Members') }}
                                <span v-if="sortKey === 'members'" class="ml-0.5">{{ sortAsc ? '↑' : '↓' }}</span>
                            </th>
                            <th
                                class="px-4 py-3 font-medium text-right text-[11px] uppercase tracking-widest cursor-pointer select-none transition-colors"
                                :class="sortKey === 'photos' ? 'text-emerald-400' : 'text-white/50 hover:text-white/70'"
                                @click="toggleSort('photos')"
                            >
                                {{ $t('Photos') }}
                                <span v-if="sortKey === 'photos'" class="ml-0.5">{{ sortAsc ? '↑' : '↓' }}</span>
                            </th>
                            <th
                                class="px-4 py-3 font-medium text-right text-[11px] uppercase tracking-widest cursor-pointer select-none transition-colors"
                                :class="sortKey === 'tags' ? 'text-emerald-400' : 'text-white/50 hover:text-white/70'"
                                @click="toggleSort('tags')"
                            >
                                {{ $t('Total Tags') }}
                                <span v-if="sortKey === 'tags'" class="ml-0.5">{{ sortAsc ? '↑' : '↓' }}</span>
                            </th>
                            <th class="px-4 py-3 w-20"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <tr
                            v-for="t in sortedTeams"
                            :key="t.id"
                            class="hover:bg-white/[0.03]"
                            :class="t.id === teamId ? 'bg-white/[0.05]' : ''"
                        >
                            <td class="px-4 py-3 font-medium text-white">{{ t.name }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider rounded-full"
                                    :class="t.type_name === 'school'
                                        ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                                        : 'bg-blue-500/20 text-blue-400 border border-blue-500/30'"
                                >
                                    {{ t.type_name === 'school' ? $t('School') : $t('Community') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-white/70">{{ t.members }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-white/70">{{ t.total_photos }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-white/70">{{ t.total_tags }}</td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    v-if="t.id === teamId"
                                    class="text-xs text-emerald-400 font-medium"
                                >{{ $t('Active') }}</span>
                                <button
                                    v-else
                                    class="text-xs text-white/40 hover:text-emerald-400 font-medium transition-colors"
                                    @click="$emit('switch-team', t.id)"
                                >
                                    {{ $t('Switch') }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useToast } from 'vue-toastification';
import { useTeamPhotosStore } from '@/stores/teamPhotos';

const props = defineProps({
    teamId: Number,
    team: Object,
    teams: Array,
    isLeader: Boolean,
    isSchoolTeam: Boolean,
});

defineEmits(['switch-team', 'switch-tab']);

// ── Sorting ──
const sortKey = ref('photos');
const sortAsc = ref(false);

const sortedTeams = computed(() => {
    if (!props.teams) return [];

    const active = props.teams.filter((t) => t.id === props.teamId);
    const rest = props.teams.filter((t) => t.id !== props.teamId);

    const sorted = [...rest].sort((a, b) => {
        let cmp = 0;

        if (sortKey.value === 'name') {
            cmp = (a.name || '').localeCompare(b.name || '');
        } else if (sortKey.value === 'members') {
            cmp = (a.members || 0) - (b.members || 0);
        } else if (sortKey.value === 'photos') {
            cmp = (a.total_photos || 0) - (b.total_photos || 0);
        } else if (sortKey.value === 'tags') {
            cmp = (a.total_tags || 0) - (b.total_tags || 0);
        }

        return sortAsc.value ? cmp : -cmp;
    });

    return [...active, ...sorted];
});

const toggleSort = (key) => {
    if (sortKey.value === key) {
        sortAsc.value = !sortAsc.value;
    } else {
        sortKey.value = key;
        sortAsc.value = key === 'name';
    }
};

const toast = useToast();
const photosStore = useTeamPhotosStore();

const hasPhotos = computed(() => photosStore.stats.total > 0);
const hasApproved = computed(() => photosStore.stats.approved > 0);
const onboardingDone = computed(() => {
    if (props.team?.participant_sessions_enabled) {
        // Check if any participants exist (members > 1 is a proxy — leader is always 1)
        return (props.team?.members ?? 0) > 1;
    }
    return (props.team?.members ?? 0) > 1;
});

const showGettingStarted = computed(() => {
    if (!props.isSchoolTeam) {
        // Community teams: show until first photo
        return !hasPhotos.value;
    }
    // School teams: show until first approved photo
    return !hasApproved.value;
});

const copyIdentifier = async () => {
    if (!props.team?.identifier) return;

    try {
        await navigator.clipboard.writeText(props.team.identifier);
        toast.success('Identifier copied!');
    } catch {
        const el = document.createElement('textarea');
        el.value = props.team.identifier;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        toast.success('Identifier copied!');
    }
};

const formatDate = (date) => {
    if (!date) return '—';

    return new Intl.DateTimeFormat('en-IE', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    }).format(new Date(date));
};
</script>
