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
        center: [50.839771326202936, 4.373932462528583],
        zoom: 16,
        titleKey: 'EU Parliament, Brussels',
        copyKey:
            "Outside the EU Parliament—where billions in 'green' budgets are approved—volunteers logged litter the lawmakers step over every day. Click to explore the hypocrisy.",
    },
    {
        id: 'de',
        center: [52.50894381678627, 13.38110858789401],
        zoom: 16,
        titleKey: 'Bundesrat, Berlin',
        copyKey:
            "Around Germany's Bundesrat we mapped thousands of cigarette butts circling the seat of federal power. Zero fines issued, zero clean-ups scheduled.",
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
                click: (e, point) => {
                    map.flyTo([point[1], point[0]], map.getZoom() + 1);
                },
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
    <section class="py-20 sm:py-32 bg-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <header class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-white mb-6">
                    {{ t('Maps communicate what others want to hide.') }}
                </h2>
                <p class="text-xl text-gray-300 max-w-3xl mx-auto">
                    {{
                        t(
                            'Maps are simple yet incredibly powerful tools that communicate things about the world we cannot usually see.'
                        )
                    }}
                </p>
            </header>

            <!-- Map sections -->
            <div v-for="(place, i) in places" :key="place.id" class="mb-20 last:mb-0">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <!-- Text column -->
                    <div :class="i % 2 === 0 ? 'order-2 lg:order-1' : 'lg:order-2'">
                        <h3 class="text-2xl font-semibold text-gray-900 mb-4">
                            {{ t(place.titleKey) }}
                        </h3>
                        <p class="text-lg text-gray-600 mb-6">
                            {{ t(place.copyKey) }}
                        </p>

                        <blockquote class="italic text-red-600 mb-6">
                            {{
                                t(
                                    "How can you legislate climate policy when you can't spot the trash at your front door?"
                                )
                            }}
                        </blockquote>

                        <a
                            :href="buildOsmUrl(place)"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center text-red-600 hover:text-red-700 font-medium"
                        >
                            {{ t('Open this spot on OpenLitterMap') }}
                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002 2v-4M14 4h6m0 0v6m0-6L10 14"
                                />
                            </svg>
                        </a>

                        <span class="sr-only">{{ t('Open this spot on OpenLitterMap') }}</span>
                    </div>

                    <!-- Map column -->
                    <div :class="i % 2 === 0 ? 'order-1 lg:order-2' : ''">
                        <div
                            :ref="(el) => (mapRefs[place.id] = el)"
                            class="map-container rounded-2xl shadow-2xl ring-2 ring-black/20 overflow-hidden opacity-0 transition-opacity duration-400"
                            :aria-label="`Map of litter dots outside ${place.titleKey}`"
                        ></div>
                    </div>
                </div>
            </div>
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

/* Respect reduced motion preference */
@media (prefers-reduced-motion: reduce) {
    .transition-opacity {
        transition: none;
    }

    .opacity-0 {
        opacity: 1;
    }
}

:deep(.leaflet-container) {
    background-color: #111;
}
</style>
