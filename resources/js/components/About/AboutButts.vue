<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <section class="py-16 sm:py-24 bg-gradient-to-br from-black via-red-950 to-orange-950 relative overflow-hidden">
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

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid lg:grid-cols-2 gap-8 items-center">
                <!-- Left Column: Text -->
                <div class="text-white">
                    <!-- Decorative element -->
                    <div class="flex items-center mb-4">
                        <span class="w-12 h-1 bg-gradient-to-r from-red-500 to-orange-500 rounded-full"></span>
                        <span class="ml-3 text-red-400 font-medium">{{ t('Environmental Impact') }}</span>
                    </div>

                    <h2
                        class="text-3xl sm:text-4xl lg:text-5xl font-extrabold mb-6 bg-gradient-to-r from-red-300 to-orange-300 bg-clip-text text-transparent"
                    >
                        {{ t('Trillions of cigarette butts leech toxic chemicals into the environment.') }}
                    </h2>

                    <p class="text-lg sm:text-xl text-red-100 mb-6">
                        {{
                            t(
                                'They poison water and release nicotine, arsenic, and microplastics into plants and animals.'
                            )
                        }}
                    </p>

                    <!-- Fancy stats boxes -->
                    <div class="grid grid-cols-2 gap-4 mt-8">
                        <div
                            class="bg-red-900/30 backdrop-blur-sm border border-red-700/50 rounded-xl p-4 transform hover:scale-105 transition duration-300"
                        >
                            <div class="text-3xl font-bold text-orange-400">4.5T</div>
                            <div class="text-sm text-red-200">{{ t('Butts per year') }}</div>
                        </div>
                        <div
                            class="bg-red-900/30 backdrop-blur-sm border border-red-700/50 rounded-xl p-4 transform hover:scale-105 transition duration-300"
                        >
                            <div class="text-3xl font-bold text-orange-400">700+</div>
                            <div class="text-sm text-red-200">{{ t('Toxic chemicals') }}</div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Image -->
                <div class="relative group">
                    <!-- Fancy border glow effect -->
                    <div
                        class="absolute -inset-1 bg-gradient-to-r from-red-500 to-orange-500 rounded-2xl blur-md opacity-75 group-hover:opacity-100 transition duration-500"
                    ></div>

                    <div class="relative">
                        <img
                            src="/resources/js/assets/images/butts.png"
                            alt="Cigarette butts"
                            class="rounded-2xl shadow-2xl w-full h-[400px] object-cover transform group-hover:scale-[1.02] transition duration-500"
                            loading="lazy"
                        />

                        <!-- Fancy overlay gradient -->
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-red-900/50 to-transparent rounded-2xl pointer-events-none"
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
</style>
