<template>
    <div>
        <p>Select a brand to add to a box</p>

        <!-- Todo - make this draggable -->
        <div
            v-for="brand, index in brands"
            :key="brand"
            :class="brandClass(index)"
            @mousedown="select(index)"
        >{{ brand }}</div>
    </div>
</template>

<script>
export default {
    name: 'BrandsBox',
    computed: {

        /**
         * Array of brand tags that were applied to the image
         */
        brands: {
            get () {
                return this.$store.state.bbox.brands;
            },
            set (v) {
                this.$store.commit('setBrandsBox', v);
            }
        }
    },
    methods: {

        /**
         * Turn brand on if its selected
         */
        brandClass (index)
        {
            return this.$store.state.bbox.selectedBrandIndex === index
                ? 'is-brand-card selected'
                : 'is-brand-card';
        },

        /**
         * Select a brand
         */
        select (index)
        {
            this.$store.commit('selectBrandBox', index);
        }
    }
};
</script>

<style scoped>

    .is-brand-card {
        border: 1px solid #ccc;
        padding: 1em;
        border-radius: 6px;
        cursor: grab;
        width: fit-content;
        margin-bottom: 1em;
    }

    .is-brand-card:active {
        cursor: grabbing;
    }
</style>
