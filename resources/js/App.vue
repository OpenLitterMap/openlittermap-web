<template>
    <div class="h-full">
        <Nav />

        <Modal />

        <router-view
            style="height: calc(100% - 90px);"
        />
    </div>
</template>
<script setup lang="ts">
</script>
