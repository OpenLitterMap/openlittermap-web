<template>
    <nav class="bg-black text-white p-1">
        <div class="container mx-auto px-4 flex justify-between items-center py-4">
            <div class="flex items-center space-x-4">
                <router-link to="/" class="flex items-center text-3xl">
                    #OpenLitterMap<sup aria-label="version five" style="font-size: 0.45em; line-height: 1">v5.0</sup>
                </router-link>
            </div>

            <!-- Hamburger Menu for Mobile -->
            <button class="md:hidden" @click="mobileNavOpen = !mobileNavOpen">
                <span class="block w-6 h-1 bg-white mb-1"></span>
                <span class="block w-6 h-1 bg-white mb-1"></span>
                <span class="block w-6 h-1 bg-white"></span>
            </button>

            <!-- Links -->
            <div :class="['md:space-x-6', mobileNavOpen ? 'md:block' : 'hidden md:flex items-center']">
                <router-link to="/about" class="nav-item">{{ t('About') }}</router-link>
                <!--                <router-link to="/cleanups" class="nav-item">Cleanups</router-link>-->
                <!--                <router-link to="/history" class="nav-item">History</router-link>-->
                <router-link to="/leaderboard" class="nav-item">{{ t('Leaderboard') }}</router-link>
                <router-link to="/global" class="nav-item">{{ t('Global Map') }}</router-link>
                <router-link to="/references" class="nav-item">{{ t('References') }}</router-link>
                <!--                <router-link to="/community" class="nav-item">Community</router-link>-->
                <router-link to="/world" class="nav-item">{{ t('World Cup') }}</router-link>

                <div v-if="auth" class="flex items-center space-x-4">
                    <router-link to="/upload" class="nav-item">{{ t('Upload') }}</router-link>
                </div>
            </div>

            <!-- Login/Signup -->
            <div v-if="!auth" class="hidden md:flex space-x-4">
                <button @click="login" class="nav-item">Login</button>
                <router-link to="/signup" class="nav-item">Sign Up</router-link>
            </div>

            <!-- User Dropdown -->
            <div v-else class="hidden md:flex items-center space-x-4">
                <!-- Dropdown -->
                <div
                    class="nav-item relative"
                    @mouseover="webDropdownOpen = true"
                    @mouseleave="webDropdownOpen = false"
                >
                    <!-- Dropdown Trigger -->
                    <span class="text-white cursor-pointer hover:underline">Menu</span>

                    <!-- Dropdown Menu -->
                    <div
                        v-if="webDropdownOpen"
                        @mouseover="webDropdownOpen = true"
                        @mouseleave="webDropdownOpen = false"
                        class="absolute left-0 mt-2 w-48 bg-white rounded-md shadow-lg z-20 group-hover:block"
                    >
                        <router-link
                            to="/admin/redis"
                            class="block rounded-md px-4 py-2 text-gray-700 hover:bg-gray-100"
                            >Admin - Redis</router-link
                        >

                        <!--                        <router-link-->
                        <!--                            to="/admin/users"-->
                        <!--                            class="block rounded-md px-4 py-2 text-gray-700 hover:bg-gray-100"-->
                        <!--                            >Admin - Users</router-link-->
                        <!--                        >-->

                        <router-link to="/tag" class="block rounded-md px-4 py-2 text-gray-700 hover:bg-gray-100"
                            >Add Tags</router-link
                        >

                        <router-link
                            to="/achievements"
                            class="block rounded-md px-4 py-2 text-gray-700 hover:bg-gray-100"
                            >Achievements</router-link
                        >

                        <router-link to="/profile" class="block rounded-md px-4 py-2 text-gray-700 hover:bg-gray-100"
                            >Profile</router-link
                        >

                        <router-link to="/settings" class="block rounded-md px-4 py-2 text-gray-700 hover:bg-gray-100"
                            >Settings</router-link
                        >
                    </div>
                </div>

                <button @click="logout" class="nav-item">Logout</button>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useModalStore } from '../stores/modal/index.js';
import { useUserStore } from '../stores/user/index.js';
import { useI18n } from 'vue-i18n';
const { t } = useI18n();

const modalStore = useModalStore();
const userStore = useUserStore();

const mobileNavOpen = ref(false);
const webDropdownOpen = ref(false);
const auth = computed(() => userStore.auth);

const toggleOpen = () => {
    open.value = !open.value;
};

const login = () => {
    modalStore.showModal({
        type: 'Login',
        title: 'Login',
        showIcon: true,
    });
};

const logout = async () => {
    userStore.logout();
};
</script>

<style scoped>
.nav-item {
    @apply block py-2 text-white hover:text-yellow-300 transition duration-200;
}

button:focus {
    outline: none;
}
</style>
