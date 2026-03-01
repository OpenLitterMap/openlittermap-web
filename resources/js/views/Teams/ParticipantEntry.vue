<template>
    <div class="min-h-screen flex items-center justify-center bg-slate-50 px-4">
        <div class="max-w-md w-full">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-slate-800">Join a Session</h1>
                <p class="text-slate-500 mt-2 text-sm">
                    Enter the session code provided by your teacher to start collecting litter data.
                </p>
            </div>

            <form class="bg-white rounded-xl p-6 shadow-sm space-y-4" @submit.prevent="enterSession">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Session Code</label>
                    <input
                        v-model="token"
                        type="text"
                        placeholder="Paste your session code here"
                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono"
                        :class="error ? 'border-red-400' : ''"
                        @input="error = ''"
                    />
                    <p v-if="error" class="text-red-500 text-xs mt-1">{{ error }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="loading || token.length < 64"
                    class="w-full py-2 rounded-lg text-white font-medium text-sm transition-colors"
                    :class="loading || token.length < 64
                        ? 'bg-slate-400 cursor-not-allowed'
                        : 'bg-blue-600 hover:bg-blue-700'"
                >
                    {{ loading ? 'Joining...' : 'Join Session' }}
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();
const token = ref('');
const loading = ref(false);
const error = ref('');

const enterSession = async () => {
    if (token.value.length !== 64) {
        error.value = 'Session code must be exactly 64 characters.';
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.post('/api/participant/session', {
            token: token.value,
        });

        if (data.success) {
            localStorage.setItem('participant_token', token.value);
            localStorage.setItem('participant_session', JSON.stringify(data.session));
            router.push({ name: 'ParticipantWorkspace' });
        }
    } catch (e) {
        error.value = e.response?.data?.message || 'Invalid or expired session code.';
    } finally {
        loading.value = false;
    }
};
</script>
