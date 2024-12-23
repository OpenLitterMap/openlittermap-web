<template>
    <div class="p-4">
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
            >{{ $t('auth.login.login-btn') }}</button>
        </form>

        <footer class="flex justify-between mt-4">
            <a href="/signup" class="text-blue-500 hover:underline">
                {{ $t('auth.login.signup-text') }}
            </a>
            <a href="/password/reset" class="text-blue-500 hover:underline">
                {{ $t('auth.login.forgot-password') }}
            </a>
        </footer>
    </div>
</template>

<script>
import { ref, computed } from "vue";
import { useUserStore } from "@/stores/user";

export default {
    name: "Login",
    setup() {
        const userStore = useUserStore();
        const email = ref("");
        const password = ref("");
        const processing = ref(false);

        const errorLogin = computed(() => userStore.errorLogin);

        const clearLoginError = () => {
            userStore.clearErrorLogin();
        };

        const clearPwError = () => {
            // Logic to clear password-related errors if needed
        };

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
