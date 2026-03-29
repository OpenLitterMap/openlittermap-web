<template>
    <nav class="bg-black border-b border-white/10 text-white p-1 relative z-50">
        <div class="container mx-auto px-4 flex flex-wrap justify-between items-center py-3">
            <div class="flex items-center space-x-4">
                <router-link to="/" class="nav-logo flex items-center text-2xl md:text-3xl font-bold text-white">
                    #OpenLitterMap<sup :aria-label="'version ' + appVersion" style="font-size: 0.45em; line-height: 1" class="text-amber-400">v{{ appVersion }}</sup>
                </router-link>
            </div>

            <!-- Hamburger Menu for Mobile -->
            <button class="md:hidden p-2 rounded-lg hover:bg-white/10 transition-colors" @click="mobileNavOpen = !mobileNavOpen">
                <svg v-if="!mobileNavOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Nav Links — vertical stack on mobile when open, horizontal on desktop -->
            <div
                :class="[
                    'w-full md:w-auto md:flex md:items-center md:space-x-6',
                    mobileNavOpen ? 'block mt-3 space-y-1 border-t border-white/10 pt-3' : 'hidden',
                ]"
            >
                <router-link to="/about" class="nav-item" @click="closeMenu">{{ t('About') }}</router-link>
                <router-link to="/global" class="nav-item" @click="closeMenu">{{ t('Global Map') }}</router-link>
                <router-link to="/leaderboard" class="nav-item" @click="closeMenu">{{ t('Leaderboard') }}</router-link>
                <router-link to="/locations" class="nav-item" @click="closeMenu">{{ t('Locations') }}</router-link>

                <!-- Auth-only nav links -->
                <template v-if="auth">
                    <router-link to="/upload" class="nav-item" @click="closeMenu">{{ t('Upload') }}</router-link>
                    <router-link to="/tag" class="nav-item" @click="closeMenu">{{ t('Add Tags') }}</router-link>
                </template>

                <!-- Mobile: show all menu items inline when open -->
                <template v-if="mobileNavOpen">
                    <div class="border-t border-white/10 my-2 md:hidden"></div>

                    <template v-if="!auth">
                        <button @click="login(); closeMenu()" class="nav-item w-full text-left">{{ t('Login') }}</button>
                        <router-link to="/signup" class="nav-item" @click="closeMenu">{{ t('Sign Up') }}</router-link>
                    </template>

                    <template v-else>
                        <!-- Admin Links -->
                        <template v-if="isAdmin">
                            <div class="border-t border-white/10 my-2 md:hidden"></div>
                            <span class="block px-3 py-1 text-[10px] uppercase tracking-wider text-white/30 md:hidden">{{ t('Admin') }}</span>
                            <router-link to="/admin/queue" class="nav-item" @click="closeMenu">{{ t('Admin - Queue') }}</router-link>
                            <router-link to="/admin/users" class="nav-item" @click="closeMenu">{{ t('Admin - Users') }}</router-link>
                            <router-link to="/admin/redis" class="nav-item" @click="closeMenu">{{ t('Admin - Redis') }}</router-link>
                            <a href="/horizon" target="_blank" class="nav-item" @click="closeMenu">{{ t('Admin - Horizon') }}</a>
                        </template>

                        <div class="border-t border-white/10 my-2 md:hidden"></div>

                        <router-link v-if="auth && !onboardingCompleted" to="/onboarding" class="nav-item text-amber-400 hover:text-amber-300" @click="closeMenu">{{ t('Onboarding') }}</router-link>
                        <router-link to="/profile" class="nav-item" @click="closeMenu">{{ t('Profile') }}</router-link>
                        <router-link to="/teams" class="nav-item relative" @click="closeMenu">
                            {{ t('Teams') }}
                            <span
                                v-if="showTeamSetupDot"
                                class="inline-block w-2 h-2 rounded-full bg-amber-400 ml-1 align-middle"
                            ></span>
                        </router-link>
                        <router-link to="/settings" class="nav-item" @click="closeMenu">{{ t('Settings') }}</router-link>

                        <div class="border-t border-white/10 my-2 md:hidden"></div>
                        <button @click="logout(); closeMenu()" class="nav-item w-full text-left text-red-400 hover:text-red-300">{{ t('Logout') }}</button>
                    </template>
                </template>
            </div>

            <!-- Desktop: Login/Signup -->
            <div v-if="!auth" class="hidden md:flex space-x-4">
                <button @click="login" class="nav-item">{{ t('Login') }}</button>
                <router-link to="/signup" class="nav-item">{{ t('Sign Up') }}</router-link>
            </div>

            <!-- Desktop: User Dropdown -->
            <div v-if="auth" class="hidden md:flex items-center space-x-4">
                <div
                    class="nav-item relative"
                    @mouseenter="webDropdownOpen = true"
                    @mouseleave="webDropdownOpen = false"
                    @click="webDropdownOpen = !webDropdownOpen"
                >
                    <span class="text-white cursor-pointer hover:text-amber-400 transition-colors flex items-center gap-1">
                        {{ t('Menu') }}
                        <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': webDropdownOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </span>

                    <!-- Dropdown Menu -->
                    <transition
                        enter-active-class="transition ease-out duration-100"
                        enter-from-class="transform opacity-0 scale-95"
                        enter-to-class="transform opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-75"
                        leave-from-class="transform opacity-100 scale-100"
                        leave-to-class="transform opacity-0 scale-95"
                    >
                        <div
                            v-if="webDropdownOpen"
                            class="absolute right-0 mt-2 w-48 bg-black border border-white/10 rounded-xl shadow-xl z-20 py-1 overflow-hidden"
                        >
                            <template v-if="isAdmin">
                                <span class="block px-4 py-1.5 text-[10px] uppercase tracking-wider text-white/30">{{ t('Admin') }}</span>
                                <router-link
                                    to="/admin/queue"
                                    class="block px-4 py-2 text-white/70 hover:bg-white/5 hover:text-amber-400 transition-colors"
                                    @click="webDropdownOpen = false"
                                >{{ t('Queue') }}</router-link>
                                <router-link
                                    to="/admin/users"
                                    class="block px-4 py-2 text-white/70 hover:bg-white/5 hover:text-amber-400 transition-colors"
                                    @click="webDropdownOpen = false"
                                >{{ t('Users') }}</router-link>
                                <router-link
                                    to="/admin/redis"
                                    class="block px-4 py-2 text-white/70 hover:bg-white/5 hover:text-amber-400 transition-colors"
                                    @click="webDropdownOpen = false"
                                >{{ t('Redis') }}</router-link>
                                <a
                                    href="/horizon"
                                    target="_blank"
                                    class="block px-4 py-2 text-white/70 hover:bg-white/5 hover:text-amber-400 transition-colors"
                                    @click="webDropdownOpen = false"
                                >{{ t('Horizon') }}</a>
                                <div class="border-t border-white/10 my-1"></div>
                            </template>

                            <router-link
                                v-if="!onboardingCompleted"
                                to="/onboarding"
                                class="block px-4 py-2 text-amber-400 hover:bg-white/5 hover:text-amber-300 transition-colors"
                                @click="webDropdownOpen = false"
                            >{{ t('Onboarding') }}</router-link>

                            <router-link
                                to="/profile"
                                class="block px-4 py-2 text-white/70 hover:bg-white/5 hover:text-amber-400 transition-colors"
                                @click="webDropdownOpen = false"
                            >{{ t('Profile') }}</router-link>

                            <router-link
                                to="/teams"
                                class="block px-4 py-2 text-white/70 hover:bg-white/5 hover:text-amber-400 transition-colors relative"
                                @click="webDropdownOpen = false"
                            >
                                {{ t('Teams') }}
                                <span
                                    v-if="showTeamSetupDot"
                                    class="inline-block w-2 h-2 rounded-full bg-amber-400 ml-1 align-middle"
                                ></span>
                            </router-link>

                            <router-link
                                to="/settings"
                                class="block px-4 py-2 text-white/70 hover:bg-white/5 hover:text-amber-400 transition-colors"
                                @click="webDropdownOpen = false"
                            >{{ t('Settings') }}</router-link>

                            <div class="border-t border-white/10 my-1"></div>

                            <button
                                @click="logout(); webDropdownOpen = false"
                                class="block w-full text-left px-4 py-2 text-red-400/80 hover:bg-red-500/10 hover:text-red-400 transition-colors"
                            >{{ t('Logout') }}</button>
                        </div>
                    </transition>
                </div>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useModalStore } from '../stores/modal/index.js';
import { useUserStore } from '../stores/user/index.js';
import { useI18n } from 'vue-i18n';
const { t } = useI18n();

const modalStore = useModalStore();
const userStore = useUserStore();
const appVersion = __APP_VERSION__;

const mobileNavOpen = ref(false);
const webDropdownOpen = ref(false);
const auth = computed(() => userStore.auth);
const onboardingCompleted = computed(() => userStore.onboardingCompleted);
const isAdmin = computed(() => {
    if (userStore.admin) return true;
    const roles = userStore.user?.roles || [];
    return roles.some((r) => ['admin', 'helper', 'superadmin'].includes(r.name));
});

const showTeamSetupDot = computed(() => {
    const roles = userStore.user?.roles || [];
    const isSchoolManager = roles.some((r) => r.name === 'school_manager');
    return isSchoolManager && (userStore.user?.remaining_teams ?? 0) > 0;
});

const closeMenu = () => {
    mobileNavOpen.value = false;
};

const login = () => {
    modalStore.showModal({
        type: 'Login',
        title: 'Login',
        showIcon: true,
    });
};

const logout = async () => {
    await userStore.LOGOUT_REQUEST();
};

// Close dropdown when clicking outside
const handleClickOutside = (e) => {
    if (webDropdownOpen.value && !e.target.closest('.nav-item.relative')) {
        webDropdownOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<style scoped>
.nav-item {
    @apply block py-2 px-3 md:px-0 text-white/70 hover:text-white transition-colors duration-200 rounded-lg md:rounded-none;
}

.router-link-active:not(.nav-logo) {
    @apply text-amber-400;
}

button:focus {
    outline: none;
}
</style>
