<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <section
        class="py-16 sm:py-20 bg-gradient-to-br from-slate-950 via-violet-950 to-purple-950 relative overflow-hidden"
    >
        <!-- Tech grid background pattern -->
        <div class="absolute inset-0 opacity-10">
            <svg class="absolute top-0 w-full h-full" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 800">
                <defs>
                    <pattern id="circuit" width="100" height="100" patternUnits="userSpaceOnUse">
                        <circle cx="10" cy="10" r="2" fill="currentColor" class="text-violet-500" />
                        <circle cx="90" cy="10" r="2" fill="currentColor" class="text-violet-500" />
                        <circle cx="90" cy="90" r="2" fill="currentColor" class="text-violet-500" />
                        <circle cx="10" cy="90" r="2" fill="currentColor" class="text-violet-500" />
                        <path
                            d="M10,10 L90,10 M90,10 L90,90 M90,90 L10,90 M10,90 L10,10"
                            stroke="currentColor"
                            stroke-width="0.5"
                            fill="none"
                            class="text-violet-500"
                            opacity="0.3"
                        />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#circuit)" />
            </svg>

            <!-- Additional gradient overlay -->
            <div class="absolute inset-0 bg-gradient-to-b from-black/30 via-transparent to-black/50"></div>
        </div>

        <div class="w-full mx-auto relative z-10 px-8 md:px-16 lg:px-20 xl:px-28">
            <!-- Header Section -->
            <div class="text-center mb-16">
                <!-- Decorative element -->
                <div class="flex items-center justify-center mb-8">
                    <span
                        class="w-20 h-1.5 bg-gradient-to-r from-violet-500 via-purple-500 to-pink-500 rounded-full shadow-lg shadow-purple-500/50"
                    ></span>
                    <span class="mx-4 text-purple-400 font-semibold tracking-wider uppercase text-sm">{{
                        t('Digital Revolution')
                    }}</span>
                    <span
                        class="w-20 h-1.5 bg-gradient-to-r from-pink-500 via-purple-500 to-violet-500 rounded-full shadow-lg shadow-purple-500/50"
                    ></span>
                </div>

                <h2
                    class="text-4xl sm:text-5xl lg:text-6xl font-black mb-8 bg-gradient-to-r from-violet-200 via-purple-200 to-pink-200 bg-clip-text text-transparent leading-tight"
                >
                    {{ t('Your device is not just a phone, its a supercomputer.') }}
                </h2>

                <!-- Main stat with animation -->
                <div class="relative inline-block mb-8">
                    <div
                        class="absolute inset-0 bg-gradient-to-r from-violet-600 to-purple-600 blur-3xl opacity-50 animate-pulse"
                    ></div>
                    <div
                        class="relative text-7xl sm:text-8xl lg:text-9xl font-black text-transparent bg-gradient-to-r from-violet-400 via-purple-400 to-pink-400 bg-clip-text"
                    >
                        5/8
                    </div>
                    <div class="text-2xl sm:text-3xl text-purple-300 font-light mt-2">
                        {{ t('billion people connected online') }}
                    </div>
                </div>

                <p class="text-xl sm:text-2xl text-purple-100/80 max-w-4xl mx-auto mb-6 leading-relaxed">
                    {{ t('Nobody has received any training in the data collection purpose of technology.') }}
                </p>

                <p class="text-xl sm:text-2xl text-purple-100/80 max-w-4xl mx-auto mb-6 leading-relaxed">
                    {{ t('Until now.') }}
                </p>
            </div>

            <!-- Comparison grid with enhanced styling -->
            <div class="grid lg:grid-cols-2 gap-8 md:gap-16 lg:gap-20 xl:gap-28 max-w-7xl mx-auto mb-20">
                <!-- Pointing at Humans Side -->
                <div class="relative group">
                    <div
                        class="absolute -inset-2 bg-gradient-to-r from-red-600 to-pink-600 rounded-3xl blur-xl opacity-40 group-hover:opacity-60 transition duration-500"
                    ></div>
                    <div
                        class="relative bg-gradient-to-br from-red-950/60 to-pink-950/60 backdrop-blur-xl rounded-3xl p-10 border border-red-500/20 h-full"
                    >
                        <div
                            class="absolute -top-6 -right-6 bg-gradient-to-r from-red-500 to-pink-500 text-white rounded-2xl w-16 h-16 flex items-center justify-center text-3xl shadow-2xl shadow-red-500/50 transform rotate-12"
                        >
                            👎
                        </div>
                        <h3
                            class="text-3xl font-bold mb-8 text-transparent bg-gradient-to-r from-red-300 to-pink-300 bg-clip-text"
                        >
                            {{ t('Pointing at Humans') }}
                        </h3>
                        <ul class="space-y-6">
                            <li class="flex items-start group/item">
                                <span class="text-red-400 mr-4 text-2xl group-hover/item:scale-125 transition-transform"
                                    >✗</span
                                >
                                <span class="text-red-100/90 text-lg">{{ t('Destroys personal privacy.') }}</span>
                            </li>
                            <li class="flex items-start group/item">
                                <span class="text-red-400 mr-4 text-2xl group-hover/item:scale-125 transition-transform"
                                    >✗</span
                                >
                                <span class="text-red-100/90 text-lg">{{
                                    t('Honeypot for governments and corporations.')
                                }}</span>
                            </li>
                            <li class="flex items-start group/item">
                                <span class="text-red-400 mr-4 text-2xl group-hover/item:scale-125 transition-transform"
                                    >✗</span
                                >
                                <span class="text-red-100/90 text-lg">{{
                                    t('Human connection becomes increasingly synthetic and augmented.')
                                }}</span>
                            </li>
                            <li class="flex items-start group/item">
                                <span class="text-red-400 mr-4 text-2xl group-hover/item:scale-125 transition-transform"
                                    >✗</span
                                >
                                <span class="text-red-100/90 text-lg">{{ t('Mental health & social crisis.') }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Pointing at Nature Side -->
                <div class="relative group">
                    <div
                        class="absolute -inset-2 bg-gradient-to-r from-green-600 to-teal-600 rounded-3xl blur-xl opacity-40 group-hover:opacity-60 transition duration-500"
                    ></div>
                    <div
                        class="relative bg-gradient-to-br from-green-950/60 to-teal-950/60 backdrop-blur-xl rounded-3xl p-10 border border-green-500/20 h-full"
                    >
                        <div
                            class="absolute -top-6 -right-6 bg-gradient-to-r from-green-500 to-teal-500 text-white rounded-2xl w-16 h-16 flex items-center justify-center text-3xl shadow-2xl shadow-green-500/50 transform -rotate-12"
                        >
                            🌍
                        </div>
                        <h3
                            class="text-3xl font-bold mb-8 text-transparent bg-gradient-to-r from-green-300 to-teal-300 bg-clip-text"
                        >
                            {{ t('Pointing at litter, plastic pollution, nature, and biodiversity') }}
                        </h3>
                        <ul class="space-y-6">
                            <li class="flex items-start group/item">
                                <span
                                    class="text-green-400 mr-4 text-2xl group-hover/item:scale-125 transition-transform"
                                    >✓</span
                                >
                                <span class="text-green-100/90 text-lg">{{
                                    t(
                                        'Safer, more ethical, and more productive than doomscrolling and comparing yourself to others.'
                                    )
                                }}</span>
                            </li>
                            <li class="flex items-start group/item">
                                <span
                                    class="text-green-400 mr-4 text-2xl group-hover/item:scale-125 transition-transform"
                                    >✓</span
                                >
                                <span class="text-green-100/90 text-lg">{{
                                    t(
                                        'Engage with in-world data collection to overcome passive electronic information consumption.'
                                    )
                                }}</span>
                            </li>
                            <li class="flex items-start group/item">
                                <span
                                    class="text-green-400 mr-4 text-2xl group-hover/item:scale-125 transition-transform"
                                    >✓</span
                                >
                                <span class="text-green-100/90 text-lg">{{
                                    t('Measurable environmental impact.')
                                }}</span>
                            </li>
                            <li class="flex items-start group/item">
                                <span
                                    class="text-green-400 mr-4 text-2xl group-hover/item:scale-125 transition-transform"
                                    >✓</span
                                >
                                <span class="text-green-100/90 text-lg">{{
                                    t(
                                        'Collecting data on litter and plastic pollution is fun, easy and a more purposeful use of your time.'
                                    )
                                }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Floating tech particles -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="tech-particle tech-particle-1"></div>
            <div class="tech-particle tech-particle-2"></div>
            <div class="tech-particle tech-particle-3"></div>
            <div class="tech-particle tech-particle-4"></div>
        </div>
    </section>
</template>

<style scoped>
/* Tech particle animation */
.tech-particle {
    position: absolute;
    width: 4px;
    height: 4px;
    background: linear-gradient(45deg, rgba(167, 139, 250, 0.8), rgba(217, 70, 239, 0.8));
    border-radius: 50%;
    box-shadow: 0 0 10px rgba(167, 139, 250, 0.5);
    animation: float-tech 30s infinite ease-in-out;
}

.tech-particle::before {
    content: '';
    position: absolute;
    inset: -8px;
    background: radial-gradient(circle, rgba(167, 139, 250, 0.3) 0%, transparent 70%);
    border-radius: 50%;
}

.tech-particle-1 {
    left: 10%;
    animation-delay: 0s;
    animation-duration: 25s;
}

.tech-particle-2 {
    left: 30%;
    animation-delay: 7s;
    animation-duration: 30s;
}

.tech-particle-3 {
    left: 60%;
    animation-delay: 14s;
    animation-duration: 28s;
}

.tech-particle-4 {
    left: 85%;
    animation-delay: 21s;
    animation-duration: 33s;
}

@keyframes float-tech {
    0% {
        transform: translateY(110vh) translateX(0) scale(0);
        opacity: 0;
    }
    10% {
        opacity: 1;
        transform: translateY(90vh) translateX(20px) scale(1);
    }
    90% {
        opacity: 1;
        transform: translateY(10vh) translateX(-20px) scale(1);
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
        transform: scale(1);
    }
    50% {
        opacity: 0.8;
        transform: scale(0.98);
    }
}

.animate-pulse {
    animation: pulse 3s ease-in-out infinite;
}

/* Hover effects */
.group:hover .group-hover\:scale-125 {
    transform: scale(1.25);
}

/* Respect reduced motion */
@media (prefers-reduced-motion: reduce) {
    .tech-particle {
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
