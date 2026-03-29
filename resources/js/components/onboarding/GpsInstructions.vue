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
                <span>Open <strong class="text-white/80">Settings</strong></span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">2.</span>
                <span>Tap <strong class="text-white/80">Privacy &amp; Security</strong> &rarr; <strong class="text-white/80">Location Services</strong></span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">3.</span>
                <span>Make sure <strong class="text-white/80">Location Services</strong> is turned <strong class="text-emerald-400">ON</strong></span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">4.</span>
                <span>Scroll down to <strong class="text-white/80">Camera</strong> &rarr; select <strong class="text-emerald-400">While Using the App</strong></span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">5.</span>
                <span>Recommended: <strong class="text-white/80">Settings</strong> &rarr; <strong class="text-white/80">Camera</strong> &rarr; <strong class="text-white/80">Formats</strong> &rarr; select <strong class="text-emerald-400">Most Compatible</strong> (uses JPG instead of HEIC)</span>
            </li>
        </ol>

        <!-- Android instructions -->
        <ol v-if="activeTab === 'android'" class="space-y-1.5 text-xs text-white/60">
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">1.</span>
                <span>Turn on <strong class="text-white/80">Location</strong> — pull down from the top of your screen and tap the <strong class="text-white/80">Location</strong> icon to turn it <strong class="text-emerald-400">ON</strong></span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">2.</span>
                <span>Open your <strong class="text-white/80">Camera</strong> app</span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">3.</span>
                <span>Tap <strong class="text-white/80">Settings</strong> (gear icon)</span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">4.</span>
                <span>Find <strong class="text-white/80">Location tags</strong>, <strong class="text-white/80">GPS tags</strong>, or <strong class="text-white/80">Save location</strong> and turn it <strong class="text-emerald-400">ON</strong></span>
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
