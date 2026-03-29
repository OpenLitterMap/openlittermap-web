<template>
    <div class="rounded-lg border border-white/10 bg-white/5 p-4">
        <h3 v-if="!compact" class="mb-2 text-sm font-semibold text-white">
            Before you start: enable location on your camera
        </h3>
        <p class="mb-3 text-xs text-white/50">
            Your photos need GPS coordinates so we can put them on the map.
            Here's how to make sure your camera saves location data:
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
                <span>Scroll down to <strong class="text-white/80">Camera</strong> and select <strong class="text-emerald-400">While Using the App</strong></span>
            </li>
        </ol>

        <!-- Android instructions -->
        <ol v-if="activeTab === 'android'" class="space-y-1.5 text-xs text-white/60">
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">1.</span>
                <span>Open your <strong class="text-white/80">Camera</strong> app</span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">2.</span>
                <span>Tap the <strong class="text-white/80">Settings</strong> gear icon</span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">3.</span>
                <span>Look for <strong class="text-white/80">Location tags</strong>, <strong class="text-white/80">GPS tags</strong>, or <strong class="text-white/80">Store location</strong> and turn it <strong class="text-emerald-400">ON</strong></span>
            </li>
            <li class="flex gap-2">
                <span class="shrink-0 text-white/30">4.</span>
                <span>If not in Camera settings: go to <strong class="text-white/80">Settings</strong> &rarr; <strong class="text-white/80">Location</strong> and make sure Location is <strong class="text-emerald-400">ON</strong></span>
            </li>
        </ol>

        <p class="mt-3 text-xs text-white/30">
            Once enabled, every photo you take will include GPS automatically. You only need to do this once.
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
