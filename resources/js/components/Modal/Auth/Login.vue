<template>
    <div class="p-6">
        <p v-if="errorLogin" class="text-red-400 text-sm mb-4 text-center">{{ errorLogin }}</p>

        <form class="space-y-4 text-center" @submit.prevent="login">
            <input
                class="w-full bg-white/5 border border-white/20 rounded-lg px-4 py-2.5 text-white placeholder-white/30 focus:outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/50"
                :placeholder="$t('Email or username')"
                type="text"
                name="identifier"
                required
                v-model="identifier"
                @keydown="clearLoginError"
                autocomplete="username"
            />

            <input
                class="w-full bg-white/5 border border-white/20 rounded-lg px-4 py-2.5 text-white placeholder-white/30 focus:outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/50"
                :placeholder="$t('Your Password')"
                type="password"
                name="password"
                required
                v-model="password"
                @keydown="clearPwError"
                autocomplete="current-password"
            />

            <button
                class="w-full px-6 py-2.5 rounded-lg text-white font-medium transition-colors"
                :class="processing ? 'bg-white/10 text-white/30 cursor-not-allowed' : 'bg-emerald-500 hover:bg-emerald-400'"
                :disabled="processing"
            >
                {{ $t('Login') }}
            </button>
        </form>

        <footer class="mt-6 border-t border-white/10 pt-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <router-link
                    to="/signup"
                    class="inline-flex items-center justify-center px-4 py-2 text-emerald-400 hover:text-emerald-300 transition-colors"
                    @click="closeModal"
                >
                    {{ $t('Sign up') }}
                </router-link>
                <router-link
                    to="/password/reset"
                    class="inline-flex items-center justify-center px-4 py-2 text-white/40 hover:text-white/60 transition-colors"
                    @click="closeModal"
                >
                    {{ $t('Forgot Password') }}
                </router-link>
            </div>
        </footer>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useUserStore } from '@/stores/user';
import { useModalStore } from '@/stores/modal';

const userStore = useUserStore();
const modalStore = useModalStore();
const identifier = ref('');
const password = ref('');
const processing = ref(false);

const errorLogin = computed(() => userStore.errorLogin);

const clearLoginError = () => userStore.clearErrorLogin();
const clearPwError = () => {};

const closeModal = () => {
    modalStore.hideModal();
};

const login = async () => {
    processing.value = true;
    await userStore.LOGIN({
        identifier: identifier.value,
        password: password.value,
    });
    processing.value = false;
};
</script>
