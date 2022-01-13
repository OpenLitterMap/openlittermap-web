<template>
    <div>
        <p class="mb-05">{{ $t('tags.recently-tags') }}</p>

        <div v-for="category in Object.keys(recentTags)">
            <p>{{ getCategoryName(category) }}</p>

            <transition-group name="list" class="recent-tags" tag="div" :key="category">
                <div
                    v-for="tag in Object.keys(recentTags[category])"
                    class="litter-tag"
                    :key="tag"
                    @click="addRecentTag(category, tag)"
                ><p>{{ getTagName(category, tag) }}</p></div>
            </transition-group>
        </div>
    </div>
</template>

<script>
export default {
    name: 'RecentTags',
    props: ['recentTags'],
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
         * Simply emits the event up
         *
         * @param category
         * @param tag
         */
        addRecentTag (category, tag)
        {
            this.$emit('add-recent-tag', category, tag);
        }
    }
};
</script>
<style lang="scss" scoped>

@import "../../styles/variables.scss";

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
