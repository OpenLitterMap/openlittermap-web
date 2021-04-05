<template>
    <div class="column is-one-third pl3 pt7">

        <BrandsBox />

        <!-- Hide inactive boxes -->
        <button
            class="button is-small is-primary mb1"
            @click.stop="hideInactive"
            v-show="manyBoxes"
        >Hide boxes</button>

        <!-- Show all boxes -->
        <button
            class="button is-small is-info mb1"
            @click="showAll"
            v-show="boxHidden"
        >Show boxes</button>

        <div
            v-for="(box, index) in boxes"
            :key="box.id"
            :class="boxClass(box.active)"
            @click.stop="activateAndCheckBox(box.id)"
        >
            <!-- Box.id, duplicate button -->
            <p class="ma">Box: <span class="is-bold">{{ box.id }}</span></p>

            <button class="button is-small duplicate-box" @click="duplicate(box.id)" disabled>Todo - Duplicate Box</button>
            <button class="button is-small toggle-box" @click="toggleLabel(box.id)">Toggle Label</button>
            <button class="button is-small is-dark rotate-box" @click="rotate(box.id)">Rotate</button>

            <!-- Box attributes -->
            <p>Left: {{ box.left }}</p>
            <p>Top: {{ box.top }}</p>
            <p>Width: {{ box.width }}</p>
            <p class="mb1">Height: {{ box.height }}</p>

            <!-- Tags -->
            <div class="container">
                <div class="box-categories">
                    <!-- Translated Category Title -->
                    <span class="box-category">{{ getCategory(box.category) }}</span>

                    <!-- Translated tag -->
                    <span
                        class="tag is-medium is-info box-label"
                        @click="removeTag(box.category, box.tag)"
                        v-html="getTags(box.category, box.tag)"
                    />

                    <div v-if="box.brand">
                        <!-- Translated Brand title -->
                        <p class="box-category">Brand</p>

                        <!-- Translated Brand tag -->
                        <span
                            class="tag is-medium is-info box-label w100"
                            @click="removeTag('brands', box.brand)"
                            v-html="getTags('brands', box.brand)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import BrandsBox from './Bbox/BrandsBox';

export default {
    name: 'Boxes',
    components: {
        BrandsBox
    },
    computed: {

        /**
         * Array of bounding boxes
         */
        boxes ()
        {
            return this.$store.state.bbox.boxes;
        },

        /**
         * One of the boxes is hidden
         */
        boxHidden ()
        {
            return this.$store.state.bbox.boxes.find(box => box.hidden);
        },

        /**
         * There are more than 1 boxes
         */
        manyBoxes ()
        {
            return this.$store.state.bbox.boxes.length > 1;
        }

    },
    methods: {

        /**
         * Activate a box
         *
         * Check if we need to add brand to this box
         */
        activateAndCheckBox (box_id)
        {
            this.$store.commit('activateBox', box_id);

            if (this.$store.state.bbox.selectedBrandIndex !== null)
            {
                this.$store.commit('addSelectedBrandToBox', box_id);
            }
        },

        /**
         * Normal or active class
         */
        boxClass (bool)
        {
            return bool ? 'is-box is-active' : 'is-box';
        },

        /**
         * Todo - Duplicate a box + tags
         *
         * Bug: position should be relative to the image container.
         * It is duplicating relative to previous box
         *
         * Position starts (0,0)
         */
        duplicate (id)
        {
            this.$store.commit('duplicateBox', id);
        },

        /**
         * Categories from the tags object the user has created
         */
        getCategories (keys)
        {
            let categories = [];

            Object.entries(keys).map(entries => {
                if (Object.keys(entries[1]).length > 0)
                {
                    categories.push({
                        category: entries[0],
                        tags: entries[1]
                    });
                }
            });

            return categories;
        },

        /**
         * Return translated value for category key
         */
        getCategory (category)
        {
            return this.$i18n.t('litter.categories.' + category);
        },

        /**
         * Return translated text for box.category, box.tag. Quantity => 1
         */
        getTags (category, tag)
        {
            return this.$i18n.t('litter.' + category + '.' + tag) + ': 1';
        },

        /**
         * Hide non-active boxes or show all
         */
        hideInactive ()
        {
            this.$store.commit('toggleHiddenBoxes');
        },

        /**
         * Remove tag from this category
         * If all tags have been removed, delete the category
         *
         * If Admin, we want to reset the tag.quantity to 0 instead of deleting it
         * This is used to pick up the change on the backend
         */
        removeTag (category, tag_key)
        {
            this.$store.commit('removeBboxTag', {
                category,
                tag_key
            });
        },

        /**
         * Temp - rotate the box
         */
        rotate (box_id)
        {
            this.$store.commit('rotateBox', box_id);
        },

        /**
         * Show all the boxes
         */
        showAll ()
        {
            this.$store.commit('showAllBoxes');
        },

        /**
         * Switch between box.id and box.category
         */
        toggleLabel (box_id)
        {
            this.$store.commit('toggleBoxLabel', box_id);
        },
    }
};
</script>

<style scoped>

    .is-box {
        border: 1px solid #ccc;
        padding: 1em;
        margin-bottom: 1em;
        max-width: 20em;
        position: relative;
    }

    .is-box.is-active {
        border: 1px solid green;
    }

    .box-label {
        margin-bottom: 0.25em;
    }

    .box-categories {
        display: grid;
    }

    .duplicate-box {
        position: absolute;
        right: 1em;
    }

    .toggle-box {
        position: absolute;
        top: 7em;
        right: 1em;
    }

    .rotate-box {
      position: absolute;
      top: 10em;
      right: 1em;
    }
</style>
