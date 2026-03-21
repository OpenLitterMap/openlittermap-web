<template>
    <div class="space-y-6">
        <!-- Error banner -->
        <div
            v-if="settingsStore.error"
            class="bg-red-500/10 border border-red-500/20 text-red-400 rounded-lg px-4 py-3 text-sm"
        >
            {{ settingsStore.error }}
        </div>

        <!-- Account Settings -->
        <div class="bg-white/5 border border-white/10 rounded-xl p-6">
            <h3 class="text-white font-semibold mb-4">{{ $t('Account') }}</h3>

            <div class="space-y-4">
                <SettingsField :label="$t('Name')" :value="name" @save="(v) => saveSetting('name', v)" />
                <SettingsField :label="$t('Username')" :value="username" @save="(v) => saveSetting('username', v)" />
                <SettingsField :label="$t('Email')" :value="email" type="email" @save="(v) => saveSetting('email', v)" />
            </div>
        </div>

        <!-- Preferences -->
        <div class="bg-white/5 border border-white/10 rounded-xl p-6">
            <h3 class="text-white font-semibold mb-4">{{ $t('Preferences') }}</h3>

            <div class="space-y-3">
                <!-- Default Picked Up -->
                <div class="flex items-center justify-between gap-4 py-1">
                    <div>
                        <div class="text-white text-sm">{{ $t('Default Picked Up') }}</div>
                        <div class="text-white/30 text-xs">{{ $t('Set the default picked-up status when tagging') }}</div>
                    </div>
                    <div class="flex rounded-lg overflow-hidden border border-white/10">
                        <button
                            v-for="opt in pickedUpOptions"
                            :key="opt.value"
                            class="px-3 py-1.5 text-xs font-medium transition-colors"
                            :class="pickedUpValue === opt.value
                                ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30'
                                : 'bg-white/5 text-white/40 hover:bg-white/10 hover:text-white/60'"
                            @click="savePickedUp(opt.value)"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                </div>

                <SettingsToggle
                    :label="$t('Public Profile')"
                    :description="$t('Allow others to see your profile')"
                    :value="userStore.user.public_profile"
                    @toggle="saveSetting('public_profile', !userStore.user.public_profile)"
                />
                <SettingsToggle
                    :label="$t('Show Previous Tags')"
                    :description="$t('Pre-fill tags from your last upload')"
                    :value="userStore.user.previous_tags"
                    @toggle="settingsStore.TOGGLE_PRIVACY('/api/settings/privacy/toggle-previous-tags')"
                />
                <SettingsToggle
                    :label="$t('Email Notifications')"
                    :description="$t('Receive email updates about your contributions')"
                    :value="userStore.user.emailsub"
                    @toggle="saveSetting('emailsub', !userStore.user.emailsub)"
                />
            </div>
        </div>

        <!-- Privacy -->
        <div class="bg-white/5 border border-white/10 rounded-xl p-6">
            <h3 class="text-white font-semibold mb-4">{{ $t('Privacy') }}</h3>

            <div class="space-y-3">
                <SettingsToggle
                    :label="$t('Show name on maps')"
                    :value="userStore.user.show_name_maps"
                    @toggle="settingsStore.TOGGLE_PRIVACY('/api/settings/privacy/maps/name')"
                />
                <SettingsToggle
                    :label="$t('Show username on maps')"
                    :value="userStore.user.show_username_maps"
                    @toggle="settingsStore.TOGGLE_PRIVACY('/api/settings/privacy/maps/username')"
                />
                <SettingsToggle
                    :label="$t('Show name on leaderboards')"
                    :value="userStore.user.show_name"
                    @toggle="settingsStore.TOGGLE_PRIVACY('/api/settings/privacy/leaderboard/name')"
                />
                <SettingsToggle
                    :label="$t('Show username on leaderboards')"
                    :value="userStore.user.show_username"
                    @toggle="settingsStore.TOGGLE_PRIVACY('/api/settings/privacy/leaderboard/username')"
                />
                <SettingsToggle
                    :label="$t('Prevent others tagging my photos')"
                    :description="$t('Only you can add or edit tags on your photos')"
                    :value="userStore.user.prevent_others_tagging_my_photos"
                    @toggle="saveSetting('prevent_others_tagging_my_photos', !userStore.user.prevent_others_tagging_my_photos)"
                />
                <SettingsToggle
                    :label="$t('Photos public by default')"
                    :description="$t('New photos will appear on the public map')"
                    :value="userStore.user.public_photos"
                    @toggle="saveSetting('public_photos', !userStore.user.public_photos)"
                />
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="bg-red-500/5 border border-red-500/20 rounded-xl p-6">
            <h3 class="text-red-400 font-semibold mb-2">{{ $t('Danger Zone') }}</h3>
            <p class="text-white/40 text-sm mb-4">
                {{ $t('Permanently delete your account. Your photos will remain as public contributions to the map.') }}
            </p>
            <button
                v-if="!showDeleteConfirm"
                class="px-4 py-2 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg text-sm hover:bg-red-500/20 transition"
                @click="showDeleteConfirm = true"
            >
                {{ $t('Delete Account') }}
            </button>

            <div v-else class="space-y-3">
                <input
                    v-model="deletePassword"
                    type="password"
                    :placeholder="$t('Enter your password to confirm')"
                    class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm placeholder-white/30 focus:border-red-500/50 focus:outline-none"
                />
                <div v-if="settingsStore.deleteError" class="text-red-400 text-sm">
                    {{ settingsStore.deleteError }}
                </div>
                <div class="flex gap-3">
                    <button
                        class="px-4 py-2 bg-red-500/20 border border-red-500/30 text-red-400 rounded-lg text-sm hover:bg-red-500/30 transition"
                        :disabled="settingsStore.deleting || !deletePassword"
                        @click="confirmDelete"
                    >
                        {{ settingsStore.deleting ? $t('Deleting...') : $t('Confirm Delete') }}
                    </button>
                    <button
                        class="px-4 py-2 bg-white/5 border border-white/10 text-white/50 rounded-lg text-sm hover:bg-white/10 transition"
                        @click="showDeleteConfirm = false; deletePassword = ''"
                    >
                        {{ $t('Cancel') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSettingsStore } from '@stores/settings.js';
import { useUserStore } from '@stores/user/index.js';
import SettingsField from './SettingsField.vue';
import SettingsToggle from './SettingsToggle.vue';

const { t: $t } = useI18n();
const settingsStore = useSettingsStore();
const userStore = useUserStore();

const showDeleteConfirm = ref(false);
const deletePassword = ref('');

// Picked Up 3-state options: Yes (true), No (false), Unknown (null)
const pickedUpOptions = computed(() => [
    { value: true, label: $t('Yes') },
    { value: false, label: $t('No') },
    { value: null, label: $t('Unknown') },
]);

const pickedUpValue = computed(() => {
    const val = userStore.user.picked_up;
    if (val === true || val === 1) return true;
    if (val === false || val === 0) return false;
    return null;
});

const savePickedUp = async (value) => {
    settingsStore.clearMessages();
    const success = await settingsStore.UPDATE_SETTING('picked_up', value);
    if (success) {
        userStore.user.picked_up = value;
    }
};

const name = computed(() => userStore.user.name || '');
const username = computed(() => userStore.user.username || '');
const email = computed(() => userStore.user.email || '');

const saveSetting = async (key, value) => {
    settingsStore.clearMessages();
    const success = await settingsStore.UPDATE_SETTING(key, value);
    if (success && userStore.user) {
        userStore.user[key] = value;
    }
};

const confirmDelete = async () => {
    await settingsStore.DELETE_ACCOUNT(deletePassword.value);
};
</script>
