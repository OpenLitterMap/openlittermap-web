<template>
    <div class="column is-one-third pl3 pt7">
        <p>Image info</p>
        <p>Height: </p>
        <p>Width: </p>

        <br>

        <div v-for="(box, index) in boxes" :key="box.id" class="is-box">
            <p>Box: <span class="is-bold">{{ index + 1 }}</span></p>
            <p>Height: {{ box.height }}</p>
            <p>Width: {{ box.width }}</p>
            <p>Top: {{ box.top }}</p>
            <p class="mb1">Left: {{ box.left }}</p>

            <ul v-if="box.tags" class="container">
                <li v-for="category in getCategories(box.tags)" class="box-categories">
                    <!-- Translated Category Title -->
                    <span class="box-category">{{ getCategory(category.category) }}</span>

                    <!-- List of tags in each category -->
                    <span
                        v-for="tags in Object.entries(category.tags)"
                        class="tag is-medium is-info box-tag"
                        @click="removeTag(category.category, tags[0])"
                        v-html="getTags(tags, category.category)"
                    />
                </li>
            </ul>
        </div>

        <button class="button is-medium is-primary" @click="addNewBox">
            Add Box
        </button>
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
        },

    },
    methods: {

        /**
         * Add a new bounding box
         */
        addNewBox ()
        {
            this.$store.commit('addNewBox');
        },

        /**
         * Categories from the tags object the user has created
         */
        getCategories (keys)
        {
            console.log({ keys });

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
         * Return Translated key: value from tags[0]: tags[1]
         */
        getTags (tags, category)
        {
            return this.$i18n.t('litter.' + category + '.' + tags[0]) + ': ' + tags[1] + '<br>';
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
            let commit = '';

            if (this.admin)  commit = 'resetTag';
            else commit = 'removeTag';

            this.$store.commit(commit, {
                category,
                tag_key
            });
        }
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

    .box-tag {
        margin-bottom: 0.25em;
    }

    .box-categories {
        display: grid;
    }
</style>
