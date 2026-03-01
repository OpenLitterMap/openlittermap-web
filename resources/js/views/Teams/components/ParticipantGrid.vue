<template>
    <div>
        <!-- Create Slots -->
        <div class="flex items-center gap-4 mb-6">
            <input
                v-model.number="slotCount"
                type="number"
                min="1"
                max="100"
                placeholder="5"
                class="w-24 border border-slate-300 rounded-lg px-3 py-2 text-sm"
            />
            <button
                :disabled="creating"
                class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors"
                :class="creating ? 'bg-slate-400 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'"
                @click="createSlots"
            >
                {{ creating ? 'Creating...' : 'Create Slots' }}
            </button>
            <button
                v-if="participants.length > 0"
                class="px-4 py-2 text-sm font-medium text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors"
                @click="showPrintView"
            >
                Print Session Cards
            </button>
        </div>

        <p v-if="error" class="text-red-500 text-sm mb-4">{{ error }}</p>

        <!-- Empty state -->
        <div v-if="!loading && participants.length === 0" class="text-center py-12 text-slate-400">
            No participant slots created yet. Create slots above to get started.
        </div>

        <!-- Loading -->
        <div v-else-if="loading" class="text-center py-12 text-slate-400">Loading...</div>

        <!-- Grid -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="p in participants"
                :key="p.id"
                class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm"
                :class="!p.is_active ? 'opacity-50' : ''"
            >
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <span class="text-xs font-mono text-slate-400">Slot {{ p.slot_number }}</span>
                        <h3 class="font-medium text-slate-800">{{ p.display_name }}</h3>
                    </div>
                    <span
                        class="text-xs px-2 py-0.5 rounded-full"
                        :class="p.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                    >
                        {{ p.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                <div class="text-sm text-slate-500 space-y-1 mb-3">
                    <p>Photos: {{ p.photo_count ?? 0 }}</p>
                    <p v-if="p.last_active_at">Last active: {{ formatDate(p.last_active_at) }}</p>
                </div>

                <!-- Token display (only after create/reset) -->
                <div v-if="revealedTokens[p.id]" class="mb-3">
                    <p class="text-xs text-slate-500 mb-1">Session Code:</p>
                    <div class="flex items-center gap-2">
                        <code class="text-xs bg-slate-100 px-2 py-1 rounded font-mono break-all flex-1">
                            {{ revealedTokens[p.id] }}
                        </code>
                        <button
                            class="text-xs text-blue-600 hover:text-blue-700 whitespace-nowrap"
                            @click="copyToken(p.id)"
                        >
                            Copy
                        </button>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-2 flex-wrap">
                    <button
                        v-if="p.is_active"
                        class="text-xs px-2 py-1 text-amber-600 border border-amber-200 rounded hover:bg-amber-50"
                        @click="deactivate(p.id)"
                    >
                        Deactivate
                    </button>
                    <button
                        v-else
                        class="text-xs px-2 py-1 text-green-600 border border-green-200 rounded hover:bg-green-50"
                        @click="activate(p.id)"
                    >
                        Activate
                    </button>
                    <button
                        class="text-xs px-2 py-1 text-blue-600 border border-blue-200 rounded hover:bg-blue-50"
                        @click="resetToken(p.id)"
                    >
                        Reset Token
                    </button>
                    <button
                        class="text-xs px-2 py-1 text-red-600 border border-red-200 rounded hover:bg-red-50"
                        @click="deleteParticipant(p.id)"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>

        <!-- Print modal -->
        <div
            v-if="showPrint"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
            @click.self="showPrint = false"
        >
            <div class="bg-white rounded-xl p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-slate-800">Session Cards</h2>
                    <div class="flex gap-2">
                        <button
                            class="px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700"
                            @click="printCards"
                        >
                            Print
                        </button>
                        <button
                            class="px-3 py-1.5 text-sm text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50"
                            @click="showPrint = false"
                        >
                            Close
                        </button>
                    </div>
                </div>
                <div id="printable-cards" class="grid grid-cols-2 gap-4">
                    <div
                        v-for="p in activeParticipants"
                        :key="p.id"
                        class="border border-slate-300 rounded-lg p-4 text-center"
                    >
                        <p class="text-sm text-slate-500">{{ team?.name }}</p>
                        <p class="text-lg font-bold text-slate-800 mt-1">{{ p.display_name }}</p>
                        <p class="text-xs text-slate-400 mt-1">Slot {{ p.slot_number }}</p>
                        <p v-if="revealedTokens[p.id]" class="text-xs font-mono mt-2 break-all text-slate-600">
                            {{ revealedTokens[p.id] }}
                        </p>
                        <p v-else class="text-xs mt-2 text-slate-400 italic">Token not available — reset to reveal</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import axios from 'axios';

const props = defineProps({
    teamId: {
        type: Number,
        required: true,
    },
    isLeader: Boolean,
    isSchoolTeam: Boolean,
});

const toast = useToast();

const participants = ref([]);
const revealedTokens = ref({});
const loading = ref(false);
const creating = ref(false);
const error = ref('');
const slotCount = ref(5);
const showPrint = ref(false);

const team = computed(() => {
    // Minimal team info for print view
    return { name: 'Team' };
});

const activeParticipants = computed(() => participants.value.filter((p) => p.is_active));

const formatDate = (dateStr) => {
    const date = new Date(dateStr);
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
};

const fetchParticipants = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(`/api/teams/${props.teamId}/participants`);
        participants.value = data.participants;
    } catch (e) {
        error.value = 'Failed to load participants.';
    } finally {
        loading.value = false;
    }
};

const createSlots = async () => {
    if (!slotCount.value || slotCount.value < 1) return;
    creating.value = true;
    error.value = '';

    try {
        const { data } = await axios.post(`/api/teams/${props.teamId}/participants`, {
            count: slotCount.value,
        });

        // Store revealed tokens
        for (const p of data.participants) {
            revealedTokens.value[p.id] = p.session_token;
        }

        toast.success(`${data.participants.length} slots created.`);
        await fetchParticipants();
    } catch (e) {
        error.value = e.response?.data?.message || 'Failed to create slots.';
    } finally {
        creating.value = false;
    }
};

const deactivate = async (id) => {
    try {
        await axios.post(`/api/teams/${props.teamId}/participants/${id}/deactivate`);
        const p = participants.value.find((p) => p.id === id);
        if (p) p.is_active = false;
    } catch (e) {
        toast.error('Failed to deactivate.');
    }
};

const activate = async (id) => {
    try {
        await axios.post(`/api/teams/${props.teamId}/participants/${id}/activate`);
        const p = participants.value.find((p) => p.id === id);
        if (p) p.is_active = true;
    } catch (e) {
        toast.error('Failed to activate.');
    }
};

const resetToken = async (id) => {
    try {
        const { data } = await axios.post(`/api/teams/${props.teamId}/participants/${id}/reset-token`);
        revealedTokens.value[id] = data.session_token;
        toast.success('Token reset. New code displayed.');
    } catch (e) {
        toast.error('Failed to reset token.');
    }
};

const deleteParticipant = async (id) => {
    if (!confirm('Delete this participant slot? Their photos will keep participant_id=null.')) return;

    try {
        await axios.delete(`/api/teams/${props.teamId}/participants/${id}`);
        participants.value = participants.value.filter((p) => p.id !== id);
        delete revealedTokens.value[id];
        toast.success('Participant deleted.');
    } catch (e) {
        toast.error('Failed to delete.');
    }
};

const copyToken = async (id) => {
    const token = revealedTokens.value[id];
    if (token) {
        await navigator.clipboard.writeText(token);
        toast.success('Copied to clipboard.');
    }
};

const showPrintView = () => {
    showPrint.value = true;
};

const printCards = () => {
    const content = document.getElementById('printable-cards');
    const win = window.open('', '_blank');
    win.document.write(`
        <html>
        <head><title>Session Cards</title>
        <style>
            body { font-family: sans-serif; }
            .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
            .card { border: 1px solid #ccc; border-radius: 8px; padding: 16px; text-align: center; page-break-inside: avoid; }
            .name { font-size: 18px; font-weight: bold; margin: 8px 0; }
            .slot { color: #999; font-size: 12px; }
            .token { font-family: monospace; font-size: 10px; word-break: break-all; margin-top: 8px; color: #555; }
        </style>
        </head>
        <body><div class="grid">${content.innerHTML}</div></body>
        </html>
    `);
    win.document.close();
    win.print();
};

onMounted(() => {
    fetchParticipants();
});
</script>
