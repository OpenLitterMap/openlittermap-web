<template>
    <div class="space-y-8 max-w-3xl">
        <!-- Edit Team (leader only) -->
        <section v-if="isLeader" class="bg-white/5 border border-white/10 rounded-xl p-6">
            <h2 class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-4">{{ $t('Team Details') }}</h2>

            <div class="space-y-4">
                <SettingsField
                    :label="$t('Name')"
                    :value="team?.name ?? ''"
                    @save="(val) => submitUpdate('name', val)"
                />
                <SettingsField
                    :label="$t('Identifier')"
                    :value="team?.identifier ?? ''"
                    @save="(val) => submitUpdate('identifier', val)"
                />
                <p v-if="updateError" class="text-red-400 text-xs">{{ updateError }}</p>
            </div>
        </section>

        <!-- Privacy & Safeguarding (school teams) -->
        <section v-if="isSchoolTeam" class="bg-white/5 border border-white/10 rounded-xl p-6">
            <h2 class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-4">{{ $t('Privacy & Safeguarding') }}</h2>

            <div class="space-y-4">
                <!-- Safeguarding is always on for school teams — informational -->
                <div class="flex items-center justify-between gap-4 py-1">
                    <div>
                        <div class="text-white text-sm">{{ $t('Mask student identities') }}</div>
                        <div class="text-white/30 text-xs">{{ $t('Student names and usernames are never visible. All contributions attributed to the school.') }}</div>
                    </div>
                    <div class="relative w-11 h-6 rounded-full bg-emerald-500 opacity-60 cursor-not-allowed">
                        <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full translate-x-5"></span>
                    </div>
                </div>

                <!-- Participant sessions toggle (leader only) -->
                <SettingsToggle
                    v-if="isLeader"
                    :label="$t('Participant sessions')"
                    :description="$t('Allow students to upload via session codes instead of creating accounts.')"
                    :value="!!team?.participant_sessions_enabled"
                    @toggle="toggleParticipantSessions"
                />
            </div>
        </section>

        <!-- Privacy Settings (community teams only) -->
        <section v-if="!isSchoolTeam" class="bg-white/5 border border-white/10 rounded-xl p-6">
            <h2 class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-4">{{ $t('Privacy') }}</h2>
            <p class="text-sm text-white/30 mb-4">
                {{ $t('Control how your name and username appear on this team\'s maps and leaderboards.') }}
            </p>

            <div v-if="privacyTeam" class="space-y-3">
                <p class="text-white/50 text-[11px] font-semibold uppercase tracking-widest">{{ $t('Team Map') }}</p>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" v-model="privacy.show_name_maps" class="rounded bg-white/5 border-white/20 text-emerald-500 focus:ring-emerald-500" />
                    <span class="text-sm text-white/70">{{ $t('Show my name') }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" v-model="privacy.show_username_maps" class="rounded bg-white/5 border-white/20 text-emerald-500 focus:ring-emerald-500" />
                    <span class="text-sm text-white/70">{{ $t('Show my username') }}</span>
                </label>

                <p
                    v-if="!privacy.show_name_maps && !privacy.show_username_maps"
                    class="text-xs text-amber-400"
                >
                    {{ $t("You won't appear on the team map.") }}
                </p>

                <p class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mt-4">{{ $t('Team Leaderboard') }}</p>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" v-model="privacy.show_name_leaderboards" class="rounded bg-white/5 border-white/20 text-emerald-500 focus:ring-emerald-500" />
                    <span class="text-sm text-white/70">{{ $t('Show my name') }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" v-model="privacy.show_username_leaderboards" class="rounded bg-white/5 border-white/20 text-emerald-500 focus:ring-emerald-500" />
                    <span class="text-sm text-white/70">{{ $t('Show my username') }}</span>
                </label>

                <div class="flex gap-3 pt-4 border-t border-white/10">
                    <button
                        :disabled="privacySaving"
                        class="px-4 py-2 text-sm font-medium rounded-lg bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/30 disabled:opacity-50 transition-colors"
                        @click="savePrivacy(false)"
                    >
                        {{ $t('Save') }}
                    </button>
                    <button
                        v-if="teams.length > 1"
                        :disabled="privacySaving"
                        class="px-4 py-2 text-sm font-medium rounded-lg bg-white/5 text-white/60 border border-white/10 hover:bg-white/10 disabled:opacity-50 transition-colors"
                        @click="savePrivacy(true)"
                    >
                        {{ $t('Apply to all teams') }}
                    </button>
                </div>
            </div>
        </section>

        <!-- Visibility & Data (leader only) -->
        <section v-if="isLeader" class="bg-white/5 border border-white/10 rounded-xl p-6">
            <h2 class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-4">{{ $t('Visibility & Data') }}</h2>

            <div class="space-y-4">
                <SettingsToggle
                    :label="$t('Show on global leaderboard')"
                    :description="$t('When enabled, this team appears on the public teams leaderboard.')"
                    :value="!!team?.leaderboards"
                    @toggle="toggleLeaderboard"
                />

                <div class="flex items-center justify-between pt-4 border-t border-white/10">
                    <div>
                        <p class="text-sm text-white">{{ $t('Download team data') }}</p>
                        <p class="text-xs text-white/30">{{ $t('Export all team photo data as CSV.') }}</p>
                    </div>
                    <button
                        class="px-3 py-1.5 text-sm font-medium rounded-lg border border-white/20 text-white/60 hover:bg-white/5 transition-colors"
                        @click="download"
                    >
                        <i class="fa fa-download mr-1" /> {{ $t('Download') }}
                    </button>
                </div>
            </div>
        </section>

        <!-- Danger Zone -->
        <section class="bg-white/5 border border-red-500/20 rounded-xl p-6">
            <h2 class="text-red-400 text-[11px] font-semibold uppercase tracking-widest mb-4">{{ $t('Danger Zone') }}</h2>

            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-white">{{ $t('Leave this team') }}</p>
                    <p class="text-xs text-white/30">
                        {{ team?.members <= 1
                            ? $t('You are the only member — leaving will leave the team empty.')
                            : $t('Your uploads will remain attributed to this team.')
                        }}
                    </p>
                </div>
                <button
                    class="px-3 py-1.5 text-sm font-medium rounded-lg bg-red-500/10 text-red-400 border border-red-500/20 hover:bg-red-500/20 transition-colors"
                    @click="leaveTeam"
                >
                    {{ $t('Leave Team') }}
                </button>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useToast } from 'vue-toastification';
import { useTeamsStore } from '@/stores/teams';
import SettingsField from '@/views/Profile/components/SettingsField.vue';
import SettingsToggle from '@/views/Profile/components/SettingsToggle.vue';

const props = defineProps({
    teamId: Number,
    team: Object,
    teams: Array,
    isLeader: Boolean,
    isSchoolTeam: Boolean,
});

const { t } = useI18n();
const router = useRouter();
const toast = useToast();
const teamsStore = useTeamsStore();

// ── Edit team ──
const updateError = ref('');

const submitUpdate = async (field, value) => {
    updateError.value = '';
    const payload = {
        teamId: props.teamId,
        name: props.team?.name,
        identifier: props.team?.identifier,
        [field]: value,
    };

    const result = await teamsStore.updateTeam(payload);

    if (result) {
        toast.success(t('Team updated.'));
        await teamsStore.fetchMyTeams();
    } else {
        const errors = teamsStore.errors;
        updateError.value = errors[field]?.[0] || t('Failed to update.');
    }
};

// ── Privacy ──
const privacySaving = ref(false);
const privacy = reactive({
    show_name_maps: false,
    show_username_maps: false,
    show_name_leaderboards: false,
    show_username_leaderboards: false,
});

const privacyTeam = computed(() =>
    props.teams?.find((t) => t.id === props.teamId),
);

watch(() => props.teamId, () => {
    const pivot = privacyTeam.value?.pivot;
    if (pivot) {
        privacy.show_name_maps = !!pivot.show_name_maps;
        privacy.show_username_maps = !!pivot.show_username_maps;
        privacy.show_name_leaderboards = !!pivot.show_name_leaderboards;
        privacy.show_username_leaderboards = !!pivot.show_username_leaderboards;
    }
}, { immediate: true });

const savePrivacy = async (all) => {
    privacySaving.value = true;
    await teamsStore.savePrivacySettings({
        teamId: props.teamId,
        all,
        settings: { ...privacy },
    });
    privacySaving.value = false;
    toast.success(t('Privacy settings saved.'));
};

// ── Toggles ──
const toggleLeaderboard = () => teamsStore.toggleLeaderboardVisibility(props.teamId);

const toggleParticipantSessions = async () => {
    const newValue = !props.team?.participant_sessions_enabled;
    const result = await teamsStore.updateTeam({
        teamId: props.teamId,
        name: props.team?.name,
        identifier: props.team?.identifier,
        participant_sessions_enabled: newValue,
    });

    if (result) {
        toast.success(t('Participant sessions updated.'));
        await teamsStore.fetchMyTeams();
    } else {
        toast.error(t('Failed to update participant sessions.'));
    }
};

// ── Data ──
const download = () => teamsStore.downloadTeamData(props.teamId);

// ── Leave ──
const leaveTeam = async () => {
    if (!confirm(t('Are you sure you want to leave this team?'))) return;

    await teamsStore.leaveTeam(props.teamId);

    if (!teamsStore.hasTeams) {
        router.push('/teams');
    }
};
</script>
