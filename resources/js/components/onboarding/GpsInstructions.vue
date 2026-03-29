<template>
    <div class="rounded-lg border border-white/10 bg-white/5 p-4">
        <h3 v-if="!compact" class="mb-2 text-sm font-semibold text-white">
            {{ $t('Before you start: enable location on your camera') }}
        </h3>
        <p class="mb-3 text-xs text-white/50">
            {{ $t('Your photos need GPS coordinates so we can put them on the map. Here\'s how to make sure your camera saves location data:') }}
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
                <span>{{ $t('Open Settings') }}</span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">2.</span>
                <span>{{ $t('Tap Privacy & Security, then Location Services') }}</span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">3.</span>
                <span>{{ $t('Make sure Location Services is turned ON') }}</span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">4.</span>
                <span>{{ $t('Scroll down to Camera and select While Using the App') }}</span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">5.</span>
                <span>{{ $t('Recommended: Settings, Camera, Formats, select Most Compatible (uses JPG instead of HEIC)') }}</span>
            </li>
        </ol>

        <!-- Android instructions -->
        <ol v-if="activeTab === 'android'" class="space-y-1.5 text-xs text-white/60">
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">1.</span>
                <span>{{ $t('Turn on Location — pull down from the top of your screen and tap the Location icon to turn it on') }}</span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">2.</span>
                <span>{{ $t('Open your Camera app') }}</span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">3.</span>
                <span>{{ $t('Tap Settings (gear icon)') }}</span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">4.</span>
                <span>{{ $t('Find Location tags, GPS tags, or Save location and turn it ON') }}</span>
            </li>
        </ol>

        <!-- Android upload tip (web only) -->
        <p v-if="activeTab === 'android'" class="mt-2 rounded bg-white/5 px-3 py-2 text-xs text-white/40">
            <strong class="text-white/60">{{ $t('Upload tip:') }} </strong>
            {{ $t('When selecting photos to upload, tap the three dots in the top-right of the file picker and choose Browse instead of selecting from Photos or Albums. The default Android picker can strip GPS data from your photos.') }}
        </p>

        <p class="mt-3 text-xs text-white/30">
            {{ $t('Once enabled, every photo you take will include GPS automatically. You only need to do this once.') }}
        </p>
        <p class="mt-1 text-xs text-white/30">
            {{ $t('If you don\'t want your other photos to be geotagged, remember to turn Location Services off again afterwards.') }}
        </p>
    </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
    compact: {
        type: Boolean,
        default: false,
    },
});

const tabs = [
    { key: 'iphone', label: 'iPhone' },
    { key: 'android', label: 'Android' },
];

const activeTab = ref('iphone');
</script>
