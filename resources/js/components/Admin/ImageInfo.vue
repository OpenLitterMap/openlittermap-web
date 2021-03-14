<template>
    <div class="column is-one-third pl3 pt7">
        <p>Image info</p>
        <p>Height: </p>
        <p>Width: </p>

        <button class="button is-medium is-primary mt1 mb1" @click="addNewBox">
            Add Box
        </button>

        <div v-for="(box, index) in boxes" :key="box.id" :class="boxClass(box.active)" @click.stop="activate(box.id)">

            <!-- Box.id, duplicate button -->
            <div class="flex">
                <p class="flex-1 ma">Box: <span class="is-bold">{{ index + 1 }}</span></p>

                <button class="button is-small" @click="duplicate(box.id)" disabled>Todo - Duplicate Box</button>
            </div>

            <button class="button is-small" @click="toggleLabel(box.id)" disabled>Todo - Toggle Label</button>

            <!-- Box attributes -->
            <p>Height: {{ box.height }}</p>
            <p>Width: {{ box.width }}</p>
            <p>Top: {{ box.top }}</p>
            <p class="mb1">Left: {{ box.left }}</p>

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
                        <span class="box-category">{{ getTags('brands', box.brand) }}</span>

                        <!-- Translated Brand tag -->
                        <span
                            class="tag is-medium is-info box-label"
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

export default {
    name: 'ImageInfo',
    computed: {

        /**
         * Array of bounding boxes
         */
        boxes ()
        {
            return this.$store.state.bbox.boxes;
        }

    },
    methods: {

        /**
         * Activate a box
         */
        activate (id)
        {
            this.$store.commit('activateBox', id);
        },

        /**
         * Add a new bounding box
         */
        addNewBox ()
        {
            this.$store.commit('addNewBox');
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
         * Switch between box.id and box.category
         */
        toggleLabel (box_id)
        {
            console.log('todo - toggle label');
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
</style>
