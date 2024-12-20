<template>
    <div v-if="photo">
        <div>
            <div class="top-row">
                <div class="switch-container">
                    <p class="mr-2"><strong>{{ $t('tags.picked-up-title') }}</strong></p>
                    <label class="switch">
                        <input
                            type="checkbox"
                            :checked="photo.picked_up"
                            @change="togglePickedUp"
                        >
                        <span class="slider round"></span>
                    </label>
                </div>
            </div>
            <div class="close-popup" @click="$emit('close')">
                <i class="fa fa-times"></i>
            </div>
        </div>
        <div class="photo-tags-container"
             v-if="photo.custom_tags && photo.custom_tags.length || Object.keys(photo.tags).length"
        >
            <div v-if="photo.custom_tags && photo.custom_tags.length">
                <p class="has-text-centered">{{ $t('tags.custom-tags') }}</p>

                <transition-group name="list" class="tags-list" tag="div">
                    <div
                        v-for="tag in photo.custom_tags"
                        class="litter-tag"
                        :key="tag"
                    >
                    <span class="close" @click.prevent.stop="clearCustomTag(tag)">
                        <i class="fa fa-times"></i>
                    </span>
                        <p class="has-text-white">{{ tag }}</p>
                    </div>
                </transition-group>
            </div>

            <transition-group name="categories" tag="div">
                <div v-for="category in Object.keys(photo.tags || {})" :key="category">
                    <p class="has-text-centered">{{ getCategoryName(category) }}</p>

                    <transition-group name="list" class="tags-list" tag="div">
                        <div
                            v-for="tag in Object.keys(photo.tags[category])"
                            class="litter-tag"
                            :key="tag"
                        >
                            <span class="close" @click.prevent.stop="removeTag(category, tag)">
                                <i class="fa fa-times"></i>
                            </span>
                            <p class="has-text-white">
                                {{ getTagName(category, tag) }}:
                                {{ photo.tags[category][tag] }}
                            </p>
                        </div>
                    </transition-group>
                </div>
            </transition-group>
        </div>
    </div>
</template>

<script>
export default {
    name: 'PhotoDetailsPopup',
    computed: {
        /**
         * The current photo to show details of
         */
        photo ()
        {
            const photoId = this.$store.state.photos.showDetailsPhotoId;
            return this.$store.state.photos.bulkPaginate.data.find(p => p.id === photoId);
        },
    },
    methods: {
        /**
         * Return translated category name
         */
        getCategoryName (category)
        {
            return this.$i18n.t(`litter.categories.${category}`);
        },

        /**
         * Return translated litter.key name for a tag
         */
        getTagName (category, tag)
        {
            return this.$i18n.t(`litter.${category}.${tag}`);
        },

        /**
         * Remove a single tag
         */
        removeTag (category, tag)
        {
            this.$store.commit('removeTagFromPhoto', {
                photoId: this.photo.id,
                category,
                tag
            });
        },

        /**
         * Remove a single custom tag
         */
        clearCustomTag (tag)
        {
            this.$store.commit('removeCustomTagFromPhoto', {
                photoId: this.photo.id,
                customTag: tag
            });
        },

        /**
         * Toggles the picked_up value for the current photo
         */
        togglePickedUp ()
        {
            this.$store.commit('setPhotoPickedUp', {
                photoId: this.photo.id,
                picked_up: !this.photo.picked_up
            });
        }
    },
};
</script>

<style lang="scss" scoped>

@import "../../../styles/variables.scss";

.top-row {
    display: flex;
    justify-content: center;
}

.close-popup {
    position: absolute;
    top: 5px;
    right: 5px;
    cursor: pointer;
    width: 20px;
    font-size: 16px;
    &:hover {
        transform: scale(1.05);
    }
}

.photo-tags-container {
    margin-top: 16px;
}

.tags-list {
    display: flex;
    max-width: 50em;
    margin: auto;
    flex-wrap: wrap;
    overflow: auto;
    justify-content: center;
}

.litter-tag {
    position: relative;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    background-color: $info;
    margin: 4px;

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
