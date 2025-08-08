<template>
    <div class="p-6 bg-[#f5f5f5] rounded-lg">
        <p v-if="errorLogin" class="text-red-500">{{ errorLogin }}</p>

        <form class="space-y-4 text-center" @submit.prevent="login">
            <input
                class="input border border-gray-300 rounded px-4 py-2 w-full text-lg"
                placeholder="you@email.com"
                type="email"
                name="email"
                required
                v-model="email"
                @keydown="clearLoginError"
                autocomplete="email"
            />

            <input
                class="input border border-gray-300 rounded px-4 py-2 w-full text-lg"
                placeholder="Your Password"
                type="password"
                name="password"
                required
                v-model="password"
                @keydown="clearPwError"
                autocomplete="current-password"
            />

            <button
                class="px-6 py-2 rounded text-white font-semibold"
                :class="processing ? 'bg-gray-400 cursor-not-allowed' : 'bg-blue-500 hover:bg-blue-600'"
                :disabled="processing"
            >
                {{ $t('Login') }}
            </button>
        </form>

        <!-- Bottom footer section -->
        <footer class="mt-6 border-t border-gray-200 pt-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <a
                    href="/signup"
                    class="inline-flex items-center justify-center px-4 py-2 text-blue-600 hover:underline"
                >
                    {{ $t('Sign up') }}
                </a>
                <a
                    href="/password/reset"
                    class="inline-flex items-center justify-center px-4 py-2 text-blue-600 hover:underline"
                >
                    {{ $t('Forgot Password') }}
                </a>
            </div>
        </footer>
    </div>
</template>

<script>
import { ref, computed } from 'vue';
import { useUserStore } from '@/stores/user';

export default {
    name: 'Login',
    setup() {
        const userStore = useUserStore();
        const email = ref('');
        const password = ref('');
        const processing = ref(false);

        const errorLogin = computed(() => userStore.errorLogin);

        const clearLoginError = () => userStore.clearErrorLogin();
        const clearPwError = () => {};

        const login = async () => {
            processing.value = true;
            await userStore.LOGIN({ email: email.value, password: password.value });
            processing.value = false;
        };

        return {
            email,
            password,
            processing,
            errorLogin,
            clearLoginError,
            clearPwError,
            login,
        };
    },
};
</script>
