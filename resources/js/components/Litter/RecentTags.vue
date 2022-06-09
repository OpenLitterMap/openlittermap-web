<template>
    <div class="tags-container" v-if="Object.keys(recentTags).length > 0 || recentCustomTags.length">
        <p class="recent-tags-title mb-5 has-text-weight-bold">{{ $t('tags.recently-tags') }}</p>

        <div v-if="recentCustomTags.length">
            <p>{{ $t('tags.custom-tags') }}</p>

            <transition-group name="list" class="recent-tags" tag="div">
                <div
                    v-for="tag in recentCustomTags"
                    class="litter-tag"
                    :key="tag"
                    @click="addRecentCustomTag(tag)"
                >
                    <span class="close" @click.prevent.stop="clearRecentCustomTag(tag)">
                        <i class="fa fa-times"></i>
                    </span>
                    <p class="has-text-white">{{ tag }}</p>
                </div>
            </transition-group>
        </div>

        <transition-group name="categories" tag="div">
            <div v-for="category in Object.keys(recentTags)" :key="category">
                <p>{{ getCategoryName(category) }}</p>

                <transition-group name="list" class="recent-tags" tag="div">
                    <div
                        v-for="tag in Object.keys(recentTags[category])"
                        class="litter-tag"
                        :key="tag"
                        @click="addRecentTag(category, tag)"
                    >
                        <span class="close" @click.prevent.stop="clearRecentTag(category, tag)">
                            <i class="fa fa-times"></i>
                        </span>
                        <p class="has-text-white">{{ getTagName(category, tag) }}</p>
                    </div>
                </transition-group>
            </div>
        </transition-group>

        <div class="clear-tags-button">
            <button class="button is-danger is-small tooltip" @click="clearRecentTags">
                <span class="tooltip-text">{{ $t('tags.clear-tags-btn') }}</span>
                <i class="fa fa-trash"></i>
            </button>
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

        /**
         * The most recent custom tags the user has applied
         */
        recentCustomTags ()
        {
            return this.$store.state.litter.recentCustomTags;
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

            if (
                this.$store.state.litter.tags.hasOwnProperty(this.photoId) &&
                this.$store.state.litter.tags[this.photoId].hasOwnProperty(category) &&
                this.$store.state.litter.tags[this.photoId][category].hasOwnProperty(tag)
            ) {
                quantity = parseInt(this.$store.state.litter.tags[this.photoId][category][tag]) + 1;
            }

            this.$store.commit('changeCategory', category);
            this.$store.commit('changeTag', tag);

            this.$store.commit('addTag', {
                photoId: this.photoId,
                category,
                tag,
                quantity
            });
        },

        /**
         * Add a recent custom tag to the existing tags
         */
        addRecentCustomTag (tag)
        {
            this.$store.commit('addCustomTag', {
                photoId: this.photoId,
                customTag: tag
            });
        },

        /**
         * Remove the users recent tags
         */
        clearRecentTags ()
        {
            this.$store.commit('initRecentTags', {});
            this.$store.commit('initRecentCustomTags', []);

            this.$localStorage.remove('recentTags');
            this.$localStorage.remove('recentCustomTags');
        },

        /**
         * Remove a single recent tag
         */
        clearRecentTag (category, tag)
        {
            this.$store.commit('removeRecentTag', {category, tag});
            this.$localStorage.set('recentTags', JSON.stringify(this.recentTags));
        },

        /**
         * Remove a single recent custom tag
         */
        clearRecentCustomTag (tag)
        {
            this.$store.commit('removeRecentCustomTag', tag);
            this.$localStorage.set('recentCustomTags', JSON.stringify(this.recentCustomTags));
        },
    }
};
</script>
<style lang="scss" scoped>

@import "../../styles/variables.scss";

.tags-container {
    max-height: 650px;
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

.recent-tags-title {
    max-width: 100px;
}

.clear-tags-button {
    position: absolute;
    top:20px;
    right: 20px;
}

.litter-tag {
    position: relative;
    cursor: pointer;
    padding: 5px;
    border-radius: 5px;
    background-color: $info;
    margin: 5px;

    .close {
        display: none;
        position: absolute;
        top: -5px;
        right: -5px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        color: white;
        font-size: 12px;
        background-color: rgba(0,0,0,.7);
        &:hover {
            background-color: black;
        }
    }

    &:hover .close {
        display: flex;
        justify-content: center;
        align-items: center;
    }
}

@media screen and (min-width: 1280px)
{
    .recent-tags-title {
        max-width: none;
    }
}

.list-enter-active, .list-leave-active,
.categories-enter-active, .categories-leave-active {
    transition: all 0.5s;
}

.list-enter, .list-leave-to {
    opacity: 0;
    transform: translateX(30px);
}

.categories-enter, .categories-leave-to {
    opacity: 0;
    transform: translateY(50px);
}
.categories-move {
    transition: transform 0.5s;
}
</style>
