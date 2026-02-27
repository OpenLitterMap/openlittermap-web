<template>
    <div class="flex items-center justify-center px-4 bg-blue-600" style="min-height: calc(100vh - 80px)">
        <div class="w-full max-w-md bg-white rounded-lg shadow p-8">
            <!-- Loading state while validating token -->
            <div v-if="validating" class="text-center text-gray-600">{{ $t('Validating your reset link...') }}</div>

            <!-- Invalid or expired token -->
            <div v-else-if="tokenInvalid">
                <h1 class="text-2xl font-bold text-center mb-2">{{ $t('Link expired') }}</h1>
                <p class="text-gray-600 text-center mb-6">{{ $t('This password reset link is invalid or has expired.') }}</p>
                <router-link
                    to="/password/reset"
                    class="block w-full text-center bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded"
                >
                    {{ $t('Request a new link') }}
                </router-link>
            </div>

            <!-- Valid token — show form or success -->
            <template v-else>
                <h1 class="text-2xl font-bold text-center mb-2">{{ $t('Set a new password') }}</h1>
                <p class="text-gray-600 text-center mb-6">{{ $t('Enter your new password below.') }}</p>

                <div v-if="successMessage" class="bg-green-50 border border-green-200 text-green-700 rounded p-3 mb-4">
                    {{ successMessage }} {{ $t('Redirecting...') }}
                </div>

                <div v-if="errorMessage" class="bg-red-50 border border-red-200 text-red-700 rounded p-3 mb-4">
                    {{ errorMessage }}
                </div>

                <form v-if="!successMessage" @submit.prevent="submit">
                    <div class="mb-4">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('Email address') }}</label>
                        <input
                            id="email"
                            v-model="email"
                            type="email"
                            required
                            readonly
                            class="w-full border border-gray-300 rounded px-3 py-2 bg-gray-50 text-gray-500"
                        />
                    </div>

                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('New password') }}</label>
                        <input
                            id="password"
                            v-model="password"
                            type="password"
                            required
                            minlength="5"
                            autofocus
                            class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500"
                        />
                    </div>

                    <div class="mb-4">
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1"
                            >{{ $t('Confirm password') }}</label
                        >
                        <input
                            id="password_confirmation"
                            v-model="passwordConfirmation"
                            type="password"
                            required
                            minlength="5"
                            class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="loading"
                        class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded disabled:opacity-50"
                    >
                        {{ loading ? $t('Resetting...') : $t('Reset Password') }}
                    </button>
                </form>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useUserStore } from '../../stores/user/index.js';

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();

const token = route.params.token;
const email = ref(route.query.email || '');
const password = ref('');
const passwordConfirmation = ref('');
const loading = ref(false);
const validating = ref(true);
const tokenInvalid = ref(false);
const successMessage = ref('');
const errorMessage = ref('');

onMounted(async () => {
    if (!token || !email.value) {
        tokenInvalid.value = true;
        validating.value = false;
        return;
    }

    try {
        await axios.post('/api/password/validate-token', {
            token,
            email: email.value,
        });
        validating.value = false;
    } catch {
        tokenInvalid.value = true;
        validating.value = false;
    }
});

async function submit() {
    if (loading.value) return;

    loading.value = true;
    successMessage.value = '';
    errorMessage.value = '';

    try {
        const { data } = await axios.post('/api/password/reset', {
            token,
            email: email.value,
            password: password.value,
            password_confirmation: passwordConfirmation.value,
        });

        successMessage.value = data.message || 'Password reset successfully!';

        // Log the user in on the frontend
        if (data.user) {
            userStore.initUser(data.user);
        }

        // Redirect to upload page after short delay
        setTimeout(() => {
            window.location.href = '/upload';
        }, 1500);
    } catch (err) {
        if (err.response?.status === 422) {
            const errors = err.response.data.errors;
            errorMessage.value =
                errors?.email?.[0] || errors?.password?.[0] || errors?.token?.[0] || 'Please check your input.';
        } else {
            errorMessage.value = 'Something went wrong. The link may have expired.';
        }
    } finally {
        loading.value = false;
    }
}
</script>
