<template>
	<div>
        <ul>
            <li v-for="category in categories" class='admin-item'>
                <span class="category">{{ getCategory(category.category) }}</span>
                <br>
                <span
                    v-for="tags in Object.entries(category.tags)"
                    v-html="getTags(tags, category.category)"
                    class="tag is-large is-info litter-tag"
                    @click="removeTag(category.category, tags[0])"
                />
            </li>
        </ul>
	</div>
</template>

<script>
export default {
    // AddedItems is quite similar to AdminItems except here we remove the tag, on AdminItems we reset the tag.
    name: 'ShowTags',
    computed: {

        /**
         * Categories from the tags object the user has created
         */
        categories ()
        {
            let categories = [];

            Object.entries(this.$store.state.litter.tags).map(entries => {
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
         *
         */
        category ()
        {
            return this.$store.state.litter.category; // was, .selectedCategory
        }
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
         * Return Translated item.key: Value from tags
         */
        getTags (tags, category)
        {
            return this.$i18n.t('litter.' + category + '.' + tags[1].key) + ': ' + tags[1].q + '<br>';
        },

        /**
         * Remove tag (string) at this category
         */
        removeTag (category, tag)
        {
            console.log('removeTag', category, tag);
            // this.$store.commit('resetTag', {
            //     category,
            //     tag
            // });
        },

        /**
         *
         */
        takeout (item)
        {
            this.$store.commit('removeItem', { item, category: this.category });
        },
    }
}
</script>

<style>

    .category {
        font-size: 1.25em;
        text-align: center;
    }

    .litter-tag {
        cursor: pointer;
        margin-bottom: 10px;
        width: 100%;
    }

</style>
