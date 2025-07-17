<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <section class="py-16 sm:py-20 bg-gradient-to-br from-blue-950 via-blue-900 to-cyan-900 relative overflow-hidden">
        <!-- Fancy wave background pattern -->
        <div class="absolute inset-0 opacity-10">
            <svg class="absolute bottom-0 w-full h-48" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
                <path
                    fill="currentColor"
                    class="text-blue-400"
                    d="M0,160L48,176C96,192,192,224,288,224C384,224,480,192,576,181.3C672,171,768,181,864,186.7C960,192,1056,192,1152,170.7C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"
                ></path>
            </svg>

            <!-- Additional gradient overlay for depth -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>
        </div>

        <div class="w-full mx-auto relative z-10 px-8 md:px-16 lg:px-20 xl:px-28">
            <div class="grid lg:grid-cols-2 items-center gap-8 md:gap-16 lg:gap-20 xl:gap-28">
                <!-- Left Column: Image -->
                <div class="relative group">
                    <!-- Enhanced multi-layer glow effect -->
                    <div
                        class="absolute -inset-3 bg-gradient-to-r from-cyan-500 via-blue-400 to-teal-500 rounded-3xl blur-2xl opacity-60 group-hover:opacity-100 transition duration-700 animate-pulse"
                    ></div>
                    <div
                        class="absolute -inset-2 bg-gradient-to-r from-cyan-400 to-blue-500 rounded-3xl blur-lg opacity-40 group-hover:opacity-70 transition duration-500"
                    ></div>

                    <div class="relative">
                        <img
                            src="/resources/js/assets/images/microplastics_oranmore.png"
                            alt="Ocean view"
                            class="rounded-3xl shadow-2xl w-full h-[500px] object-cover transform group-hover:scale-[1.02] transition-all duration-500 ring-1 ring-white/10"
                            loading="lazy"
                        />

                        <!-- Enhanced overlay gradients -->
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-blue-950/60 via-transparent to-transparent rounded-3xl pointer-events-none"
                        ></div>
                        <div
                            class="absolute inset-0 bg-gradient-to-br from-transparent via-transparent to-cyan-900/30 rounded-3xl pointer-events-none"
                        ></div>

                        <!-- Location badge -->
                        <div
                            class="absolute bottom-6 left-6 bg-black/80 backdrop-blur-xl rounded-2xl px-5 py-3 border border-cyan-500/20 shadow-2xl"
                        >
                            <div class="flex items-center space-x-2">
                                <div class="w-2 h-2 bg-cyan-400 rounded-full animate-pulse"></div>
                                <span class="text-cyan-300 text-sm font-semibold tracking-wide">{{
                                    t('Oranmore, Ireland')
                                }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Text -->
                <div class="text-white">
                    <!-- Enhanced decorative element -->
                    <div class="flex items-center mb-6">
                        <span
                            class="w-16 h-1.5 bg-gradient-to-r from-cyan-500 via-blue-500 to-teal-500 rounded-full shadow-lg shadow-cyan-500/50"
                        ></span>
                        <span class="ml-4 text-cyan-400 font-semibold tracking-wider uppercase text-sm">{{
                            t('Plastic pollution')
                        }}</span>
                    </div>

                    <h2
                        class="text-4xl sm:text-5xl lg:text-6xl font-black mb-8 bg-gradient-to-r from-cyan-200 via-blue-200 to-teal-200 bg-clip-text text-transparent leading-tight"
                    >
                        {{
                            t(
                                'There is about 300 million tonnes of plastic starting to fragment exponentially across the worlds oceans.'
                            )
                        }}
                    </h2>

                    <p class="text-xl sm:text-2xl text-blue-100/90 mb-8 leading-relaxed font-light">
                        {{
                            t(
                                'Microplastics can be found on any beach in the world, found here in Oranmore Co. Galway, Ireland, around the corner from the Marine Institute. How many can you find on the next beach you visit?'
                            )
                        }}
                    </p>

                    <!-- Ocean impact stats -->
                    <div class="grid grid-cols-3 gap-6 mt-10">
                        <div class="relative group">
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-cyan-600 to-blue-600 rounded-2xl blur-xl opacity-50 group-hover:opacity-75 transition duration-500"
                            ></div>
                            <div
                                class="relative bg-gradient-to-br from-blue-900/40 to-cyan-900/40 backdrop-blur-md border border-cyan-600/30 rounded-2xl p-6 transform hover:scale-105 transition duration-300"
                            >
                                <div
                                    class="text-3xl font-black text-transparent bg-gradient-to-r from-cyan-400 to-blue-400 bg-clip-text"
                                >
                                    5.25T
                                </div>
                                <div class="text-sm text-blue-200/90 mt-1">{{ t('Plastic pieces') }}</div>
                            </div>
                        </div>
                        <div class="relative group">
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-cyan-600 to-blue-600 rounded-2xl blur-xl opacity-50 group-hover:opacity-75 transition duration-500"
                            ></div>
                            <div
                                class="relative bg-gradient-to-br from-blue-900/40 to-cyan-900/40 backdrop-blur-md border border-cyan-600/30 rounded-2xl p-6 transform hover:scale-105 transition duration-300"
                            >
                                <div
                                    class="text-3xl font-black text-transparent bg-gradient-to-r from-cyan-400 to-blue-400 bg-clip-text"
                                >
                                    1M+
                                </div>
                                <div class="text-sm text-blue-200/90 mt-1">{{ t('Species affected') }}</div>
                            </div>
                        </div>
                        <div class="relative group">
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-cyan-600 to-blue-600 rounded-2xl blur-xl opacity-50 group-hover:opacity-75 transition duration-500"
                            ></div>
                            <div
                                class="relative bg-gradient-to-br from-blue-900/40 to-cyan-900/40 backdrop-blur-md border border-cyan-600/30 rounded-2xl p-6 transform hover:scale-105 transition duration-300"
                            >
                                <div
                                    class="text-3xl font-black text-transparent bg-gradient-to-r from-cyan-400 to-blue-400 bg-clip-text"
                                >
                                    2050
                                </div>
                                <div class="text-sm text-blue-200/90 mt-1">{{ t('More plastic than fish') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced floating particles animation -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="bubble bubble-1"></div>
            <div class="bubble bubble-2"></div>
            <div class="bubble bubble-3"></div>
            <div class="bubble bubble-4"></div>
        </div>
    </section>
</template>

<style scoped>
/* Enhanced bubble animation for ocean effect */
.bubble {
    position: absolute;
    background: radial-gradient(
        circle,
        rgba(34, 211, 238, 0.4) 0%,
        rgba(34, 211, 238, 0.1) 40%,
        rgba(34, 211, 238, 0) 70%
    );
    border-radius: 50%;
    opacity: 0;
    animation: float 25s infinite ease-in-out;
    filter: blur(1px);
}

.bubble::before {
    content: '';
    position: absolute;
    inset: 25%;
    background: radial-gradient(circle, rgba(34, 211, 238, 0.8) 0%, rgba(34, 211, 238, 0.4) 50%, transparent 70%);
    border-radius: 50%;
    box-shadow: 0 0 20px rgba(34, 211, 238, 0.6);
}

.bubble::after {
    content: '';
    position: absolute;
    inset: 40%;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 50%;
    animation: shimmer 3s infinite ease-in-out;
}

.bubble-1 {
    width: 80px;
    height: 80px;
    left: 10%;
    animation-delay: 0s;
    animation-duration: 22s;
}

.bubble-2 {
    width: 120px;
    height: 120px;
    left: 70%;
    animation-delay: 5s;
    animation-duration: 28s;
}

.bubble-3 {
    width: 60px;
    height: 60px;
    left: 40%;
    animation-delay: 10s;
    animation-duration: 25s;
}

.bubble-4 {
    width: 90px;
    height: 90px;
    left: 85%;
    animation-delay: 15s;
    animation-duration: 30s;
}

@keyframes float {
    0% {
        transform: translateY(110vh) translateX(0) scale(0.8);
        opacity: 0;
    }
    5% {
        opacity: 0.7;
    }
    25% {
        transform: translateY(70vh) translateX(40px) scale(1);
    }
    50% {
        transform: translateY(40vh) translateX(-20px) scale(1.1);
        opacity: 0.9;
    }
    75% {
        transform: translateY(20vh) translateX(30px) scale(1);
    }
    95% {
        opacity: 0.7;
    }
    100% {
        transform: translateY(-10vh) translateX(-30px) scale(0.8);
        opacity: 0;
    }
}

@keyframes shimmer {
    0%,
    100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(0.7);
        opacity: 0.7;
    }
}

/* Pulse animation */
@keyframes pulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.animate-pulse {
    animation: pulse 2s ease-in-out infinite;
}

/* Respect reduced motion preference */
@media (prefers-reduced-motion: reduce) {
    .bubble {
        animation: none;
    }

    .animate-pulse {
        animation: none;
    }

    .transform {
        transform: none !important;
    }
}
</style>
