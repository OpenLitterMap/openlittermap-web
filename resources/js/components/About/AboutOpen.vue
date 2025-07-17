<script setup>
import { useI18n } from 'vue-i18n';
import { ref, onMounted } from 'vue';

const { t } = useI18n();

// Animation state
const dataPoints = ref(0);
const targetPoints = 10000000; // 10 million like OSM

// Animate counter on mount
onMounted(() => {
    const duration = 3000;
    const increment = targetPoints / (duration / 16);

    const timer = setInterval(() => {
        if (dataPoints.value < targetPoints) {
            dataPoints.value = Math.min(dataPoints.value + increment, targetPoints);
        } else {
            clearInterval(timer);
        }
    }, 16);
});

// Format number with commas
const formatNumber = (num) => {
    return Math.floor(num).toLocaleString();
};
</script>

<template>
    <section
        class="min-h-screen py-16 sm:py-20 bg-gradient-to-br from-slate-950 via-emerald-950 to-lime-950 relative overflow-hidden flex items-center"
    >
        <!-- Open data network background pattern -->
        <div class="absolute inset-0 opacity-20">
            <svg class="absolute top-0 w-full h-full" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 800">
                <defs>
                    <pattern id="network" width="200" height="200" patternUnits="userSpaceOnUse">
                        <!-- Network nodes -->
                        <circle cx="50" cy="50" r="3" fill="currentColor" class="text-emerald-500" />
                        <circle cx="150" cy="50" r="3" fill="currentColor" class="text-lime-500" />
                        <circle cx="100" cy="150" r="3" fill="currentColor" class="text-emerald-500" />
                        <circle cx="0" cy="100" r="3" fill="currentColor" class="text-lime-500" />
                        <circle cx="200" cy="100" r="3" fill="currentColor" class="text-lime-500" />
                        <!-- Connecting lines -->
                        <path
                            d="M50,50 L150,50 M150,50 L100,150 M100,150 L50,50 M0,100 L50,50 M150,50 L200,100"
                            stroke="currentColor"
                            stroke-width="0.5"
                            fill="none"
                            class="text-emerald-500"
                            opacity="0.3"
                        />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#network)" />
            </svg>

            <!-- Radial gradient overlay -->
            <div class="absolute inset-0 bg-radial-gradient from-transparent via-black/30 to-black/60"></div>
        </div>

        <div class="w-full mx-auto relative z-10 px-8 md:px-16 lg:px-20 xl:px-28">
            <div class="grid lg:grid-cols-2 items-center gap-8 md:gap-16 lg:gap-20 xl:gap-28">
                <!-- Left Column: Narrative -->
                <div class="text-white">
                    <!-- Open Science Badge -->
                    <div class="flex items-center mb-8">
                        <span
                            class="w-20 h-1.5 bg-gradient-to-r from-emerald-500 via-lime-500 to-teal-500 rounded-full shadow-lg shadow-emerald-500/50"
                        ></span>
                        <span class="ml-4 text-emerald-400 font-semibold tracking-wider uppercase text-sm">{{
                            t('Open Science Revolution')
                        }}</span>
                    </div>

                    <!-- Main heading with gradient -->
                    <h2 class="text-5xl sm:text-6xl lg:text-7xl font-black mb-8 leading-tight">
                        <span
                            class="bg-gradient-to-r from-emerald-200 via-lime-200 to-teal-200 bg-clip-text text-transparent"
                        >
                            {{ t('Open Systems') }}
                        </span>
                        <br />
                        <span class="text-white/90">
                            {{ t('are') }}
                        </span>
                        <br />
                        <span class="bg-gradient-to-r from-lime-300 to-emerald-300 bg-clip-text text-transparent">
                            {{ t('Better Systems.') }}
                        </span>
                    </h2>

                    <!-- Problem → Solution → Impact narrative -->
                    <div class="space-y-6 mb-10">
                        <!-- Problem -->
                        <div class="relative group">
                            <div
                                class="absolute -left-4 top-1 w-8 h-8 bg-red-500/20 rounded-full blur-xl group-hover:bg-red-500/30 transition"
                            ></div>
                            <div class="pl-6">
                                <h3 class="text-red-400 font-bold mb-2 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                        />
                                    </svg>
                                    {{ t('The Problem') }}
                                </h3>
                                <p class="text-gray-300 text-lg">
                                    {{ t('Most systems are locked away, allowing few people to participate.') }}
                                </p>
                            </div>
                        </div>

                        <!-- Solution -->
                        <div class="relative group">
                            <div
                                class="absolute -left-4 top-1 w-8 h-8 bg-emerald-500/20 rounded-full blur-xl group-hover:bg-emerald-500/30 transition"
                            ></div>
                            <div class="pl-6">
                                <h3 class="text-emerald-400 font-bold mb-2 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                    {{ t('Our Solution') }}
                                </h3>
                                <p class="text-gray-300 text-lg">
                                    {{
                                        t(
                                            'We made OpenLitterMap entirely open source so that anyone can participate in the data collection, coding, direction, or analysis. OpenLitterMap is the academic precursor to a global real-time data collection experience that intends to transform our relationship with technology, education, and institutions.'
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Impact -->
                        <div class="relative group">
                            <div
                                class="absolute -left-4 top-1 w-8 h-8 bg-lime-500/20 rounded-full blur-xl group-hover:bg-lime-500/30 transition"
                            ></div>
                            <div class="pl-6">
                                <h3 class="text-lime-400 font-bold mb-2 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M13 10V3L4 14h7v7l9-11h-7z"
                                        />
                                    </svg>
                                    {{ t('The Impact') }}
                                </h3>
                                <p class="text-gray-300 text-lg">
                                    {{
                                        t(
                                            'Real-time impact data on litter & plastic pollution that enables anyone in society to shape our understanding of reality.'
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- CTA Button -->
                    <button
                        class="group relative inline-flex items-center px-8 py-4 overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-600 to-lime-600 text-white font-bold text-lg shadow-2xl transform transition-all duration-300 hover:scale-105 hover:shadow-emerald-500/50"
                    >
                        <span class="relative z-10 flex items-center">
                            {{ t('Join the Open Data Movement') }}
                            <svg
                                class="w-5 h-5 ml-2 transform group-hover:translate-x-1 transition-transform"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 7l5 5m0 0l-5 5m5-5H6"
                                />
                            </svg>
                        </span>
                        <div
                            class="absolute inset-0 bg-gradient-to-r from-lime-600 to-emerald-600 opacity-0 group-hover:opacity-100 transition-opacity duration-300"
                        ></div>
                    </button>
                </div>

                <!-- Right Column: Interactive Visual -->
                <div class="relative">
                    <!-- Main globe container with enhanced glow -->
                    <div class="relative group">
                        <div
                            class="absolute -inset-4 bg-gradient-to-r from-emerald-600 via-lime-500 to-teal-600 rounded-full blur-3xl opacity-50 group-hover:opacity-70 transition duration-700 animate-pulse"
                        ></div>
                        <div
                            class="absolute -inset-3 bg-gradient-to-r from-lime-500 to-emerald-500 rounded-full blur-2xl opacity-30 group-hover:opacity-50 transition duration-500"
                        ></div>

                        <div
                            class="relative bg-gradient-to-br from-emerald-950/40 to-lime-950/40 backdrop-blur-xl rounded-full p-12 border border-emerald-500/20 shadow-2xl"
                        >
                            <!-- Central OSM-inspired visualization -->
                            <div class="relative w-64 h-64 mx-auto">
                                <!-- Globe icon -->
                                <svg
                                    class="absolute inset-0 w-full h-full text-emerald-400/20"
                                    fill="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm-2.29 6.165L12 10.455l2.29-2.29a6.97 6.97 0 011.945 3.516H13.88L12 9.801l-1.88 1.88H7.765a6.97 6.97 0 011.945-3.516zM7.11 13.68h2.355L12 16.215l2.535-2.535h2.355a6.97 6.97 0 01-1.945 3.516L12 14.251l-2.945 2.945A6.97 6.97 0 017.11 13.68z"
                                    />
                                </svg>

                                <!-- Animated data points -->
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="text-center">
                                        <div
                                            class="text-5xl font-black text-transparent bg-gradient-to-r from-emerald-400 to-lime-400 bg-clip-text mb-2"
                                        >
                                            {{ formatNumber(dataPoints) }}+
                                        </div>
                                        <div class="text-emerald-300 text-sm font-medium">
                                            {{ t('Open Data Points') }}
                                        </div>
                                        <div class="text-lime-400/60 text-xs mt-1">
                                            {{ t('Inspired by OpenStreetMap') }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Orbiting elements -->
                                <div class="absolute inset-0 animate-spin-slow">
                                    <div class="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-4">
                                        <div
                                            class="w-3 h-3 bg-gradient-to-r from-emerald-400 to-lime-400 rounded-full shadow-lg shadow-emerald-400/50"
                                        ></div>
                                    </div>
                                    <div class="absolute bottom-0 left-1/2 -translate-x-1/2 translate-y-4">
                                        <div
                                            class="w-3 h-3 bg-gradient-to-r from-lime-400 to-teal-400 rounded-full shadow-lg shadow-lime-400/50"
                                        ></div>
                                    </div>
                                    <div class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-4">
                                        <div
                                            class="w-3 h-3 bg-gradient-to-r from-teal-400 to-emerald-400 rounded-full shadow-lg shadow-teal-400/50"
                                        ></div>
                                    </div>
                                    <div class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-4">
                                        <div
                                            class="w-3 h-3 bg-gradient-to-r from-emerald-400 to-lime-400 rounded-full shadow-lg shadow-emerald-400/50"
                                        ></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stats badges -->
                            <div class="grid grid-cols-3 gap-4 mt-8">
                                <div class="text-center">
                                    <svg
                                        class="w-8 h-8 mx-auto mb-2 text-emerald-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                                        />
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"
                                        />
                                    </svg>
                                    <div class="text-xs text-emerald-300">{{ t('Capture') }}</div>
                                </div>
                                <div class="text-center">
                                    <svg
                                        class="w-8 h-8 mx-auto mb-2 text-lime-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"
                                        />
                                    </svg>
                                    <div class="text-xs text-lime-300">{{ t('Open Data') }}</div>
                                </div>
                                <div class="text-center">
                                    <svg
                                        class="w-8 h-8 mx-auto mb-2 text-teal-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                    <div class="text-xs text-teal-300">{{ t('Impact') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quote -->
                    <div class="mt-8 text-center">
                        <p class="text-emerald-300/80 text-sm italic">
                            {{
                                t(
                                    '"When systes are open, innovation is democratised, and unlimited change is possible."'
                                )
                            }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Floating open data particles -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="data-node data-node-1"></div>
            <div class="data-node data-node-2"></div>
            <div class="data-node data-node-3"></div>
            <div class="data-node data-node-4"></div>
            <div class="data-node data-node-5"></div>
        </div>
    </section>
</template>

<style scoped>
/* Radial gradient background */
.bg-radial-gradient {
    background: radial-gradient(ellipse at center, transparent 0%, rgba(0, 0, 0, 0.3) 50%, rgba(0, 0, 0, 0.6) 100%);
}

/* Slow spin animation */
@keyframes spin-slow {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.animate-spin-slow {
    animation: spin-slow 20s linear infinite;
}

/* Data node particles */
.data-node {
    position: absolute;
    width: 6px;
    height: 6px;
    background: linear-gradient(45deg, rgba(16, 185, 129, 0.8), rgba(132, 204, 22, 0.8));
    border-radius: 50%;
    box-shadow: 0 0 15px rgba(16, 185, 129, 0.6);
    animation: float-data 35s infinite ease-in-out;
}

.data-node::before {
    content: '';
    position: absolute;
    inset: -10px;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.3) 0%, transparent 70%);
    border-radius: 50%;
}

.data-node-1 {
    left: 5%;
    animation-delay: 0s;
    animation-duration: 30s;
}

.data-node-2 {
    left: 25%;
    animation-delay: 6s;
    animation-duration: 35s;
}

.data-node-3 {
    left: 50%;
    animation-delay: 12s;
    animation-duration: 32s;
}

.data-node-4 {
    left: 75%;
    animation-delay: 18s;
    animation-duration: 38s;
}

.data-node-5 {
    left: 90%;
    animation-delay: 24s;
    animation-duration: 33s;
}

@keyframes float-data {
    0% {
        transform: translateY(110vh) translateX(0) scale(0);
        opacity: 0;
    }
    10% {
        opacity: 1;
        transform: translateY(90vh) translateX(30px) scale(1);
    }
    90% {
        opacity: 1;
        transform: translateY(10vh) translateX(-30px) scale(1);
    }
    100% {
        transform: translateY(-10vh) translateX(0) scale(0);
        opacity: 0;
    }
}

/* Pulse animation */
@keyframes pulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

.animate-pulse {
    animation: pulse 4s ease-in-out infinite;
}

/* Respect reduced motion */
@media (prefers-reduced-motion: reduce) {
    .data-node {
        animation: none;
    }

    .animate-pulse {
        animation: none;
    }

    .animate-spin-slow {
        animation: none;
    }

    .transform {
        transform: none !important;
    }
}
</style>
