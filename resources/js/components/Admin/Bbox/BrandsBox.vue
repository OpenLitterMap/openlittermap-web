<template>
    <div @click.stop class="fit-content">
        <p>Select a brand to add to a box</p>

        <p v-show="selectedBrandIndex !== null" class="mb1">When a box is selected, click a box to add the brand</p>

        <!-- Todo - make this draggable so we can drag + drop the brand into a box -->
<!--        <draggable v-model="brands">-->
            <div
                v-for="brand, index in brands"
                :key="brand + index"
                :class="brandClass(index)"
                @mousedown="select(index)"
            >{{ brand }} {{ isSelected(index) }}</div>
<!--        </draggable>-->
    </div>
</template>

<script>
// import draggable from 'vuedraggable'

export default {
    name: 'BrandsBox',
    // components: {
    //     draggable
    // },
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
        },

        /**
         * Shortcut
         */
        selectedBrandIndex ()
        {
            return this.$store.state.bbox.selectedBrandIndex;
        }
    },
    methods: {

        /**
         * Turn brand on if its selected
         */
        brandClass (index)
        {
            return this.selectedBrandIndex === index
                ? 'is-brand-card selected'
                : 'is-brand-card';
        },

        /**
         * Add "- selected" text if this brand is selected
         */
        isSelected (index)
        {
            return this.selectedBrandIndex === index
                ? ' - selected'
                : '';
        },

        /**
         * Select a brand
         */
        select (index)
        {
            this.$store.commit('selectBrandBoxIndex', index);
        }
    }
};
</script>

<style scoped>

    .fit-content {
        max-width: fit-content;
    }

    .is-brand-card {
        border: 1px solid #ccc;
        padding: 1em;
        border-radius: 6px;
        cursor: grab;
        width: fit-content;
        margin-bottom: 1em;
    }

    .is-brand-card.selected {
        border: 1px solid green;
    }

    .is-brand-card:active {
        cursor: grabbing;
    }
</style>
