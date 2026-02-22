<template>
    <div>
        <h1 class="text-2xl font-bold text-slate-800 mb-6">Team Settings</h1>

        <p v-if="!hasTeams" class="text-slate-500">You haven't joined any teams yet.</p>

        <template v-else>
            <!-- Privacy Settings -->
            <section class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <div>
                    <h2 class="text-lg font-semibold text-slate-700 mb-2">Privacy</h2>
                    <p class="text-sm text-slate-500">
                        Control how your name and username appear on team maps and leaderboards.
                    </p>
                </div>

                <div class="lg:col-span-2 bg-white rounded-xl p-6 shadow-sm space-y-5">
                    <select
                        v-model="privacyTeamId"
                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white"
                    >
                        <option v-for="team in teams" :key="team.id" :value="team.id">
                            {{ team.name }}
                        </option>
                    </select>

                    <div v-if="privacyTeam">
                        <!-- Maps -->
                        <h3 class="text-sm font-semibold text-slate-600 mb-2">Team Map</h3>
                        <label class="flex items-center gap-2 mb-1 cursor-pointer">
                            <input type="checkbox" v-model="privacy.show_name_maps" class="rounded" />
                            <span class="text-sm">Show my name</span>
                        </label>
                        <label class="flex items-center gap-2 mb-3 cursor-pointer">
                            <input type="checkbox" v-model="privacy.show_username_maps" class="rounded" />
                            <span class="text-sm">Show my username</span>
                        </label>

                        <p
                            v-if="!privacy.show_name_maps && !privacy.show_username_maps"
                            class="text-xs text-red-500 mb-3"
                        >
                            You won't appear on the team map.
                        </p>

                        <!-- Leaderboards -->
                        <h3 class="text-sm font-semibold text-slate-600 mb-2 mt-4">Team Leaderboard</h3>
                        <label class="flex items-center gap-2 mb-1 cursor-pointer">
                            <input type="checkbox" v-model="privacy.show_name_leaderboards" class="rounded" />
                            <span class="text-sm">Show my name</span>
                        </label>
                        <label class="flex items-center gap-2 mb-3 cursor-pointer">
                            <input type="checkbox" v-model="privacy.show_username_leaderboards" class="rounded" />
                            <span class="text-sm">Show my username</span>
                        </label>

                        <div class="flex gap-3 pt-3 border-t border-slate-100">
                            <button
                                :disabled="privacySaving"
                                class="px-4 py-2 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
                                @click="savePrivacy(false)"
                            >
                                Save for this team
                            </button>
                            <button
                                :disabled="privacySaving"
                                class="px-4 py-2 text-sm font-medium rounded-lg bg-slate-200 text-slate-700 hover:bg-slate-300 disabled:opacity-50"
                                @click="savePrivacy(true)"
                            >
                                Apply to all teams
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Team Attributes (leader only) -->
            <section v-if="teamsLed.length" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div>
                    <h2 class="text-lg font-semibold text-slate-700 mb-2">Edit Team</h2>
                    <p class="text-sm text-slate-500">Update the name or identifier for teams you lead.</p>
                </div>

                <div class="lg:col-span-2 bg-white rounded-xl p-6 shadow-sm">
                    <form class="space-y-4" @submit.prevent="submitUpdate">
                        <select
                            v-model="editTeamId"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white"
                        >
                            <option v-for="team in teamsLed" :key="team.id" :value="team.id">
                                {{ team.name }}
                            </option>
                        </select>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Team name</label>
                            <input
                                v-model="editName"
                                type="text"
                                required
                                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                                :class="errors.name ? 'border-red-400' : ''"
                                @input="teamsStore.clearError('name')"
                            />
                            <p v-if="errors.name" class="text-red-500 text-xs mt-1">{{ errors.name[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Team identifier</label>
                            <input
                                v-model="editIdentifier"
                                type="text"
                                required
                                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                                :class="errors.identifier ? 'border-red-400' : ''"
                                @input="teamsStore.clearError('identifier')"
                            />
                            <p v-if="errors.identifier" class="text-red-500 text-xs mt-1">{{ errors.identifier[0] }}</p>
                        </div>

                        <button
                            type="submit"
                            :disabled="updating"
                            class="px-4 py-2 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ updating ? 'Saving...' : 'Update Team' }}
                        </button>
                    </form>
                </div>
            </section>
        </template>
    </div>
</template>

<script>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useTeamsStore } from '@/stores/teams';
import { useUserStore } from '@/stores/user';

export default {
    name: 'TeamSettings',
    setup() {
        const teamsStore = useTeamsStore();
        const userStore = useUserStore();

        // ── Privacy ──
        const privacyTeamId = ref(null);
        const privacySaving = ref(false);

        const privacy = reactive({
            show_name_maps: false,
            show_username_maps: false,
            show_name_leaderboards: false,
            show_username_leaderboards: false,
        });

        const teams = computed(() => teamsStore.teams);
        const hasTeams = computed(() => teamsStore.hasTeams);
        const errors = computed(() => teamsStore.errors);

        const privacyTeam = computed(() => teams.value.find((t) => t.id === privacyTeamId.value));

        // Sync privacy checkboxes when selected team changes
        watch(
            privacyTeamId,
            () => {
                const pivot = privacyTeam.value?.pivot;
                if (pivot) {
                    privacy.show_name_maps = !!pivot.show_name_maps;
                    privacy.show_username_maps = !!pivot.show_username_maps;
                    privacy.show_name_leaderboards = !!pivot.show_name_leaderboards;
                    privacy.show_username_leaderboards = !!pivot.show_username_leaderboards;
                }
            },
            { immediate: true }
        );

        const savePrivacy = async (all) => {
            privacySaving.value = true;
            await teamsStore.savePrivacySettings({
                teamId: privacyTeamId.value,
                all,
                settings: { ...privacy },
            });
            privacySaving.value = false;
        };

        // ── Edit team attributes (leader only) ──
        const editTeamId = ref(null);
        const editName = ref('');
        const editIdentifier = ref('');
        const updating = ref(false);

        const teamsLed = computed(() => {
            const uid = userStore.user?.id;
            return teams.value.filter((t) => t.leader === uid);
        });

        // Sync edit form when selected team changes
        watch(editTeamId, (id) => {
            const team = teamsLed.value.find((t) => t.id === id);
            if (team) {
                editName.value = team.name;
                editIdentifier.value = team.identifier;
            }
        });

        const submitUpdate = async () => {
            updating.value = true;

            const result = await teamsStore.updateTeam({
                teamId: editTeamId.value,
                name: editName.value,
                identifier: editIdentifier.value,
            });

            updating.value = false;

            if (result) {
                await teamsStore.fetchMyTeams();
            }
        };

        // ── Init ──
        onMounted(async () => {
            if (!hasTeams.value) await teamsStore.fetchMyTeams();
            teamsStore.clearErrors();

            privacyTeamId.value = teams.value[0]?.id ?? null;
            editTeamId.value = teamsLed.value[0]?.id ?? null;
        });

        return {
            teams,
            hasTeams,
            errors,
            teamsStore,
            privacyTeamId,
            privacyTeam,
            privacy,
            privacySaving,
            savePrivacy,
            editTeamId,
            editName,
            editIdentifier,
            updating,
            teamsLed,
            submitUpdate,
        };
    },
};
</script>
