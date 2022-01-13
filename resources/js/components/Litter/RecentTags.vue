<template>
    <div class="tags-container" v-if="Object.keys(recentTags).length > 0">
        <p class="mb-5 has-text-weight-bold">{{ $t('tags.recently-tags') }}</p>

        <div v-for="category in Object.keys(recentTags)">
            <p>{{ getCategoryName(category) }}</p>

            <transition-group name="list" class="recent-tags" tag="div" :key="category">
                <div
                    v-for="tag in Object.keys(recentTags[category])"
                    class="litter-tag"
                    :key="tag"
                    @click="addRecentTag(category, tag)"
                ><p class="has-text-white">{{ getTagName(category, tag) }}</p></div>
            </transition-group>
        </div>
    </div>
</template>

<script>
export default {
    name: 'RecentTags',
    props: ['photoId'],
    computed: {
        /**
         * The most recent tags the user has applied
         */
        recentTags ()
        {
            return this.$store.state.litter.recentTags;
        },
    },
    methods: {
        /**
         * Return translated category name for recent tags
         */
        getCategoryName (category)
        {
            return this.$i18n.t(`litter.categories.${category}`);
        },

        /**
         * Return translated litter.key name for recent tags
         */
        getTagName (category, tag)
        {
            return this.$i18n.t(`litter.${category}.${tag}`);
        },

        /**
         * When a recent tag was applied, we update the category + tag
         *
         * Todo - Persist this to local browser cache with this.$localStorage.set('recentTags', keys)
         * Todo - Click and hold recent tag to update this.category and this.tag
         * Todo - Allow the user to pick their top tags in Settings and load them on this page by default
         *        (New - PopularTags, bottom-left)
         */
        addRecentTag (category, tag)
        {
            let quantity = 1;

            if (this.$store.state.litter.tags.hasOwnProperty(category))
            {
                if (this.$store.state.litter.tags[category].hasOwnProperty(tag))
                {
                    quantity = (this.$store.state.litter.tags[category][tag] + 1);
                }
            }

            this.$store.commit('addTag', {
                photoId: this.photoId,
                category,
                tag,
                quantity
            });
        },
    }
};
</script>
<style lang="scss" scoped>

@import "../../styles/variables.scss";

.tags-container {
    max-height: 300px;
    overflow-y: auto;
}

.recent-tags {
    display: flex;
    max-width: 50em;
    margin: auto;
    flex-wrap: wrap;
    overflow: auto;
    justify-content: center;
}

.litter-tag {
    cursor: pointer;
    padding: 5px;
    border-radius: 5px;
    background-color: $info;
    margin: 5px
}

</style>
