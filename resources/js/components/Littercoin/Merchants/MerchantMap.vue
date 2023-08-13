<template>
    <div class="h100">
        <div v-if="loading" />

        <Supercluster
            v-else
            activeLayer="merchants"
        />
    </div>
</template>

<script>
import Supercluster from "../../../views/global/Supercluster";

export default {
    name: "MerchantMap",
    components: {
        Supercluster
    },
    data() {
        return {
            loading: false
        };
    },
    async created() {
        this.loading = true;

        await this.$store.dispatch('GET_MERCHANTS_GEOJSON');

        this.loading = false;
    }
}
</script>

<style scoped>

</style>
