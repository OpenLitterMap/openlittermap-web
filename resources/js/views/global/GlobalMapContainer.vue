<template>
    <div class="global-map-container" @click="closeButtons">

    </div>
</template>

<script>
export default {
    name: 'GlobalMapContainer'
}
</script>

<style scoped>

    .global-map-container {
        height: calc(100% - 72px);
        margin: 0;
        position: relative;
    }

</style>
