<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <section class="py-16 sm:py-20 bg-gradient-to-br from-black via-red-950 to-orange-950 relative overflow-hidden">
        <!-- Fancy smoke/wave background pattern -->
        <div class="absolute inset-0 opacity-10">
            <svg class="absolute bottom-0 w-full h-48" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
                <path
                    fill="currentColor"
                    class="text-red-600"
                    d="M0,160L48,176C96,192,192,224,288,224C384,224,480,192,576,181.3C672,171,768,181,864,186.7C960,192,1056,192,1152,170.7C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"
                ></path>
            </svg>
        </div>

        <div class="w-full mx-auto relative z-10 px-8 md:px-16 lg:px-20 xl:px-28">
            <div class="grid lg:grid-cols-2 items-center gap-8 md:gap-16 lg:gap-20 xl:gap-28">
                <!-- Left Column: Text -->
                <div class="text-white">
                    <!-- Decorative element -->
                    <div class="flex items-center mb-6">
                        <span
                            class="w-16 h-1.5 bg-gradient-to-r from-red-500 to-orange-500 rounded-full shadow-lg shadow-red-500/50"
                        ></span>
                        <span class="ml-4 text-red-400 font-semibold tracking-wider uppercase text-sm">{{
                            t('Environmental Impact')
                        }}</span>
                    </div>

                    <h2
                        class="text-4xl sm:text-5xl lg:text-6xl font-black mb-8 bg-gradient-to-r from-red-300 to-orange-300 bg-clip-text text-transparent leading-tight"
                    >
                        {{ t('Trillions of cigarette butts are leeching toxic chemicals into the environment.') }}
                    </h2>

                    <p class="text-xl sm:text-2xl text-red-100/90 mb-8 leading-relaxed font-light">
                        {{
                            t(
                                'They are poisoning water and releasing nicotine, arsenic, and microplastics into plants and animals.'
                            )
                        }}
                    </p>

                    <!-- Fancy stats boxes -->
                    <div class="grid grid-cols-2 gap-6 mt-10">
                        <div class="relative group">
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-red-600 to-orange-600 rounded-2xl blur-xl opacity-50 group-hover:opacity-75 transition duration-500"
                            ></div>
                            <div
                                class="relative bg-gradient-to-br from-red-900/40 to-orange-900/40 backdrop-blur-md border border-red-600/30 rounded-2xl p-6 transform hover:scale-105 transition duration-300"
                            >
                                <div
                                    class="text-3xl font-black text-transparent bg-gradient-to-r from-red-400 to-orange-400 bg-clip-text"
                                >
                                    ~150,000
                                </div>
                                <div class="text-sm text-red-200/90 mt-1">{{ t('dropped per second') }}</div>
                            </div>
                        </div>
                        <div class="relative group">
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-red-600 to-orange-600 rounded-2xl blur-xl opacity-50 group-hover:opacity-75 transition duration-500"
                            ></div>
                            <div
                                class="relative bg-gradient-to-br from-red-900/40 to-orange-900/40 backdrop-blur-md border border-red-600/30 rounded-2xl p-6 transform hover:scale-105 transition duration-300"
                            >
                                <div
                                    class="text-3xl font-black text-transparent bg-gradient-to-r from-red-400 to-orange-400 bg-clip-text"
                                >
                                    700+
                                </div>
                                <div class="text-sm text-red-200/90 mt-1">{{ t('Toxic chemicals') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Image -->
                <div class="relative group">
                    <!-- Enhanced multi-layer glow effect -->
                    <div
                        class="absolute -inset-3 bg-gradient-to-r from-red-500 via-orange-400 to-red-500 rounded-3xl blur-2xl opacity-60 group-hover:opacity-100 transition duration-700 animate-pulse"
                    ></div>
                    <div
                        class="absolute -inset-2 bg-gradient-to-r from-red-400 to-orange-500 rounded-3xl blur-lg opacity-40 group-hover:opacity-70 transition duration-500"
                    ></div>

                    <div class="relative">
                        <img
                            src="/resources/js/assets/images/butts.png"
                            alt="Cigarette butts"
                            class="rounded-3xl shadow-2xl w-full h-[500px] object-cover transform group-hover:scale-[1.02] transition-all duration-500 ring-1 ring-white/10"
                            loading="lazy"
                        />

                        <!-- Enhanced overlay gradients -->
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-red-950/60 via-transparent to-transparent rounded-3xl pointer-events-none"
                        ></div>
                        <div
                            class="absolute inset-0 bg-gradient-to-br from-transparent via-transparent to-orange-900/30 rounded-3xl pointer-events-none"
                        ></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Floating smoke particles animation -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="smoke smoke-1"></div>
            <div class="smoke smoke-2"></div>
            <div class="smoke smoke-3"></div>
        </div>
    </section>
</template>

<style scoped>
/* Smoke animation for fancy effect */
.smoke {
    position: absolute;
    background: radial-gradient(circle, rgba(239, 68, 68, 0.2) 0%, rgba(239, 68, 68, 0) 70%);
    border-radius: 50%;
    opacity: 0.4;
    animation: rise 15s infinite ease-out;
}

.smoke-1 {
    width: 100px;
    height: 100px;
    left: 15%;
    animation-delay: 0s;
}

.smoke-2 {
    width: 150px;
    height: 150px;
    left: 65%;
    animation-delay: 5s;
}

.smoke-3 {
    width: 80px;
    height: 80px;
    left: 35%;
    animation-delay: 10s;
}

@keyframes rise {
    0% {
        transform: translateY(100vh) translateX(0) scale(0.8);
        opacity: 0;
    }
    10% {
        opacity: 0.4;
    }
    50% {
        transform: translateY(50vh) translateX(20px) scale(1.2);
    }
    90% {
        opacity: 0.3;
    }
    100% {
        transform: translateY(-10vh) translateX(-20px) scale(1.5);
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
        opacity: 0.5;
    }
}

.animate-pulse {
    animation: pulse 2s ease-in-out infinite;
}

/* Respect reduced motion preference */
@media (prefers-reduced-motion: reduce) {
    .smoke {
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
