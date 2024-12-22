<template>
    <nav class="bg-black text-white p-3">
        <div class="container mx-auto px-4 flex justify-between items-center py-3">
            <!-- Logo and Title -->
            <div class="flex items-center space-x-4">
                <router-link to="/" class="flex items-center text-4xl font-bold">
<!--                    <img src="/assets/logo.png" alt="Logo" class="h-8 w-8 mr-2" />-->
                    <span class="md:block">#OpenLitterMap</span>
                </router-link>
            </div>

            <!-- Hamburger Menu for Mobile -->
            <button class="md:hidden" @click="toggleOpen">
                <span class="block w-6 h-1 bg-white mb-1"></span>
                <span class="block w-6 h-1 bg-white mb-1"></span>
                <span class="block w-6 h-1 bg-white"></span>
            </button>

            <!-- Links -->
            <div :class="['flex flex-col md:flex-row md:space-x-6', open ? 'block' : 'hidden md:block']">
                <router-link to="/about" class="nav-item">About</router-link>
<!--                <router-link to="/cleanups" class="nav-item">Cleanups</router-link>-->
<!--                <router-link to="/history" class="nav-item">History</router-link>-->
<!--                <router-link to="/leaderboard" class="nav-item">Leaderboard</router-link>-->
<!--                <router-link to="/global" class="nav-item">Global Map</router-link>-->
<!--                <router-link to="/community" class="nav-item">Community</router-link>-->
<!--                <router-link to="/world" class="nav-item">World Cup</router-link>-->
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

<script>
import { ref, computed } from "vue";

export default {
    name: "Nav",

    setup() {
        const open = ref(false);
        const dropdownOpen = ref(false);

        const auth = computed(() => {
            return false;
        });

        const toggleOpen = () => {
            open.value = !open.value;
        };

        const close = () => {
            open.value = false;
            dropdownOpen.value = false;
        };

        const login = () => {
            console.log("Show login modal");
        };

        const logout = async () => {
            console.log("Logging out...");
        };

        const isDesktop = computed(() => {
            return window.innerWidth >= 768;
        });

        const burger = computed(() =>
            open.value ? "burger is-active" : "burger"
        );

        const toggleDropdown = () => {
            dropdownOpen.value = !dropdownOpen.value;
        };

        return {
            open,
            auth,
            burger,
            toggleOpen,
            close,
            login,
            logout,
            toggleDropdown,
            dropdownOpen,
            isDesktop,
        };
    }
};
</script>

<style scoped>

    /* Utility Classes for Reusability */
    .nav-item {
        @apply block py-2 text-white hover:text-yellow-300 transition duration-200;
    }

    .btn-primary {
        @apply bg-yellow-400 text-black font-semibold px-4 py-2 rounded hover:bg-yellow-500 transition;
    }

    .btn-secondary {
        @apply border border-white text-white px-4 py-2 rounded hover:bg-white hover:text-black transition;
    }

    /* Mobile Hamburger Menu Animation */
    button:focus {
        outline: none;
    }

</style>
