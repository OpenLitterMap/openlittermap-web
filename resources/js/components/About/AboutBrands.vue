<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet.glify';
import { useGlobalMapStore } from '../../stores/maps/global/index.js';

const { t } = useI18n();
const globalMapStore = useGlobalMapStore();

// Map refs and state
const mapContainer = ref(null);
let map = null;
let pointLayer = null;

// Set the specific location you requested
const defaultLocation = {
    center: [52.14299569196593, 4.41278747870765], // Specific coordinates
    zoom: 15, // Zoom level 15 as requested
};

// Initialize map
async function initMap() {
    if (!mapContainer.value || map) return;

    map = L.map(mapContainer.value, {
        center: defaultLocation.center,
        zoom: defaultLocation.zoom,
        scrollWheelZoom: false,
    });

    // OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    // Load points
    map.on('moveend zoomend', loadPoints);
    loadPoints();
}

// Load points with brand data
async function loadPoints() {
    if (!map) return;

    const bounds = map.getBounds();
    const bbox = {
        left: bounds.getWest(),
        bottom: bounds.getSouth(),
        right: bounds.getEast(),
        top: bounds.getNorth(),
    };

    try {
        // For demonstrating brand filtering at the specific location
        // You can uncomment this to filter by specific brands
        // globalMapStore.setFilters({ brands: ['coca-cola', 'pepsi', 'marlboro'] });

        await globalMapStore.GET_POINTS({
            zoom: Math.round(map.getZoom()),
            bbox,
        });

        if (globalMapStore.pointsGeojson?.features?.length > 0) {
            if (pointLayer) {
                pointLayer.remove();
                pointLayer = null;
            }

            // Extract coordinates and properties for enhanced visualization
            const data = globalMapStore.pointsGeojson.features.map((feature) => {
                return {
                    coords: [feature.geometry.coordinates[0], feature.geometry.coordinates[1]],
                    properties: feature.properties,
                };
            });

            // Create colored points - you could vary color based on properties
            pointLayer = L.glify.points({
                map: map,
                data: data.map((d) => d.coords),
                size: function (zoom) {
                    // Dynamic sizing based on zoom
                    return zoom < 17 ? 8 : zoom < 19 ? 12 : 16;
                },
                color: function (index, point) {
                    // You could vary color based on properties
                    // For now, keep the brand-themed amber color
                    return { r: 0.9, g: 0.2, b: 0.4, a: 1 };
                },
                click: (e, point, xy) => {
                    // Enhanced click handling
                    const index = data.findIndex((d) => d.coords[0] === point[0] && d.coords[1] === point[1]);

                    if (index !== -1) {
                        const feature = globalMapStore.pointsGeojson.features[index];
                        console.log('Clicked photo:', feature.properties);

                        // You could show a popup with photo details
                        // or emit an event to show photo details
                        map.flyTo([point[1], point[0]], Math.min(map.getZoom() + 1, 20));
                    }
                },
            });
        }
    } catch (error) {
        console.error('Failed to load points:', error);
    }
}

onMounted(() => {
    initMap();
});

onBeforeUnmount(() => {
    if (pointLayer) pointLayer.remove();
    if (map) {
        map.off();
        map.remove();
    }
});
</script>

<template>
    <section
        class="py-16 sm:py-20 bg-gradient-to-br from-gray-950 via-amber-950 to-orange-950 relative overflow-hidden"
    >
        <!-- Corporate pattern background -->
        <div class="absolute inset-0 opacity-10">
            <svg class="absolute top-0 w-full h-full" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 800">
                <defs>
                    <pattern
                        id="hexagons"
                        width="50"
                        height="43.4"
                        patternUnits="userSpaceOnUse"
                        patternTransform="scale(2) rotate(0)"
                    >
                        <polygon
                            points="24.8,22 37.3,11 49.8,22 49.8,43.4 37.3,54.4 24.8,43.4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="0.5"
                            class="text-amber-500"
                        />
                        <polygon
                            points="0,22 12.5,11 25,22 25,43.4 12.5,54.4 0,43.4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="0.5"
                            class="text-amber-500"
                        />
                        <polygon
                            points="49.8,22 62.3,11 74.8,22 74.8,43.4 62.3,54.4 49.8,43.4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="0.5"
                            class="text-amber-500"
                        />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#hexagons)" />
            </svg>

            <!-- Additional gradient overlay for depth -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent"></div>
        </div>

        <div class="w-full mx-auto relative z-10 px-8 md:px-16 lg:px-20 xl:px-28">
            <div class="grid lg:grid-cols-2 items-center gap-8 md:gap-16 lg:gap-20 xl:gap-28">
                <!-- Left Column: Text -->
                <div class="text-white">
                    <!-- Decorative element -->
                    <div class="flex items-center mb-6">
                        <span
                            class="w-16 h-1.5 bg-gradient-to-r from-amber-500 via-yellow-500 to-orange-500 rounded-full shadow-lg shadow-amber-500/50"
                        ></span>
                        <span class="ml-4 text-amber-400 font-semibold tracking-wider uppercase text-sm">{{
                            t('Accountability')
                        }}</span>
                    </div>

                    <h2
                        class="text-4xl sm:text-5xl lg:text-6xl font-black mb-8 bg-gradient-to-r from-amber-200 via-yellow-200 to-orange-200 bg-clip-text text-transparent leading-tight"
                    >
                        {{ t('What Brands are Polluting Your Community?') }}
                    </h2>

                    <p class="text-xl sm:text-2xl text-amber-100/90 mb-8 leading-relaxed font-light">
                        {{
                            t(
                                'Taxpayers and volunteers have to work long hours to cleanup the waste produced by super-profitable ocean-polluting megacorporations.'
                            )
                        }}
                    </p>

                    <p class="text-lg sm:text-xl text-amber-100/80 mb-10 leading-relaxed">
                        {{
                            t(
                                'They privatize all the gains and socialize all the losses, leaving communities to deal with the mess they benefit from.'
                            )
                        }}
                    </p>

                    <!-- Brand stats preview with enhanced styling -->
                    <div class="grid grid-cols-3 gap-6">
                        <div class="relative group">
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-amber-600 to-orange-600 rounded-2xl blur-xl opacity-50 group-hover:opacity-75 transition duration-500"
                            ></div>
                            <div
                                class="relative bg-gradient-to-br from-amber-900/40 to-orange-900/40 backdrop-blur-md border border-amber-600/30 rounded-2xl p-6 transform hover:scale-105 transition duration-300"
                            >
                                <div
                                    class="text-3xl font-black text-transparent bg-gradient-to-r from-yellow-400 to-amber-400 bg-clip-text"
                                >
                                    1500+
                                </div>
                                <div class="text-sm text-amber-200/90 mt-1">{{ t('Brands identified') }}</div>
                            </div>
                        </div>
                        <div class="relative group">
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-amber-600 to-orange-600 rounded-2xl blur-xl opacity-50 group-hover:opacity-75 transition duration-500"
                            ></div>
                            <div
                                class="relative bg-gradient-to-br from-amber-900/40 to-orange-900/40 backdrop-blur-md border border-amber-600/30 rounded-2xl p-6 transform hover:scale-105 transition duration-300"
                            >
                                <div
                                    class="text-3xl font-black text-transparent bg-gradient-to-r from-yellow-400 to-amber-400 bg-clip-text"
                                >
                                    100+
                                </div>
                                <div class="text-sm text-amber-200/90 mt-1">{{ t('Countries') }}</div>
                            </div>
                        </div>
                        <div class="relative group">
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-amber-600 to-orange-600 rounded-2xl blur-xl opacity-50 group-hover:opacity-75 transition duration-500"
                            ></div>
                            <div
                                class="relative bg-gradient-to-br from-amber-900/40 to-orange-900/40 backdrop-blur-md border border-amber-600/30 rounded-2xl p-6 transform hover:scale-105 transition duration-300"
                            >
                                <div
                                    class="text-3xl font-black text-transparent bg-gradient-to-r from-yellow-400 to-amber-400 bg-clip-text"
                                >
                                    24/7
                                </div>
                                <div class="text-sm text-amber-200/90 mt-1">{{ t('Live tracking') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Map -->
                <div class="relative group">
                    <!-- Enhanced glow effect with multiple layers -->
                    <div
                        class="absolute -inset-3 bg-gradient-to-r from-amber-600 via-yellow-500 to-orange-600 rounded-3xl blur-2xl opacity-60 group-hover:opacity-100 transition duration-700 animate-pulse"
                    ></div>
                    <div
                        class="absolute -inset-2 bg-gradient-to-r from-amber-500 to-orange-500 rounded-3xl blur-lg opacity-40 group-hover:opacity-70 transition duration-500"
                    ></div>

                    <div class="relative">
                        <div
                            ref="mapContainer"
                            class="rounded-3xl shadow-2xl w-full h-[500px] overflow-hidden transform group-hover:scale-[1.02] transition-all duration-500 ring-1 ring-white/10"
                        ></div>

                        <!-- Enhanced overlay gradients -->
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-amber-950/50 via-transparent to-transparent rounded-3xl pointer-events-none"
                        ></div>
                        <div
                            class="absolute inset-0 bg-gradient-to-br from-transparent via-transparent to-orange-900/30 rounded-3xl pointer-events-none"
                        ></div>

                        <!-- Additional corner accent -->
                        <div
                            class="absolute bottom-6 right-6 bg-gradient-to-r from-amber-500/20 to-orange-500/20 backdrop-blur-md rounded-xl px-4 py-2 border border-amber-500/20"
                        >
                            <span class="text-amber-300 text-xs font-medium">{{ t('Click points to zoom') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Floating brand logos animation -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="brand-particle brand-particle-1"></div>
            <div class="brand-particle brand-particle-2"></div>
            <div class="brand-particle brand-particle-3"></div>
        </div>
    </section>
</template>

<style scoped>
/* Map styling with enhanced visual effects */
:deep(.leaflet-container) {
    background: linear-gradient(135deg, #0a0a0a 0%, #1a0f00 100%);
    font-weight: 500;
}

:deep(.leaflet-control-attribution) {
    background-color: rgba(0, 0, 0, 0.9);
    color: #fbbf24;
    backdrop-filter: blur(10px);
    border-radius: 8px;
    padding: 4px 8px;
    border: 1px solid rgba(251, 191, 36, 0.2);
}

/* Enhanced canvas glow for brand points */
:deep(canvas) {
    mix-blend-mode: screen;
    filter: drop-shadow(0 0 20px rgba(251, 191, 36, 0.8)) drop-shadow(0 0 40px rgba(251, 191, 36, 0.4))
        drop-shadow(0 0 60px rgba(251, 191, 36, 0.2));
}

/* Enhanced brand particle animation */
.brand-particle {
    position: absolute;
    width: 60px;
    height: 60px;
    background: radial-gradient(
        circle,
        rgba(251, 191, 36, 0.4) 0%,
        rgba(251, 191, 36, 0.1) 40%,
        rgba(251, 191, 36, 0) 70%
    );
    border-radius: 50%;
    opacity: 0;
    animation: drift 30s infinite ease-in-out;
    filter: blur(1px);
}

.brand-particle::before {
    content: '';
    position: absolute;
    inset: 25%;
    background: radial-gradient(circle, rgba(251, 191, 36, 0.8) 0%, rgba(251, 191, 36, 0.4) 50%, transparent 70%);
    border-radius: 50%;
    box-shadow: 0 0 20px rgba(251, 191, 36, 0.6);
}

.brand-particle::after {
    content: '';
    position: absolute;
    inset: 40%;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 50%;
    animation: sparkle 2s infinite ease-in-out;
}

.brand-particle-1 {
    left: 5%;
    animation-delay: 0s;
    animation-duration: 28s;
}

.brand-particle-2 {
    left: 45%;
    animation-delay: 10s;
    animation-duration: 32s;
}

.brand-particle-3 {
    left: 85%;
    animation-delay: 20s;
    animation-duration: 30s;
}

@keyframes drift {
    0% {
        transform: translateY(110vh) translateX(0) rotate(0deg) scale(0.8);
        opacity: 0;
    }
    5% {
        opacity: 0.7;
    }
    50% {
        transform: translateY(50vh) translateX(80px) rotate(180deg) scale(1.2);
        opacity: 0.9;
    }
    95% {
        opacity: 0.7;
    }
    100% {
        transform: translateY(-10vh) translateX(-30px) rotate(360deg) scale(0.8);
        opacity: 0;
    }
}

@keyframes sparkle {
    0%,
    100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(0.6);
        opacity: 0.6;
    }
}

/* Respect reduced motion */
@media (prefers-reduced-motion: reduce) {
    .brand-particle {
        animation: none;
    }

    .transform {
        transform: none !important;
    }
}
</style>
