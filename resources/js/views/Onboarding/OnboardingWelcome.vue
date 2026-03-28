<template>
    <div class="relative min-h-[calc(100vh-72px)] bg-gradient-to-br from-slate-900 via-blue-900 to-emerald-900">
        <div class="flex min-h-[calc(100vh-72px)] flex-col items-center justify-center p-4">
            <div class="w-full max-w-lg">
                <div class="rounded-xl bg-white/5 border border-white/10 backdrop-blur-xl p-6 shadow-xl sm:p-8">
                    <!-- Header -->
                    <h1 class="mb-2 text-center text-2xl font-bold text-white sm:text-3xl">
                        Map litter in 3 steps
                    </h1>
                    <p class="mb-6 text-center text-white/60">
                        Upload a photo, tag what you see, and your data goes on the global map.
                    </p>

                    <!-- Step Indicator -->
                    <StepIndicator :current-step="1" />

                    <!-- Value prop -->
                    <div class="mt-6 space-y-3 text-sm text-white/50">
                        <div class="flex items-start gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span>Take a photo of litter with your phone — the GPS location is captured automatically.</span>
                        </div>
                        <div class="flex items-start gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            <span>Tag what you see — cigarette butts, bottles, wrappers. One tag is enough.</span>
                        </div>
                        <div class="flex items-start gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                            </svg>
                            <span>Your data appears on the global litter map — open data for everyone.</span>
                        </div>
                    </div>

                    <!-- CTA -->
                    <button
                        @click="router.push('/onboarding/upload')"
                        class="mt-8 w-full rounded-lg bg-emerald-500 py-3 text-center font-semibold text-white transition-colors hover:bg-emerald-400"
                    >
                        Get started
                    </button>

                    <!-- Skip -->
                    <button
                        @click="skipOnboarding"
                        :disabled="isSkipping"
                        class="mt-3 w-full text-center text-sm text-white/30 transition-colors hover:text-white/50"
                    >
                        {{ isSkipping ? 'Skipping...' : 'Skip for now' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useUserStore } from '@/stores/user';
import StepIndicator from '@/components/onboarding/StepIndicator.vue';

const router = useRouter();
const userStore = useUserStore();
const isSkipping = ref(false);

async function skipOnboarding() {
    isSkipping.value = true;

    try {
        await axios.post('/api/user/onboarding/skip');
        await userStore.REFRESH_USER();
        router.push('/upload');
    } catch {
        isSkipping.value = false;
    }
}
</script>
