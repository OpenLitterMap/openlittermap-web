<template>
    <div class="flex items-center justify-center px-4 bg-gradient-to-br from-slate-900 via-blue-900 to-emerald-900" style="min-height: calc(100vh - 73px)">
        <div class="w-full max-w-md bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl shadow-xl p-8">
            <h1 class="text-2xl font-bold text-center mb-2 text-white">{{ $t('Reset your password') }}</h1>
            <p class="text-white/60 text-center mb-6">{{ $t("Enter your username or email and we'll send you a reset link.") }}</p>

            <div v-if="successMessage" class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 rounded-lg p-3 mb-4">
                {{ successMessage }}
            </div>

            <div v-if="errorMessage" class="bg-red-500/10 border border-red-500/30 text-red-300 rounded-lg p-3 mb-4">
                {{ errorMessage }}
            </div>

            <form @submit.prevent="submit">
                <div class="mb-4">
                    <label for="login" class="block text-sm font-medium text-white/70 mb-1">{{ $t('Username or email') }}</label>
                    <input
                        id="login"
                        v-model="login"
                        type="text"
                        required
                        autofocus
                        class="w-full bg-white/5 border border-white/20 text-white placeholder-white/30 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:border-emerald-500/50 focus:ring-emerald-500/30"
                        placeholder="you@example.com or username"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="loading || disabled"
                    class="w-full bg-emerald-500 hover:bg-emerald-400 text-white font-medium py-2.5 px-4 rounded-lg disabled:opacity-50 transition-colors"
                >
                    {{ loading ? $t('Sending...') : $t('Send Reset Link') }}
                </button>
            </form>

            <p class="text-center text-sm text-white/50 mt-4">
                <router-link to="/signup" class="text-emerald-400 hover:underline">{{ $t('Back to login') }}</router-link>
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

const login = ref('');
const loading = ref(false);
const successMessage = ref('');
const errorMessage = ref('');

async function submit() {
    if (loading.value) return;

    loading.value = true;
    successMessage.value = '';
    errorMessage.value = '';

    try {
        const { data } = await axios.post('/api/password/email', { login: login.value });
        successMessage.value = data.message;
    } catch (err) {
        errorMessage.value = 'Something went wrong. Please try again.';
    } finally {
        loading.value = false;
    }
}
</script>
