<template>
    <div class="rounded-lg border border-white/10 bg-white/5 p-4">
        <h3 v-if="!compact" class="mb-2 text-sm font-semibold text-white">
            {{ $t('onboarding_gps_heading') }}
        </h3>
        <p class="mb-3 text-xs text-white/50">
            {{ $t('onboarding_gps_intro') }}
        </p>

        <!-- Tab buttons -->
        <div class="mb-3 flex gap-1 rounded-lg bg-white/5 p-1">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                @click="activeTab = tab.key"
                class="flex-1 rounded-md px-3 py-1.5 text-xs font-medium transition-all"
                :class="activeTab === tab.key
                    ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'
                    : 'text-white/40 hover:text-white/60'"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- iPhone instructions -->
        <ol v-if="activeTab === 'iphone'" class="space-y-1.5 text-xs text-white/60">
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">1.</span>
                <span>{{ $t('onboarding_gps_iphone_1_prefix') }} <strong class="text-white/80">{{ $t('onboarding_gps_iphone_1_bold') }}</strong></span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">2.</span>
                <span>{{ $t('onboarding_gps_iphone_2_prefix') }} <strong class="text-white/80">{{ $t('onboarding_gps_iphone_2_bold1') }}</strong> &rarr; <strong class="text-white/80">{{ $t('onboarding_gps_iphone_2_bold2') }}</strong></span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">3.</span>
                <span>{{ $t('onboarding_gps_iphone_3_prefix') }} <strong class="text-white/80">{{ $t('onboarding_gps_iphone_3_bold') }}</strong> {{ $t('onboarding_gps_iphone_3_suffix') }} <strong class="text-emerald-400">{{ $t('onboarding_gps_on') }}</strong></span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">4.</span>
                <span>{{ $t('onboarding_gps_iphone_4_prefix') }} <strong class="text-white/80">{{ $t('onboarding_gps_iphone_4_bold') }}</strong> {{ $t('onboarding_gps_iphone_4_mid') }} <strong class="text-emerald-400">{{ $t('onboarding_gps_iphone_4_value') }}</strong></span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">5.</span>
                <span>{{ $t('onboarding_gps_iphone_5') }}</span>
            </li>
        </ol>

        <!-- Android instructions -->
        <ol v-if="activeTab === 'android'" class="space-y-1.5 text-xs text-white/60">
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">1.</span>
                <span>{{ $t('onboarding_gps_android_1') }}</span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">2.</span>
                <span>{{ $t('onboarding_gps_android_2_prefix') }} <strong class="text-white/80">{{ $t('onboarding_gps_android_2_bold') }}</strong></span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">3.</span>
                <span>{{ $t('onboarding_gps_android_3_prefix') }} <strong class="text-white/80">{{ $t('onboarding_gps_android_3_bold') }}</strong></span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">4.</span>
                <span>{{ $t('onboarding_gps_android_4') }}</span>
            </li>
        </ol>

        <!-- Android upload tip (web only) -->
        <p v-if="activeTab === 'android'" class="mt-2 rounded bg-white/5 px-3 py-2 text-xs text-white/40">
            <strong class="text-white/60">{{ $t('onboarding_gps_android_upload_tip_label') }}</strong>
            {{ $t('onboarding_gps_android_upload_tip') }}
        </p>

        <p class="mt-3 text-xs text-white/30">
            {{ $t('onboarding_gps_footer') }}
        </p>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps({
    compact: {
        type: Boolean,
        default: false,
    },
});

const { t } = useI18n();

const tabs = [
    { key: 'iphone', label: 'iPhone' },
    { key: 'android', label: 'Android' },
];

const activeTab = ref('iphone');
</script>
