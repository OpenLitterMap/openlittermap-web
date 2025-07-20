<script setup>
import { useI18n } from 'vue-i18n';
import { ref } from 'vue';

const { t } = useI18n();

// State for showing geolocation instructions
const showGeoInstructions = ref(false);
const selectedPlatform = ref('ios');

const steps = [
    {
        number: '1',
        title: 'Collect data (take a photo)',
        description: 'Make sure location services are enabled!',
        subtext:
            'When you take a photo the time and location are automatically recorded in the image. You can use this to tell a real-world story and include whatever you want in each image: from a single cigarette butt to the contents of an entire cleanup, or an overflowing bin, a blocked grain, racist graffiti, and lots more.',
        image: '/resources/js/assets/images/iphone.png',
        color: 'from-blue-500 to-cyan-500',
        icon: '📸',
    },
    {
        number: '2',
        title: 'Add Tags',
        description:
            'You can choose from our pre-defined list (200+ tags) or enter any text you want to go beyond litter and map anything.',
        subtext:
            'Litter is the first application of this technology. We are developing real-world impact mapping and data collection tools to transform education, scientific inclusion and upgrade societies citizen science capacity.',
        image: '/resources/js/assets/images/facemask-tag.png',
        color: 'from-purple-500 to-pink-500',
        icon: '🏷️',
    },
    {
        number: '3',
        title: 'Upload It',
        description: 'Upload anytime! See live notifications on the global map and climb the leaderboards!',
        subtext:
            'You can upload your data anytime. All historical data will update and the global map will show live notifications to anyone viewing. Every upload generates a unique geolink that you can use to send anyone to any observation globally in real-time.',
        image: '/resources/js/assets/images/facemask-map.png',
        color: 'from-green-500 to-emerald-500',
        icon: '🚀',
    },
];
</script>

<template>
    <section class="py-16 sm:py-20 bg-gradient-to-br from-slate-950 via-teal-950 to-blue-950 relative overflow-hidden">
        <!-- Background pattern -->
        <div class="absolute inset-0 opacity-10">
            <svg class="absolute top-0 w-full h-full" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 800">
                <defs>
                    <pattern id="grid-dots" width="50" height="50" patternUnits="userSpaceOnUse">
                        <circle cx="25" cy="25" r="2" fill="currentColor" class="text-teal-500" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid-dots)" />
            </svg>

            <!-- Gradient overlay -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent"></div>
        </div>

        <div class="w-full mx-auto relative z-10 px-8 md:px-16 lg:px-20 xl:px-28">
            <!-- Header -->
            <div class="text-center mb-16">
                <!-- Decorative element -->
                <div class="flex items-center justify-center mb-8">
                    <span
                        class="w-20 h-1.5 bg-gradient-to-r from-teal-500 via-cyan-500 to-blue-500 rounded-full shadow-lg shadow-cyan-500/50"
                    ></span>
                    <span class="mx-4 text-cyan-400 font-semibold tracking-wider uppercase text-sm">{{
                        t('Get Started')
                    }}</span>
                    <span
                        class="w-20 h-1.5 bg-gradient-to-r from-blue-500 via-cyan-500 to-teal-500 rounded-full shadow-lg shadow-cyan-500/50"
                    ></span>
                </div>

                <h2
                    class="text-5xl sm:text-6xl lg:text-7xl font-black mb-6 bg-gradient-to-r from-teal-200 via-cyan-200 to-blue-200 bg-clip-text text-transparent leading-tight"
                >
                    {{ t('How It Works') }}
                </h2>

                <p class="text-xl sm:text-2xl text-cyan-100/70 max-w-3xl mx-auto">
                    {{
                        t(
                            'Transform your phone into a powerful data collection tool for environmental and social impact'
                        )
                    }}
                </p>
            </div>

            <!-- Steps -->
            <div class="space-y-20 lg:space-y-28 max-w-7xl mx-auto">
                <div
                    v-for="(step, index) in steps"
                    :key="step.number"
                    :class="[
                        'grid lg:grid-cols-2 gap-8 md:gap-16 lg:gap-20 xl:gap-28 items-center',
                        index % 2 === 1 ? 'lg:direction-rtl' : '',
                    ]"
                >
                    <!-- Text content -->
                    <div :class="index % 2 === 1 ? 'lg:order-2' : ''" class="text-white">
                        <!-- Step number with gradient -->
                        <div class="flex items-center mb-6">
                            <div class="relative">
                                <div
                                    :class="`absolute -inset-3 bg-gradient-to-r ${step.color} rounded-full blur-xl opacity-50`"
                                ></div>
                                <div
                                    :class="`relative w-20 h-20 bg-gradient-to-r ${step.color} rounded-full flex items-center justify-center shadow-2xl`"
                                >
                                    <span class="text-3xl font-black text-white">{{ step.number }}</span>
                                </div>
                            </div>
                            <div class="ml-6">
                                <span class="text-4xl">{{ step.icon }}</span>
                            </div>
                        </div>

                        <h3
                            :class="`text-4xl sm:text-5xl font-bold mb-6 bg-gradient-to-r ${step.color} bg-clip-text text-transparent`"
                        >
                            {{ t(step.title) }}
                        </h3>

                        <p class="text-xl sm:text-2xl text-gray-300 mb-6 leading-relaxed">
                            {{ t(step.description) }}
                        </p>

                        <p class="text-lg text-gray-400 mb-8 leading-relaxed">
                            {{ t(step.subtext) }}
                        </p>

                        <!-- Special content for step 1 -->
                        <div v-if="step.number === '1'" class="space-y-4 mt-6">
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
                                <div
                                    class="text-center p-3 bg-blue-900/30 backdrop-blur-sm border border-blue-500/30 rounded-xl"
                                >
                                    <div class="text-lg mb-1">🚬</div>
                                    <div class="text-xs text-blue-300">{{ t('Single Item') }}</div>
                                </div>
                                <div
                                    class="text-center p-3 bg-cyan-900/30 backdrop-blur-sm border border-cyan-500/30 rounded-xl"
                                >
                                    <div class="text-lg mb-1">🗑️</div>
                                    <div class="text-xs text-cyan-300">{{ t('Full Cleanup') }}</div>
                                </div>
                                <div
                                    class="text-center p-3 bg-blue-900/30 backdrop-blur-sm border border-blue-500/30 rounded-xl"
                                >
                                    <div class="text-lg mb-1">🚮</div>
                                    <div class="text-xs text-blue-300">{{ t('Overflowing Bins') }}</div>
                                </div>
                                <div
                                    class="text-center p-3 bg-cyan-900/30 backdrop-blur-sm border border-cyan-500/30 rounded-xl"
                                >
                                    <div class="text-lg mb-1">🎨</div>
                                    <div class="text-xs text-cyan-300">{{ t('Graffiti') }}</div>
                                </div>
                            </div>

                            <button
                                @click="showGeoInstructions = !showGeoInstructions"
                                class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-cyan-600 to-blue-600 text-white font-medium rounded-xl hover:from-cyan-700 hover:to-blue-700 transform hover:scale-105 transition-all duration-300 shadow-lg hover:shadow-cyan-500/25"
                            >
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                                    />
                                </svg>
                                {{ t('How to Enable Geotagging') }}
                            </button>

                            <!-- Geolocation instructions -->
                            <transition name="slide-fade">
                                <div v-if="showGeoInstructions" class="mt-6">
                                    <!-- Platform selector -->
                                    <div class="flex gap-4 mb-6">
                                        <button
                                            @click="selectedPlatform = 'ios'"
                                            :class="[
                                                'px-4 py-2 rounded-lg font-medium transition-all',
                                                selectedPlatform === 'ios'
                                                    ? 'bg-gradient-to-r from-cyan-600 to-blue-600 text-white'
                                                    : 'bg-slate-800 text-gray-400 hover:text-white',
                                            ]"
                                        >
                                            🍎 iPhone
                                        </button>
                                        <button
                                            @click="selectedPlatform = 'android'"
                                            :class="[
                                                'px-4 py-2 rounded-lg font-medium transition-all',
                                                selectedPlatform === 'android'
                                                    ? 'bg-gradient-to-r from-green-600 to-emerald-600 text-white'
                                                    : 'bg-slate-800 text-gray-400 hover:text-white',
                                            ]"
                                        >
                                            🤖 Android
                                        </button>
                                    </div>

                                    <!-- Instructions -->
                                    <div
                                        class="bg-slate-900/80 backdrop-blur-xl rounded-2xl p-6 border border-cyan-500/20"
                                    >
                                        <div v-if="selectedPlatform === 'ios'" class="space-y-3">
                                            <h4 class="text-cyan-400 font-bold mb-4">
                                                {{ t('iPhone Geotagging Instructions:') }}
                                            </h4>
                                            <ol class="space-y-2 text-gray-300">
                                                <li class="flex items-start">
                                                    <span class="text-cyan-400 mr-2">1.</span>
                                                    {{ t('Open Settings → Privacy & Security → Location Services') }}
                                                </li>
                                                <li class="flex items-start">
                                                    <span class="text-cyan-400 mr-2">2.</span>
                                                    {{ t('Make sure Location Services is ON') }}
                                                </li>
                                                <li class="flex items-start">
                                                    <span class="text-cyan-400 mr-2">3.</span>
                                                    {{ t('Find Camera and set to "While Using App"') }}
                                                </li>
                                                <li class="flex items-start">
                                                    <span class="text-cyan-400 mr-2">4.</span>
                                                    {{
                                                        t('Find OpenLitterMap and set to "Always" or "While Using App"')
                                                    }}
                                                </li>
                                            </ol>
                                        </div>
                                        <div v-else class="space-y-3">
                                            <h4 class="text-emerald-400 font-bold mb-4">
                                                {{ t('Android Geotagging Instructions:') }}
                                            </h4>
                                            <ol class="space-y-2 text-gray-300">
                                                <li class="flex items-start">
                                                    <span class="text-emerald-400 mr-2">1.</span>
                                                    {{ t('Open Camera app → Settings (gear icon)') }}
                                                </li>
                                                <li class="flex items-start">
                                                    <span class="text-emerald-400 mr-2">2.</span>
                                                    {{ t('Enable "Save location" or "Location tags"') }}
                                                </li>
                                                <li class="flex items-start">
                                                    <span class="text-emerald-400 mr-2">3.</span>
                                                    {{ t('Go to Settings → Location → App permissions') }}
                                                </li>
                                                <li class="flex items-start">
                                                    <span class="text-emerald-400 mr-2">4.</span>
                                                    {{ t('Set Camera and OpenLitterMap to "Allow all the time"') }}
                                                </li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                            </transition>
                        </div>

                        <!-- Special content for step 2 -->
                        <div v-if="step.number === '2'" class="flex flex-wrap gap-3 mb-6">
                            <span
                                class="px-4 py-2 bg-purple-900/30 backdrop-blur-sm border border-purple-500/30 rounded-full text-purple-300 text-sm"
                            >
                                {{ t('200+ Pre-defined Tags') }}
                            </span>
                            <span
                                class="px-4 py-2 bg-pink-900/30 backdrop-blur-sm border border-pink-500/30 rounded-full text-pink-300 text-sm"
                            >
                                {{ t('Custom Text') }}
                            </span>
                            <span
                                class="px-4 py-2 bg-purple-900/30 backdrop-blur-sm border border-purple-500/30 rounded-full text-purple-300 text-sm"
                            >
                                {{ t('Beyond Litter') }}
                            </span>
                            <span
                                class="px-4 py-2 bg-pink-900/30 backdrop-blur-sm border border-pink-500/30 rounded-full text-pink-300 text-sm"
                            >
                                {{ t('Map Anything') }}
                            </span>
                        </div>

                        <!-- Special content for step 3 -->
                        <div v-if="step.number === '3'" class="grid grid-cols-3 gap-4 mb-6">
                            <div
                                class="text-center p-4 bg-emerald-900/30 backdrop-blur-sm border border-emerald-500/30 rounded-xl"
                            >
                                <div class="text-2xl mb-2">🔔</div>
                                <div class="text-sm text-emerald-300">{{ t('Live Notifications') }}</div>
                            </div>
                            <div
                                class="text-center p-4 bg-green-900/30 backdrop-blur-sm border border-green-500/30 rounded-xl"
                            >
                                <div class="text-2xl mb-2">🔗</div>
                                <div class="text-sm text-green-300">{{ t('Unique Geolinks') }}</div>
                            </div>
                            <div
                                class="text-center p-4 bg-emerald-900/30 backdrop-blur-sm border border-emerald-500/30 rounded-xl"
                            >
                                <div class="text-2xl mb-2">🏆</div>
                                <div class="text-sm text-emerald-300">{{ t('Leaderboards') }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Image -->
                    <div :class="index % 2 === 1 ? 'lg:order-1' : ''" class="relative group">
                        <!-- Multi-layer glow -->
                        <div
                            :class="`absolute -inset-3 bg-gradient-to-r ${step.color} rounded-3xl blur-2xl opacity-40 group-hover:opacity-60 transition duration-700`"
                        ></div>
                        <div
                            :class="`absolute -inset-2 bg-gradient-to-r ${step.color} rounded-3xl blur-lg opacity-30 group-hover:opacity-50 transition duration-500`"
                        ></div>

                        <div class="relative">
                            <!-- Step-specific images -->
                            <img
                                v-if="step.number === '1'"
                                src="/resources/js/assets/images/iphone.png"
                                :alt="`Step ${step.number}: ${step.title}`"
                                class="rounded-3xl shadow-2xl w-full h-auto transform group-hover:scale-[1.02] transition-all duration-500 ring-1 ring-white/10"
                                loading="eager"
                            />
                            <img
                                v-else-if="step.number === '2'"
                                src="/resources/js/assets/images/facemask-tag.png"
                                :alt="`Step ${step.number}: ${step.title}`"
                                class="rounded-3xl shadow-2xl w-full h-auto transform group-hover:scale-[1.02] transition-all duration-500 ring-1 ring-white/10"
                                loading="eager"
                            />
                            <img
                                v-else-if="step.number === '3'"
                                src="/resources/js/assets/images/facemask-map.png"
                                :alt="`Step ${step.number}: ${step.title}`"
                                class="rounded-3xl shadow-2xl w-full h-auto transform group-hover:scale-[1.02] transition-all duration-500 ring-1 ring-white/10"
                                loading="eager"
                            />

                            <!-- Overlay gradient -->
                            <div
                                :class="`absolute inset-0 bg-gradient-to-t ${step.color} opacity-10 rounded-3xl pointer-events-none`"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom CTA -->
            <div class="text-center mt-20">
                <h3
                    class="text-3xl sm:text-4xl font-bold mb-8 text-transparent bg-gradient-to-r from-cyan-300 to-blue-300 bg-clip-text"
                >
                    {{ t('Ready to Make a Difference?') }}
                </h3>
                <button
                    class="group relative inline-flex items-center px-10 py-5 overflow-hidden rounded-2xl bg-gradient-to-r from-cyan-600 to-blue-600 text-white font-bold text-xl shadow-2xl transform transition-all duration-300 hover:scale-105 hover:shadow-cyan-500/50"
                >
                    <span class="relative z-10 flex items-center">
                        {{ t('Download the App') }}
                        <svg
                            class="w-6 h-6 ml-3 transform group-hover:translate-x-1 transition-transform"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 4v16m8-8l-8 8-8-8"
                            />
                        </svg>
                    </span>
                    <div
                        class="absolute inset-0 bg-gradient-to-r from-blue-600 to-cyan-600 opacity-0 group-hover:opacity-100 transition-opacity duration-300"
                    ></div>
                </button>
            </div>
        </div>

        <!-- Floating particles -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="step-particle step-particle-1"></div>
            <div class="step-particle step-particle-2"></div>
            <div class="step-particle step-particle-3"></div>
        </div>
    </section>
</template>

<style scoped>
/* Slide fade transition */
.slide-fade-enter-active,
.slide-fade-leave-active {
    transition: all 0.3s ease;
}

.slide-fade-enter-from,
.slide-fade-leave-to {
    transform: translateY(-10px);
    opacity: 0;
}

/* Step particles */
.step-particle {
    position: absolute;
    width: 6px;
    height: 6px;
    background: linear-gradient(45deg, rgba(6, 182, 212, 0.8), rgba(59, 130, 246, 0.8));
    border-radius: 50%;
    box-shadow: 0 0 15px rgba(6, 182, 212, 0.6);
    animation: float-diagonal 35s infinite ease-in-out;
}

.step-particle::before {
    content: '';
    position: absolute;
    inset: -10px;
    background: radial-gradient(circle, rgba(6, 182, 212, 0.3) 0%, transparent 70%);
    border-radius: 50%;
}

.step-particle-1 {
    left: 10%;
    animation-delay: 0s;
}

.step-particle-2 {
    left: 50%;
    animation-delay: 12s;
}

.step-particle-3 {
    left: 85%;
    animation-delay: 24s;
}

@keyframes float-diagonal {
    0% {
        transform: translate(0, 110vh) scale(0);
        opacity: 0;
    }
    10% {
        opacity: 1;
        transform: translate(50px, 90vh) scale(1);
    }
    90% {
        opacity: 1;
        transform: translate(-50px, 10vh) scale(1);
    }
    100% {
        transform: translate(0, -10vh) scale(0);
        opacity: 0;
    }
}

/* RTL support for alternating layout */
.direction-rtl {
    direction: rtl;
}

.direction-rtl > * {
    direction: ltr;
}

/* Respect reduced motion */
@media (prefers-reduced-motion: reduce) {
    .step-particle {
        animation: none;
    }

    .transform {
        transform: none !important;
    }
}
</style>
