<template>
    <div>
        <ul class="container">
            <transition-group name="categories" tag="div">
                <li v-if="customTags.length" class="admin-item" :key="'customTags'">
                    <span class="category">{{ $t('tags.custom-tags') }}</span>
                    <span v-for="tag in customTags"
                          class="tag is-medium has-background-link has-text-white litter-tag"
                          @click="removeCustomTag(tag)"
                          v-html="tag"
                    />
                </li>

                <li v-for="category in categories" class="admin-item" :key="category.category">
                    <!-- Translated Category Title -->
                    <span class="category">{{ getCategory(category.category) }}</span>

                    <!-- List of tags in each category -->
                    <span
                        v-for="tags in Object.entries(category.tags)"
                        class="tag is-medium is-info litter-tag"
                        @click="removeTag(category.category, tags[0])"
                        v-html="getTags(tags, category.category)"
                    />
                </li>
            </transition-group>
        </ul>
    </div>
</template>

<script>
/*** Tags (previously AddedItems) is quite similar to AdminItems except here we remove the tag, on AdminItems we reset the tag.*/
export default {
    name: 'Tags',
    props: [
        'photoId',
        'admin'
    ],
    computed: {
        /**
         * Categories from the tags object the user has created
         */
        categories ()
        {
            let categories = [];

            Object.entries(this.$store.state.litter.tags[this.photoId] || {}).map(entries => {
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
         * Custom tags that the user has selected
         */
        customTags ()
        {
            return this.$store.state.litter.customTags[this.photoId] || [];
        },
    },
    methods: {

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
            let commit = this.admin ? 'resetTag' : 'removeTag';

            this.$store.commit(commit, {
                photoId: this.photoId,
                category,
                tag_key
            });
        },

        /**
         * Remove the custom tag
         */
        removeCustomTag (tag)
        {
            this.$store.commit('removeCustomTag', {
                photoId: this.photoId,
                customTag: tag
            });
        }
    }
};
</script>

<style scoped>

    @media only screen and (max-width: 900px) {
        .container {
            display: flex;
            overflow-x: auto;
        }
        .admin-item {
            padding: 10px;
        }
    }
    .category {
        font-size: 1.25em;
        display: flex;
        justify-content: center;
        margin-bottom: 0.5em;
    }

    .litter-tag {
        cursor: pointer;
        margin-bottom: 10px;
        width: 100%;
    }

    .categories-enter-active, .categories-leave-active {
        transition: all 0.5s;
    }
    .categories-enter, .categories-leave-to {
        opacity: 0;
        transform: translateY(50px);
    }
    .categories-move {
        transition: transform 0.5s;
    }

</style>
