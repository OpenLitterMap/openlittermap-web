<template>
    <section class="hero-section">
        <!-- Background pattern -->
        <div class="hero-bg-pattern"></div>
        <div class="hero-bg-glow"></div>

        <!-- Hero Copy -->
        <div class="hero-copy">
            <h1 class="hero-title">
                {{ $t('Every piece of litter tells a story.') }}
            </h1>
            <p class="hero-subtitle">
                {{
                    $t(
                        'Every photo captures valuable information about its location, time, brand, object, material, and effort.'
                    )
                }}
            </p>
        </div>

        <!-- Desktop burst visualization -->
        <div v-if="!isMobile" class="burst-container">
            <!-- Subtle pulse ring -->
            <div class="pulse-ring"></div>

            <!-- Central image -->
            <div class="central-image">
                <div class="image-glow"></div>
                <div class="image-wrapper">
                    <img
                        src="/resources/js/assets/images/can.png"
                        :alt="$t('Taking a photo of litter')"
                        class="litter-image"
                        loading="eager"
                    />
                </div>
            </div>

            <!-- Data cards with lines -->
            <template v-for="(field, index) in dataFields" :key="field.key">
                <!-- Burst line -->
                <div
                    class="burst-line"
                    :style="{
                        '--index': index,
                        '--total': dataFields.length,
                    }"
                >
                    <div class="line-inner">
                        <div class="line-flow"></div>
                    </div>
                    <div class="line-dot"></div>
                </div>

                <!-- Data card -->
                <div
                    class="burst-card data-card"
                    :style="{
                        '--index': index,
                        '--total': dataFields.length,
                        'transition-delay': `${index * 0.1}s`,
                        '--offset-x': field.offsetX || '0',
                        width: field.cardWidth || '16rem',
                    }"
                >
                    <div class="card-icon" :aria-label="field.label">
                        {{ field.emoji }}
                    </div>
                    <div class="card-text">
                        <h4 class="card-label">{{ field.label }}</h4>
                        <p class="card-value">{{ field.value }}</p>
                    </div>
                </div>
            </template>
        </div>

        <!-- Mobile list view -->
        <ul v-else class="mobile-list">
            <li v-for="field in dataFields" :key="field.key" class="data-row">
                <div class="row-icon" :aria-label="field.label">
                    {{ field.emoji }}
                </div>
                <div class="row-text">
                    <h4 class="row-label">{{ field.label }}</h4>
                    <p class="row-value">{{ field.value }}</p>
                </div>
            </li>
        </ul>
    </section>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

// Window size handling (SSR-safe)
const windowWidth = ref(768);
const isMobile = computed(() => windowWidth.value < 768);

const updateWindowWidth = () => {
    if (typeof window !== 'undefined') {
        windowWidth.value = window.innerWidth;
    }
};

onMounted(() => {
    updateWindowWidth();
    window.addEventListener('resize', updateWindowWidth);
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', updateWindowWidth);
    }
});

// Data fields
const dataFields = ref([
    {
        key: 'location',
        emoji: '📍',
        label: t('Location'),
        value: '51.8969° N, 8.4689° W',
        offsetX: '3em',
        cardWidth: '18rem',
    },
    {
        key: 'address',
        emoji: '🏛️',
        label: t('Address'),
        value: 'The Lough, Cork City, Ireland',
        cardWidth: '20rem',
    },
    {
        key: 'time',
        emoji: '🕐',
        label: t('Time'),
        value: 'Jan 15, 2025 14:32:18',
        cardWidth: '16rem',
    },
    {
        key: 'object',
        emoji: '🥫',
        label: t('Object'),
        value: t('Aluminum Can'),
    },
    {
        key: 'brand',
        emoji: '🏷️',
        label: t('Brand'),
        value: 'Coca-Cola',
        offsetX: '-2em',
        cardWidth: '15rem',
    },
    {
        key: 'material',
        emoji: '♻️',
        label: t('Material'),
        value: t('Aluminum, Plastic'),
        cardWidth: '18rem',
    },
    {
        key: 'user',
        emoji: '👤',
        label: t('Logged by'),
        value: 'User #42069',
        cardWidth: '12rem',
    },
    {
        key: 'condition',
        emoji: '📊',
        label: t('Condition'),
        value: t('Crushed, Weathered'),
        cardWidth: '15rem',
    },
]);
</script>

<style scoped>
/* Base styles - Enhanced but preserved */
.hero-section {
    @apply relative min-h-screen lg:min-h-[80vh];
    @apply bg-gradient-to-br from-slate-900 via-blue-900 to-blue-800;
    @apply flex flex-col items-center justify-center;
    @apply py-12 px-4 sm:px-6 lg:px-8;
    @apply overflow-hidden;
}

/* Enhanced background pattern */
.hero-bg-pattern {
    @apply absolute inset-0 opacity-20 pointer-events-none;
    background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpattern id='dots' x='0' y='0' width='40' height='40' patternUnits='userSpaceOnUse'%3E%3Ccircle cx='20' cy='20' r='1' fill='%2360A5FA'/%3E%3C/pattern%3E%3Crect width='100%25' height='100%25' fill='url(%23dots)'/%3E%3C/svg%3E");
}

/* Subtle background glow */
.hero-bg-glow {
    @apply absolute inset-0 pointer-events-none;
    background: radial-gradient(circle at 50% 50%, rgba(59, 130, 246, 0.1) 0%, transparent 60%);
}

/* Hero copy - Enhanced typography */
.hero-copy {
    @apply text-center relative z-10;
}

.hero-title {
    @apply text-4xl sm:text-5xl lg:text-6xl xl:text-7xl;
    @apply font-bold text-white mb-4 lg:mb-6;
    @apply leading-tight;
    text-shadow: 0 2px 20px rgba(59, 130, 246, 0.3);
}

.hero-subtitle {
    @apply text-lg sm:text-xl lg:text-2xl;
    @apply text-blue-100 max-w-4xl mx-auto;
    @apply leading-relaxed;
    font-size: 22px;
    opacity: 0.9;
}

/* Burst container - Preserved */
.burst-container {
    @apply relative mx-auto;
    width: min(700px, 90vw);
    height: min(700px, 90vw);
}

/* Subtle pulse ring */
.pulse-ring {
    @apply absolute inset-0;
    @apply border border-blue-500/10 rounded-full;
    animation: pulse-subtle 4s ease-out infinite;
}

@keyframes pulse-subtle {
    0% {
        transform: scale(0.8);
        opacity: 0;
    }
    50% {
        opacity: 0.2;
    }
    100% {
        transform: scale(1.2);
        opacity: 0;
    }
}

/* Pull burst upward on large screens */
@screen lg {
    .burst-container {
        margin-top: -1.5rem;
    }
}

@screen sm {
    .hero-subtitle {
        padding: 0;
    }
}

/* Central image - Enhanced glow */
.central-image {
    @apply absolute left-1/2 top-1/2;
    @apply transform -translate-x-1/2 -translate-y-1/2;
    @apply z-30;
}

.image-glow {
    @apply absolute inset-0;
    @apply bg-blue-500/30 blur-3xl scale-150;
    @apply pointer-events-none;
    animation: glow-subtle 3s ease-in-out infinite;
}

@keyframes glow-subtle {
    0%,
    100% {
        opacity: 0.3;
    }
    50% {
        opacity: 0.5;
    }
}

.image-wrapper {
    @apply relative;
    @apply bg-gray-900/80 backdrop-blur-md;
    @apply p-4 lg:p-6 rounded-2xl;
    @apply border border-gray-600/50;
    box-shadow:
        0 8px 32px rgba(0, 0, 0, 0.3),
        0 0 60px rgba(59, 130, 246, 0.2);
}

.litter-image {
    @apply w-32 h-32 lg:w-48 lg:h-48;
    @apply object-contain;
}

/* Burst lines - Enhanced but preserved structure */
.burst-line {
    --angle: calc((360deg / var(--total)) * var(--index));
    --line-length: clamp(8rem, 25vw, 12rem);

    position: absolute;
    inset: 50%;
    transform: translate(-50%, -50%) rotate(var(--angle));
    transform-origin: center;
    pointer-events: none;
    z-index: 10;
}

.line-inner {
    @apply relative;
    width: var(--line-length);
    height: 2px;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(96, 165, 250, 0.5) 30%,
        rgba(59, 130, 246, 0.3) 70%,
        rgba(96, 165, 250, 0.1) 100%
    );
}

.line-flow {
    @apply absolute inset-y-0 left-0;
    @apply w-12 h-full;
    background: linear-gradient(90deg, transparent 0%, rgba(147, 197, 253, 1) 50%, transparent 100%);
}

.line-dot {
    @apply absolute;
    @apply w-3 h-3 bg-gradient-to-r from-blue-400 to-blue-300 rounded-full;
    right: -6px;
    top: 50%;
    transform: translateY(-50%);
    box-shadow: 0 0 12px rgba(96, 165, 250, 0.6);
}

/* Data cards positioning - PRESERVED EXACTLY */
.burst-card {
    --radius: clamp(10rem, 30vw, 15rem);
    position: absolute;
    inset: 50%;
    transform: translate(-50%, -50%) rotate(calc((360deg / var(--total)) * var(--index))) translateX(var(--radius))
        rotate(calc(-1 * (360deg / var(--total)) * var(--index))) translateX(var(--offset-x));
}

/* Data cards - Enhanced styling */
.data-card {
    @apply flex items-center space-x-3 lg:space-x-4;
    @apply bg-gray-900/95 backdrop-blur-md;
    @apply rounded-xl px-4 py-4 lg:py-6;
    @apply border border-gray-600/50;
    @apply opacity-0 animate-fade-in;
    line-height: 1.6;
    z-index: 20;
    box-shadow:
        0 4px 20px rgba(0, 0, 0, 0.3),
        0 0 40px rgba(59, 130, 246, 0.1);
}

.card-icon,
.row-icon {
    @apply text-2xl lg:text-3xl;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
}

.card-text,
.row-text {
    @apply flex-1;
}

.card-label,
.row-label {
    @apply text-[10px] lg:text-xs;
    @apply text-blue-300 uppercase tracking-wider font-semibold;
    @apply mb-0.5;
}

.card-value,
.row-value {
    @apply text-sm lg:text-base;
    @apply text-white font-semibold;
    @apply truncate;
}

/* Mobile list - Enhanced */
.mobile-list {
    @apply space-y-4 max-w-sm mx-auto mt-12;
    @apply list-none;
}

.data-row {
    @apply flex items-center space-x-4;
    @apply bg-gray-900/95 backdrop-blur-md;
    @apply rounded-xl px-6 py-5;
    @apply border border-gray-600/50;
    line-height: 1.6;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

/* Animations - Preserved */
@keyframes fade-in {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.animate-fade-in {
    animation: fade-in 0.5s ease-out forwards;
}

/* Respect motion preferences */
@media (prefers-reduced-motion: no-preference) {
    .line-flow {
        animation: flow 3s linear infinite;
    }

    .line-dot {
        animation: pulse-dot 2s ease-in-out infinite;
    }
}

@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

@keyframes flow {
    from {
        transform: translateX(-100%);
    }
    to {
        transform: translateX(calc(var(--line-length) + 100%));
    }
}

@keyframes pulse-dot {
    0%,
    100% {
        opacity: 1;
        transform: translateY(-50%) scale(1);
    }
    50% {
        opacity: 0.7;
        transform: translateY(-50%) scale(0.8);
    }
}
</style>
