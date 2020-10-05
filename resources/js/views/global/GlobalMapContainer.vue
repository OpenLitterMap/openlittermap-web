<template>
    <div class="global-map-container">
        <global-map />
    </div>
</template>

<script>
import GlobalMap from './GlobalMap'

export default {
    name: 'GlobalMapContainer',
    components: { GlobalMap }
}
</script>

<style scoped>
    @import '~leaflet/dist/leaflet.css';

    .global-map-container {
        height: calc(100% - 72px);
        margin: 0;
        position: relative;
        z-index: 1;
    }
</style>
