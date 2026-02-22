<template>
    <div class="max-w-lg">
        <h1 class="text-2xl font-bold text-slate-800 mb-2">Join a Team</h1>
        <p class="text-slate-500 mb-6">Enter the team identifier shared by your team leader.</p>

        <form class="bg-white rounded-xl p-6 shadow-sm space-y-5" @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Team identifier</label>
                <input
                    v-model="identifier"
                    type="text"
                    required
                    autofocus
                    placeholder="e.g. Awesome2026"
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
                {{ processing ? 'Joining...' : 'Join Team' }}
            </button>

            <p v-if="successMessage" class="text-green-600 text-sm font-medium">{{ successMessage }}</p>
        </form>
    </div>
</template>

<script>
import { ref, computed } from 'vue';
import { useTeamsStore } from '@/stores/teams';

export default {
    name: 'JoinTeam',
    setup() {
        const teamsStore = useTeamsStore();

        const identifier = ref('');
        const processing = ref(false);
        const successMessage = ref('');

        const errors = computed(() => teamsStore.errors);

        const submit = async () => {
            processing.value = true;
            successMessage.value = '';

            const team = await teamsStore.joinTeam(identifier.value);

            processing.value = false;

            if (team) {
                successMessage.value = `Joined "${team.name}" successfully!`;
                identifier.value = '';
            }
        };

        teamsStore.clearErrors();

        return { identifier, processing, successMessage, errors, submit, teamsStore };
    },
};
</script>
