<template>
    <div>
        <p>Drag these brands into the correct box</p>

        <draggable v-model="brands" @start="drag=true" @end="drag=false">
            <div
                v-for="brand, index in brands"
                :key="brand + index"
                class="is-brand-card"
                @mousedown="select(brand, index)"
            >{{ brand }}</div>
        </draggable>
    </div>
</template>

<script>
import draggable from 'vuedraggable'

export default {
    name: 'BrandsBox',
    components: {
        draggable
    },
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
         * Select a brand
         */
        select (brand, index)
        {
            this.$store.commit('selectBrandBox', { brand, index });
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
