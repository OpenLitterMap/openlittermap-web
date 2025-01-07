<template>
    <nav class="bg-black text-white p-3">
        <div class="container mx-auto px-4 flex justify-between items-center py-3">

            <!-- Logo -->
            <div class="flex items-center space-x-4">
                <router-link to="/" class="flex items-center text-4xl">
                    <span class="md:block">#OpenLitterMap</span>
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
                <router-link to="/about" class="nav-item">About</router-link>
<!--                <router-link to="/cleanups" class="nav-item">Cleanups</router-link>-->
<!--                <router-link to="/history" class="nav-item">History</router-link>-->
                <router-link to="/leaderboard" class="nav-item">Leaderboard</router-link>
                <router-link to="/global" class="nav-item">Global Map</router-link>
                <router-link to="/references" class="nav-item">References</router-link>
<!--                <router-link to="/community" class="nav-item">Community</router-link>-->
<!--                <router-link to="/world" class="nav-item">World Cup</router-link>-->

                <div v-if="auth" class="flex items-center space-x-4">
                    <router-link to="/upload" class="nav-item">
                        {{ $t('nav.upload') }}
                    </router-link>

                    <!-- Dropdown -->
                    <div
                        class="nav-item relative"
                        @mouseover="webDropdownOpen = true"
                        @mouseleave="webDropdownOpen = false"
                    >
                        <!-- Dropdown Trigger -->
                        <span class="nav-item text-white cursor-pointer hover:underline">Menu</span>

                        <!-- Dropdown Menu -->
                        <div
                            v-if="webDropdownOpen"
                            @mouseover="webDropdownOpen = true"
                            @mouseleave="webDropdownOpen = false"
                            class="absolute left-0 mt-2 w-48 bg-white rounded-md shadow-lg z-20 group-hover:block"
                        >
                            <router-link
                                to="/add-tags"
                                class="block px-4 py-2 text-gray-700 hover:bg-gray-100"
                            >Add Tags</router-link>

                            <router-link
                                to="/profile"
                                class="block px-4 py-2 text-gray-700 hover:bg-gray-100"
                            >Profile</router-link>

                            <router-link
                                to="/settings"
                                class="block px-4 py-2 text-gray-700 hover:bg-gray-100"
                            >Settings</router-link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Login/Signup -->
            <div v-if="!auth" class="hidden md:flex space-x-4">
                <button @click="login" class="btn-secondary">Login</button>
                <router-link to="/signup" class="btn-primary">Sign Up</router-link>
            </div>

            <!-- User Dropdown -->
            <div v-else class="hidden md:flex items-center space-x-4">
                <button @click="logout" class="btn-secondary">Logout</button>
                <router-link to="/profile" class="btn-primary">Profile</router-link>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { ref, computed } from "vue";
import { useModalStore } from "../stores/modal/index.js";
import { useUserStore } from "../stores/user/index.js";

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
        type: "Login",
        title: "Login",
        showIcon: true
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

    .btn-primary {
        @apply bg-yellow-400 text-black font-semibold px-4 py-2 rounded hover:bg-yellow-500 transition;
    }

    .btn-secondary {
        @apply border border-white text-white px-4 py-2 rounded hover:bg-white hover:text-black transition;
    }

    button:focus {
        outline: none;
    }

</style>
