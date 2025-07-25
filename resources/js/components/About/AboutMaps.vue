<script setup>
import { ref, onMounted, onBeforeUnmount, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet.glify';
import { useGlobalMapStore } from '../../stores/maps/global/index.js';

const { t } = useI18n();
const globalMapStore = useGlobalMapStore();

// Place definitions
const places = [
    {
        id: 'eu',
        center: [50.83957919204694, 4.3740783135703065],
        zoom: 16,
        titleKey: 'EU Parliament, Brussels',
        copyKey:
            "Check out this map of litter outside the EU Parliament - where they debate how to spend billions in 'green' budgets on public health, education, and the environment, but they can't even see what's on their doorstep.",
    },
    {
        id: 'de',
        center: [52.50894381678627, 13.38110858789401],
        zoom: 16,
        titleKey: 'Bundesrat, Berlin',
        copyKey:
            'Check out this map of cigarette litter around the Bundesrat (the Federal government buildings of Germany), where billions of euro of public money is spent on public health, education and the environment.',
    },
];

// Map refs and state
const mapRefs = reactive({});
const maps = reactive({});
const pointLayers = reactive({});
const visibleMaps = reactive({});

// Build OpenLitterMap URL
function buildOsmUrl(place) {
    return `https://openlittermap.com/global?lat=${place.center[0]}&lon=${place.center[1]}&zoom=${place.zoom}`;
}

// Initialize map with dark tiles
async function initMap(element, place) {
    if (!element || maps[place.id]) return;

    // Ensure element has dimensions using requestAnimationFrame
    await new Promise((r) => requestAnimationFrame(r));

    const map = L.map(element, {
        center: place.center,
        zoom: place.zoom,
        scrollWheelZoom: false,
    });

    // OpenStreetMap tiles
    const tileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    // Remove opacity on first tile load
    tileLayer.once('tileload', () => {
        element.classList.remove('opacity-0');
    });

    // Force size recalculation
    map.whenReady(() => map.invalidateSize());

    maps[place.id] = map;

    // Load points initially and on map movement
    map.on('moveend zoomend', () => loadPoints(place.id));

    // Initial load after tiles are ready
    tileLayer.once('tileload', () => {
        loadPoints(place.id);
    });
}

// Color function for points
function getPointColor() {
    // Bright red for all points
    return { r: 1, g: 0, b: 0.2, a: 1 };
}

// Load points for a map
async function loadPoints(placeId) {
    const map = maps[placeId];
    if (!map || !map.getContainer()) return;

    const bounds = map.getBounds();
    const bbox = {
        left: bounds.getWest(),
        bottom: bounds.getSouth(),
        right: bounds.getEast(),
        top: bounds.getNorth(),
    };

    try {
        // Note: The API doesn't use layers parameter, just bbox and zoom
        await globalMapStore.GET_POINTS({
            zoom: Math.round(map.getZoom()),
            bbox,
        });

        if (globalMapStore.pointsGeojson?.features?.length > 0) {
            // Remove existing points if any
            if (pointLayers[placeId]) {
                pointLayers[placeId].remove();
                pointLayers[placeId] = null;
            }

            // Build array for glify
            const data = globalMapStore.pointsGeojson.features.map((feature) => {
                return [feature.geometry.coordinates[0], feature.geometry.coordinates[1]];
            });

            // Create red points
            pointLayers[placeId] = L.glify.points({
                map: map,
                data,
                size: 10,
                color: getPointColor(),
            });
        }
    } catch (error) {
        console.error('Failed to load points:', error);
    }
}

// Intersection Observer for lazy loading
function setupIntersectionObserver() {
    const options = {
        root: null,
        rootMargin: '100px',
        threshold: 0.1,
    };

    const observer = new IntersectionObserver(async (entries) => {
        for (const entry of entries) {
            const placeId = entry.target.dataset.placeId;
            if (entry.isIntersecting && !visibleMaps[placeId]) {
                visibleMaps[placeId] = true;
                const place = places.find((p) => p.id === placeId);
                if (place && mapRefs[placeId]) {
                    initMap(mapRefs[placeId], place);
                }
            }
        }
    }, options);

    // Observe all map containers
    places.forEach((place) => {
        const element = mapRefs[place.id];
        if (element) {
            element.dataset.placeId = place.id;
            observer.observe(element);
        }
    });

    return observer;
}

let observer = null;

onMounted(() => {
    // Small delay to ensure refs are populated
    setTimeout(() => {
        observer = setupIntersectionObserver();
    }, 100);
});

onBeforeUnmount(() => {
    observer?.disconnect();

    Object.values(pointLayers).forEach((layer) => layer?.remove());
    Object.values(maps).forEach((map) => {
        map.off();
        map.remove();
    });
});
</script>

<template>
    <section
        class="py-20 sm:py-32 bg-gradient-to-br from-slate-950 via-purple-950 to-indigo-950 relative overflow-hidden"
    >
        <!-- Fancy geometric background pattern -->
        <div class="absolute inset-0 opacity-10">
            <svg class="absolute top-0 w-full h-full" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 800">
                <defs>
                    <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                        <path
                            d="M 40 0 L 0 0 0 40"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1"
                            class="text-purple-500"
                        />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid)" />
            </svg>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <!-- Header -->
            <header class="text-center mb-16">
                <h2
                    class="text-3xl sm:text-4xl lg:text-5xl font-bold mb-6 bg-gradient-to-r from-purple-300 via-pink-300 to-indigo-300 bg-clip-text text-transparent"
                >
                    {{ t('Tell a story about the world.') }}
                </h2>
                <p class="text-xl text-gray-300 max-w-3xl mx-auto">
                    {{
                        t(
                            'Maps are powerful tools that helps us see and understand the world. ' +
                                'OpenLitterMap empowers you to use your device for its real-world data collection purpose and communicate your story about litter and plastic pollution with the world.'
                        )
                    }}
                </p>
            </header>

            <!-- Map sections -->
            <div v-for="(place, i) in places" :key="place.id" class="mb-20 last:mb-0">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <!-- Text column -->
                    <div :class="i % 2 === 0 ? 'order-2 lg:order-1' : 'lg:order-2'">
                        <h3 class="text-2xl font-semibold text-white mb-4 flex items-center">
                            <span
                                class="w-2 h-2 bg-gradient-to-r from-purple-400 to-indigo-400 rounded-full mr-3 animate-pulse"
                            ></span>
                            {{ t(place.titleKey) }}
                        </h3>
                        <p class="text-lg text-purple-100 mb-6">
                            {{ t(place.copyKey) }}
                        </p>

                        <!-- Fancy button -->
                        <a
                            :href="buildOsmUrl(place)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-medium rounded-xl hover:from-purple-700 hover:to-indigo-700 transform hover:scale-105 transition-all duration-300 shadow-lg hover:shadow-purple-500/25"
                        >
                            {{ t('View on OpenLitterMap') }}
                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                ></path>
                            </svg>
                        </a>
                    </div>

                    <!-- Map column -->
                    <div :class="i % 2 === 0 ? 'order-1 lg:order-2' : ''" class="relative group">
                        <!-- Fancy border glow effect -->
                        <div
                            class="absolute -inset-1 bg-gradient-to-r from-purple-500 to-indigo-500 rounded-2xl blur-md opacity-50 group-hover:opacity-75 transition duration-500"
                        ></div>

                        <div class="relative">
                            <div
                                :ref="(el) => (mapRefs[place.id] = el)"
                                class="map-container rounded-2xl shadow-2xl ring-2 ring-white/10 overflow-hidden opacity-0 transition-opacity duration-400 transform group-hover:scale-[1.01] transition-transform"
                                :aria-label="`Map of litter dots outside ${place.titleKey}`"
                            ></div>

                            <!-- Map overlay gradient -->
                            <div
                                class="absolute inset-0 bg-gradient-to-t from-purple-900/20 to-transparent rounded-2xl pointer-events-none"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Floating data particles animation -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="particle particle-1"></div>
            <div class="particle particle-2"></div>
            <div class="particle particle-3"></div>
            <div class="particle particle-4"></div>
        </div>
    </section>
</template>

<style scoped>
/* Animated gradient background */
@keyframes gradient {
    0%,
    100% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
}

.animate-gradient {
    background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
    background-size: 400% 400%;
    animation: gradient 15s ease infinite;
}

/* Map container with proper width and height */
.map-container {
    position: relative;
    width: 100%;
    height: 0;
    padding-bottom: 56.25%; /* 16:9 ratio */
    background: #111;
}

.map-container > :deep(.leaflet-container) {
    position: absolute;
    inset: 0;
}

/* Canvas glow and blend mode */
:deep(canvas) {
    mix-blend-mode: screen;
    filter: drop-shadow(0 0 8px rgba(239, 68, 68, 0.6));
}

/* Improve label contrast on dark maps */
:deep(.leaflet-container) {
    background-color: #111;
    font-weight: 500;
}

:deep(.leaflet-control-attribution) {
    background-color: rgba(0, 0, 0, 0.7);
    color: #ccc;
}

/* Fade in animation */
.transition-opacity {
    transition: opacity 0.4s ease-out;
}

/* Floating data particles */
.particle {
    position: absolute;
    width: 4px;
    height: 4px;
    background: linear-gradient(45deg, rgba(168, 85, 247, 0.8), rgba(99, 102, 241, 0.8));
    border-radius: 50%;
    box-shadow: 0 0 10px rgba(168, 85, 247, 0.5);
    animation: float-data 20s infinite ease-in-out;
}

.particle-1 {
    left: 10%;
    animation-delay: 0s;
    animation-duration: 18s;
}

.particle-2 {
    left: 30%;
    animation-delay: 5s;
    animation-duration: 22s;
}

.particle-3 {
    left: 60%;
    animation-delay: 10s;
    animation-duration: 20s;
}

.particle-4 {
    left: 85%;
    animation-delay: 15s;
    animation-duration: 24s;
}

@keyframes float-data {
    0% {
        transform: translateY(100vh) translateX(0) scale(0);
        opacity: 0;
    }
    10% {
        opacity: 1;
        transform: translateY(90vh) translateX(10px) scale(1);
    }
    90% {
        opacity: 1;
        transform: translateY(10vh) translateX(-10px) scale(1);
    }
    100% {
        transform: translateY(0) translateX(0) scale(0);
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
    .transition-opacity {
        transition: none;
    }

    .opacity-0 {
        opacity: 1;
    }

    .particle {
        animation: none;
    }

    .animate-pulse {
        animation: none;
    }
}

:deep(.leaflet-container) {
    background-color: #111;
}
</style>
