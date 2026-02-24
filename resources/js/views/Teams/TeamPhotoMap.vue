<template>
    <div>
        <div class="flex items-center justify-between mb-4">
            <p class="text-sm text-slate-600">
                <strong>{{ mapPoints.length }}</strong> locations collected by your team.
            </p>
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-1.5 text-sm text-slate-600">
                    <input v-model="showPrivate" type="checkbox" class="rounded" />
                    Show pending
                </label>
            </div>
        </div>

        <!-- Map container -->
        <div
            ref="mapContainer"
            class="w-full rounded-xl overflow-hidden shadow-sm"
            style="height: 500px;"
        />

        <!-- Legend -->
        <div class="flex gap-4 mt-3 text-xs text-slate-500">
            <span class="flex items-center gap-1">
                <span class="w-3 h-3 rounded-full bg-green-500 inline-block"></span>
                Published
            </span>
            <span class="flex items-center gap-1">
                <span class="w-3 h-3 rounded-full bg-amber-400 inline-block"></span>
                Pending approval
            </span>
        </div>
    </div>
</template>

<script>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useTeamPhotosStore } from '@/stores/teamPhotos';

export default {
    name: 'TeamPhotoMap',
    props: {
        teamId: { type: Number, required: true },
    },
    setup(props) {
        const store = useTeamPhotosStore();
        const mapContainer = ref(null);
        const showPrivate = ref(true);
        let map = null;
        let markerGroup = null;

        const mapPoints = computed(() => {
            if (showPrivate.value) return store.mapPoints;
            return store.mapPoints.filter((p) => p.is_public);
        });

        const initMap = () => {
            if (!mapContainer.value || !window.L) return;

            map = window.L.map(mapContainer.value).setView([53.35, -6.26], 7); // Cork/Dublin default

            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);

            markerGroup = window.L.layerGroup().addTo(map);

            renderMarkers();
        };

        const renderMarkers = () => {
            if (!markerGroup) return;
            markerGroup.clearLayers();

            const points = mapPoints.value;
            if (points.length === 0) return;

            points.forEach((point) => {
                const color = point.is_public ? '#22c55e' : '#f59e0b';

                const marker = window.L.circleMarker([point.lat, point.lng], {
                    radius: 6,
                    fillColor: color,
                    color: '#fff',
                    weight: 1,
                    opacity: 1,
                    fillOpacity: 0.8,
                });

                marker.bindPopup(`
                    <strong>${point.tags} items</strong><br/>
                    <span style="font-size:12px;color:#64748b">${point.date}</span><br/>
                    <span style="font-size:11px;color:${point.is_public ? '#22c55e' : '#f59e0b'}">
                        ${point.is_public ? 'Published' : 'Pending'}
                    </span>
                `);

                markerGroup.addLayer(marker);
            });

            // Fit bounds
            const bounds = points.map((p) => [p.lat, p.lng]);
            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [30, 30] });
            }
        };

        watch(mapPoints, renderMarkers);

        onMounted(async () => {
            await store.fetchMapPoints(props.teamId);
            await nextTick();
            initMap();
        });

        onUnmounted(() => {
            if (map) {
                map.remove();
                map = null;
            }
        });

        return { mapContainer, mapPoints, showPrivate };
    },
};
</script>
