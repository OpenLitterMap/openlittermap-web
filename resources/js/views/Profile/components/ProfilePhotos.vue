<template>
    <div class="space-y-6">
        <div class="bg-white/5 border border-white/10 rounded-xl p-6 text-center">
            <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-3">{{ $t('Your Photos') }}</div>
            <div class="text-white text-3xl font-bold tabular-nums mb-2">
                {{ profileStore.stats.uploads?.toLocaleString() }}
            </div>
            <div class="text-white/40 text-sm mb-6">{{ $t('uploads across') }} {{ profileStore.locations.countries }} {{ $t('countries') }}</div>

            <router-link
                v-if="profileStore.stats.uploads > 0"
                to="/uploads"
                class="inline-block px-6 py-2.5 bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-lg font-semibold hover:bg-emerald-500/30 transition"
            >
                {{ $t('View & Manage Photos') }}
            </router-link>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <template v-if="profileStore.stats.uploads > 0">
                <router-link
                    to="/upload"
                    class="bg-white/5 border border-white/10 rounded-xl p-6 hover:bg-white/[0.08] transition group"
                >
                    <div class="text-emerald-400 text-lg font-semibold mb-1 group-hover:text-emerald-300">
                        {{ $t('Upload Photos') }}
                    </div>
                    <div class="text-white/40 text-sm">{{ $t('Upload new photos to the map') }}</div>
                </router-link>

                <router-link
                    to="/tag"
                    class="bg-white/5 border border-white/10 rounded-xl p-6 hover:bg-white/[0.08] transition group"
                >
                    <div class="text-emerald-400 text-lg font-semibold mb-1 group-hover:text-emerald-300">
                        {{ $t('Tag Photos') }}
                    </div>
                    <div class="text-white/40 text-sm">{{ $t('Tag unprocessed uploads with litter data') }}</div>
                </router-link>
            </template>

            <template v-else>
                <div class="bg-white/5 border border-white/10 rounded-xl p-6 opacity-60">
                    <div class="text-white/50 text-lg font-semibold mb-1">
                        {{ $t('Coming soon: How to upload') }}
                    </div>
                    <div class="text-white/30 text-sm">{{ $t('Upload new photos to the map') }}</div>
                </div>

                <div class="bg-white/5 border border-white/10 rounded-xl p-6 opacity-60">
                    <div class="text-white/50 text-lg font-semibold mb-1">
                        {{ $t('Coming soon: How to tag') }}
                    </div>
                    <div class="text-white/30 text-sm">{{ $t('Tag unprocessed uploads with litter data') }}</div>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { useProfileStore } from '@stores/profile.js';

const profileStore = useProfileStore();
</script>
