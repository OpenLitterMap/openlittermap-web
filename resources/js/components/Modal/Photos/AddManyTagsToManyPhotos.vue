<template>
    <div @keyup.ctrl.enter="submit">
        <add-tags :id="0"/>

        <div class="bulk-tag-picked-up">
            <presence/>
        </div>

        <!-- These are the tags the user has added -->
        <Tags class="mb1" :photo-id="0" />

        <button class="button is-medium" @click="back">Back</button>

        <button
            class="button is-medium is-primary"
            @click="store"
            :disabled="!hasAddedTags"
        >{{ $t('common.add-tags') }}</button>
    </div>
</template>

<script>
import AddTags from '../../Litter/AddTags';
import Tags from '../../Litter/Tags';
import Presence from '../../Litter/Presence';

export default {
    name: 'AddManyTagsToManyPhotos',
    components: {
        AddTags,
        Tags,
        Presence
    },
    computed: {
        /**
         * Disable button if false
         */
        hasAddedTags ()
        {
            let tags = this.$store.state.litter.tags;
            let customTags = this.$store.state.litter.customTags;
            let hasTags = tags && tags[0] && Object.keys(tags[0]).length;
            let hasCustomTags = customTags && customTags[0] && customTags[0].length;

            return hasTags || hasCustomTags;
        },

        /**
         * Returns the ids of the selected photos
         */
        selectedPhotos ()
        {
            return this.$store.state.photos.bulkPaginate.data
                .filter(photo => photo.selected)
                .map(photo => photo.id);
        }
    },
    async mounted ()
    {
        await this.$store.dispatch('LOAD_PREVIOUS_CUSTOM_TAGS');
    },
    methods: {
        /**
         * Hides the current modal
         */
        back ()
        {
            this.$store.commit('hideModal');
        },

        /**
         * Stores the tags in memory for each photo
         * without submitting them
         */
        async store ()
        {
            if (! this.hasAddedTags) return;

            for (let index in this.selectedPhotos) {
                // Set picked_up value for every photo
                this.$store.commit('setPhotoPickedUp', {
                    photoId: this.selectedPhotos[index],
                    picked_up: this.$store.state.litter.pickedUp
                });

                // Add tags
                Object.entries(this.$store.state.litter.tags[0] ?? {}).forEach(([category, tags]) => {
                    Object.entries(tags).forEach(([tag, quantity]) => {
                        this.$store.commit('addTagToPhoto', {
                            photoId: this.selectedPhotos[index],
                            category: category,
                            tag: tag,
                            quantity: quantity
                        });

                        this.$store.commit('addRecentTag', {category, tag});
                    });
                });

                // Add custom tags
                const customTags = this.$store.state.litter.customTags[0] ?? [];
                for (const customTag in customTags) {
                    this.$store.commit('addCustomTagToPhoto', {
                        photoId: this.selectedPhotos[index],
                        customTag: customTags[customTag]
                    });
                }
            }

            this.$localStorage.set('recentTags', JSON.stringify(this.$store.state.litter.recentTags));
            this.$localStorage.set('recentCustomTags', JSON.stringify(this.$store.state.litter.recentCustomTags));

            this.back();
        }
    },
};
</script>

<style scoped>
    .bulk-tag-picked-up {
        display: flex;
        justify-content: center;
        margin-bottom: 16px;
    }
</style>
