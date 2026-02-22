<template>
    <div class="max-w-lg">
        <h1 class="text-2xl font-bold text-slate-800 mb-2">Create a Team</h1>
        <p class="text-slate-500 mb-6">You can create {{ remaining }} more team{{ remaining !== 1 ? 's' : '' }}.</p>

        <form v-if="remaining > 0" class="bg-white rounded-xl p-6 shadow-sm space-y-5" @submit.prevent="submit">
            <!-- Team Type -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Team type</label>
                <select v-model="teamType" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <option v-for="t in types" :key="t.id" :value="t.id">{{ t.team }}</option>
                </select>
            </div>

            <!-- Team Name -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Team name</label>
                <input
                    v-model="name"
                    type="text"
                    required
                    placeholder="My Awesome Team"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                    :class="errors.name ? 'border-red-400' : ''"
                    @input="teamsStore.clearError('name')"
                />
                <p v-if="errors.name" class="text-red-500 text-xs mt-1">{{ errors.name[0] }}</p>
            </div>

            <!-- Identifier -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Unique team ID</label>
                <p class="text-xs text-slate-400 mb-1">Share this code so others can join your team.</p>
                <input
                    v-model="identifier"
                    type="text"
                    required
                    placeholder="Awesome2026"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                    :class="errors.identifier ? 'border-red-400' : ''"
                    @input="teamsStore.clearError('identifier')"
                />
                <p v-if="errors.identifier" class="text-red-500 text-xs mt-1">{{ errors.identifier[0] }}</p>
            </div>

            <button
                type="submit"
                :disabled="processing"
                class="w-full py-2 rounded-lg text-white font-medium text-sm transition-colors"
                :class="processing ? 'bg-slate-400 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'"
            >
                {{ processing ? 'Creating...' : 'Create Team' }}
            </button>
        </form>

        <div v-else class="bg-amber-50 border border-amber-200 rounded-xl p-6">
            <p class="text-amber-800">You've reached the maximum number of teams you can create.</p>
        </div>
    </div>
</template>

<script>
import { ref, computed } from 'vue';
import { useTeamsStore } from '@/stores/teams';
import { useUserStore } from '@/stores/user';

export default {
    name: 'CreateTeam',
    setup() {
        const teamsStore = useTeamsStore();
        const userStore = useUserStore();

        const name = ref('');
        const identifier = ref('');
        const teamType = ref(1);
        const processing = ref(false);

        const types = computed(() => teamsStore.types);
        const errors = computed(() => teamsStore.errors);
        const remaining = computed(() => userStore.user?.remaining_teams ?? 0);

        const submit = async () => {
            processing.value = true;

            const team = await teamsStore.createTeam({
                name: name.value,
                identifier: identifier.value,
                teamType: teamType.value,
            });

            processing.value = false;

            if (team) {
                name.value = '';
                identifier.value = '';
            }
        };

        // Clear errors on mount
        teamsStore.clearErrors();

        return { name, identifier, teamType, processing, types, errors, remaining, submit, teamsStore };
    },
};
</script>
