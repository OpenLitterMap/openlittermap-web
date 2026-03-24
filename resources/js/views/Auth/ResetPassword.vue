<template>
    <div class="flex items-center justify-center px-4 bg-gradient-to-br from-slate-900 via-blue-900 to-emerald-900" style="min-height: calc(100vh - 73px)">
        <div class="w-full max-w-md bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl shadow-xl p-8">
            <!-- Loading state while validating token -->
            <div v-if="validating" class="text-center text-white/60">{{ $t('Validating your reset link...') }}</div>

            <!-- Invalid or expired token -->
            <div v-else-if="tokenInvalid">
                <h1 class="text-2xl font-bold text-center mb-2 text-white">{{ $t('Link expired') }}</h1>
                <p class="text-white/60 text-center mb-6">{{ $t('This password reset link is invalid or has expired.') }}</p>
                <router-link
                    to="/password/reset"
                    class="block w-full text-center bg-emerald-500 hover:bg-emerald-400 text-white font-medium py-2.5 px-4 rounded-lg transition-colors"
                >
                    {{ $t('Request a new link') }}
                </router-link>
            </div>

            <!-- Valid token — show form or success -->
            <template v-else>
                <h1 class="text-2xl font-bold text-center mb-2 text-white">{{ $t('Set a new password') }}</h1>
                <p class="text-white/60 text-center mb-6">{{ $t('Enter your new password below.') }}</p>

                <div v-if="successMessage" class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 rounded-lg p-3 mb-4">
                    {{ successMessage }} {{ $t('Redirecting...') }}
                </div>

                <div v-if="errorMessage" class="bg-red-500/10 border border-red-500/30 text-red-300 rounded-lg p-3 mb-4">
                    {{ errorMessage }}
                </div>

                <form v-if="!successMessage" @submit.prevent="submit">
                    <div class="mb-4">
                        <label for="email" class="block text-sm font-medium text-white/70 mb-1">{{ $t('Email address') }}</label>
                        <input
                            id="email"
                            v-model="email"
                            type="email"
                            required
                            readonly
                            class="w-full bg-white/5 border border-white/10 text-white/50 rounded-lg px-3 py-2.5"
                        />
                    </div>

                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-white/70 mb-1">{{ $t('New password') }}</label>
                        <input
                            id="password"
                            v-model="password"
                            type="password"
                            required
                            minlength="8"
                            autofocus
                            class="w-full bg-white/5 border border-white/20 text-white placeholder-white/30 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:border-emerald-500/50 focus:ring-emerald-500/30"
                        />
                    </div>

                    <div class="mb-4">
                        <label for="password_confirmation" class="block text-sm font-medium text-white/70 mb-1"
                            >{{ $t('Confirm password') }}</label
                        >
                        <input
                            id="password_confirmation"
                            v-model="passwordConfirmation"
                            type="password"
                            required
                            minlength="8"
                            class="w-full bg-white/5 border border-white/20 text-white placeholder-white/30 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:border-emerald-500/50 focus:ring-emerald-500/30"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="loading"
                        class="w-full bg-emerald-500 hover:bg-emerald-400 text-white font-medium py-2.5 px-4 rounded-lg disabled:opacity-50 transition-colors"
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
