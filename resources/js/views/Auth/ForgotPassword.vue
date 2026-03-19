<template>
    <div class="flex items-center justify-center px-4 bg-blue-600" style="min-height: calc(100vh - 73px)">
        <div class="w-full max-w-md bg-white rounded-lg shadow p-8">
            <h1 class="text-2xl font-bold text-center mb-2">{{ $t('Reset your password') }}</h1>
            <p class="text-gray-600 text-center mb-6">{{ $t("Enter your username or email and we'll send you a reset link.") }}</p>

            <div v-if="successMessage" class="bg-green-50 border border-green-200 text-green-700 rounded p-3 mb-4">
                {{ successMessage }}
            </div>

            <div v-if="errorMessage" class="bg-red-50 border border-red-200 text-red-700 rounded p-3 mb-4">
                {{ errorMessage }}
            </div>

            <form @submit.prevent="submit">
                <div class="mb-4">
                    <label for="login" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('Username or email') }}</label>
                    <input
                        id="login"
                        v-model="login"
                        type="text"
                        required
                        autofocus
                        class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500"
                        placeholder="you@example.com or username"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="loading || disabled"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded disabled:opacity-50"
                >
                    {{ loading ? $t('Sending...') : $t('Send Reset Link') }}
                </button>
            </form>

            <p class="text-center text-sm text-gray-500 mt-4">
                <router-link to="/signup" class="text-green-600 hover:underline">{{ $t('Back to login') }}</router-link>
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
