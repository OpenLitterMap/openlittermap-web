<template>
    <section class="hero fullheight bulk-tag">
        <loading v-show="processing" v-model:active="processing" :is-full-page="true" />

        <FilterMyPhotos />

        <div class="my-photos-grid-container">

            <div
                v-for="photo in photos"
                class="my-grid-photo"
                :key="photo.id"
            >
                <img
                    class="litter"
                    @click="select(photo.id)"
                    v-img="{sourceButton: true, openOn: 'dblclick'}"
                    :src="photo.filename"
                />

                <div v-if="photo.selected" class="grid-checkmark">
                    <div class="tag-icon">
                        <i class="fa fa-check"></i>
                    </div>
                </div>

                <div
                    v-if="photoIsTagged(photo)"
                    class="grid-tagged tooltip"
                    @click.prevent.stop="togglePhotoDetailsPopup(photo)"
                >
                    <div class="tag-icon">
                        <span class="tooltip-text is-size-7">View tags</span>
                        <i class="fa fa-tags"></i>
                    </div>
                </div>

                <transition name="fade">
                    <div
                        v-if="showPhotoDetails(photo)"
                        class="photo-tags"
                    >
                        <PhotoDetailsPopup
                            @close="togglePhotoDetailsPopup(photo)"
                        />
                    </div>
                </transition>
            </div>

        </div>

        <div class="bottom-actions">
            <div class="bottom-navigation">
                <button
                    class="button is-medium mr1"
                    v-show="this.paginate.prev_page_url"
                    @click="previous"
                >{{$t('common.previous') }}</button>

                <button
                    class="button is-medium mr1"
                    v-show="this.paginate.next_page_url"
                    @click="next"
                >{{$t('common.next') }}</button>

                <div class="photos-info">
                    <div class="info-icon"><i class="fa fa-info"></i></div>
                    <div>{{ $t('profile.dashboard.bulk-tag-dblclick-info') }}</div>
                </div>
            </div>

            <div>
                <!-- Todo - test this on production -->
                <!--                <button-->
                <!--                    class="button is-medium is-danger mr1"-->
                <!--                    @click="deletePhotos"-->
                <!--                    :disabled="selectedCount === 0"-->
                <!--                >Delete</button>-->

                <button
                    class="button is-medium is-primary"
                    @click="addTags"
                    :disabled="selectedCount === 0"
                >{{$t('common.add-tags') }}</button>
            </div>

            <div class="bottom-right-actions">
                <button
                    class="button is-medium is-primary"
                    @click="applyTags"
                    :disabled="!hasAddedTags"
                >Add and tag one by one</button>

                <button
                    class="button is-medium is-primary"
                    @click="submit"
                    :disabled="!hasAddedTags"
                >{{ $t('common.submit') }}</button>
            </div>
        </div>
    </section>
</template>

<script>
import PhotoDetailsPopup from '../../components/Modal/Photos/PhotoDetailsPopup';
import FilterMyPhotos from '../../components/Profile/bottom/MyPhotos/FilterMyPhotos';
import Loading from 'vue-loading-overlay';
import 'vue-loading-overlay/dist/vue-loading.css';
import moment from 'moment';

export default {
    name: 'BulkTag',
    components: {
        Loading,
        PhotoDetailsPopup,
        FilterMyPhotos
    },
    data () {
        return {
            processing: false,
        };
    },
    async mounted ()
    {
        this.$store.commit('resetPhotoState');

        await this.$store.dispatch('LOAD_MY_PHOTOS');
    },
    computed: {

        /**
         * Class to show when calendar is open
         */
        calendar ()
        {
            return this.showCalendar
                ? 'dropdown is-active mr1'
                : 'dropdown mr1';
        },

        /**
         * Shortcut to pagination object
         */
        paginate ()
        {
            return this.$store.state.photos.bulkPaginate;
        },

        /**
         * Array of paginated photos
         */
        photos ()
        {
            return this.paginate.data;
        },

        /**
         * Number of photos that have been selected
         */
        selectedCount ()
        {
            return this.$store.state.photos.selectedCount;
        },

        /**
         * Disable button if false
         */
        hasAddedTags ()
        {
            if (this.processing) return false;

            return this.photos.filter(this.photoIsTagged).length;
        },
    },
    methods: {

        /**
         * Load a modal to add 1 or more tags to the selected photos
         */
        addTags ()
        {
            this.$store.commit('showModal', {
                type: 'AddManyTagsToManyPhotos',
                title: this.$t('common.add-many-tags') //'Add Many Tags'
            });
        },

        /**
         * Dispatch request
         */
        async submit ()
        {
            if (! this.hasAddedTags) return;

            this.processing = true;

            await this.$store.dispatch('BULK_TAG_PHOTOS');

            this.processing = false;

            setTimeout(() => {
                window.location.reload();
            }, 2000);
        },

        /**
         * Apply tags and submit individually
         */
        async applyTags ()
        {
            if (! this.hasAddedTags) return;

            this.processing = true;

            const taggedPhotos = this.photos.filter(this.photoIsTagged);

            for (let index in taggedPhotos) {
                // Add tags
                Object.entries(taggedPhotos[index].tags ?? {}).forEach(([category, tags]) => {
                    Object.entries(tags).forEach(([tag, quantity]) => {
                        this.$store.commit('addTag', {
                            photoId: taggedPhotos[index].id,
                            category: category,
                            tag: tag,
                            quantity: quantity
                        });
                    });
                });

                // Add custom tags
                const customTags = taggedPhotos[index].custom_tags ?? [];
                for (const customTag in customTags) {
                    this.$store.commit('addCustomTag', {
                        photoId: taggedPhotos[index].id,
                        customTag: customTags[customTag]
                    });
                }
            }

            setTimeout(() => {
                this.processing = false;
                this.$router.push('/tag');
            }, 300);
        },

        /**
         * Load a modal to confirm delete of the selected photos
         */
        deletePhotos ()
        {
            this.$store.commit('showModal', {
                type: 'ConfirmDeleteManyPhotos',
                title: this.$t('common.confirm-delete') //'Confirm Delete'
            });
        },

        /**
         * Shows/Hides the PhotoDetailsPopup
         */
        togglePhotoDetailsPopup (photo)
        {
            const photoId = this.showPhotoDetails(photo)
                ? null
                : photo.id;

            this.$store.commit('setPhotoToShowDetails', photoId);
        },

        /**
         * Return formatted date
         */
        getDate (date)
        {
            return moment(date).format('LL');
        },

        /**
         * Load the previous page of photos
         */
        previous ()
        {
            this.$store.dispatch('PREVIOUS_PHOTOS_PAGE');
        },

        /**
         * Load the next page of photos
         */
        next ()
        {
            this.$store.dispatch('NEXT_PHOTOS_PAGE');
        },

        /**
         * A photo was selected or de-selected
         */
        select (photoId)
        {
            this.$store.commit('togglePhotoSelected', photoId);
        },

        /**
         * Returns true if the photo has tags or custom tags
         */
        photoIsTagged (photo)
        {
            const hasTags = photo.tags && Object.keys(photo.tags).length;
            const hasCustomTags = photo.custom_tags?.length;
            return hasTags || hasCustomTags;
        },

        /**
         * Returns true if the photo has its details popup open
         */
        showPhotoDetails (photo)
        {
            return this.$store.state.photos.showDetailsPhotoId === photo.id;
        }
    }
};
</script>

<style lang="scss" scoped>
.bulk-tag {
    padding: 3rem;
}

.my-photos-grid-container {
    display: grid;
    grid-template-rows: repeat(5, 1fr);
    grid-template-columns: repeat(6, 1fr);
    grid-row-gap: 0.5em;
    grid-column-gap: 0.5em;
}

.my-grid-photo {
    max-height: 10em;
    max-width: 10em;
    position: relative;

    .litter {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 5px;
    }
}

.grid-checkmark {
    position: absolute;
    height: 32px;
    bottom: 8px;
    right: 0;
    color: #0ca3e0;
    font-size: 1rem;
    padding: 5px;

    .tag-icon {
        position: relative;
        height: 30px;
        width: 30px;
        border-radius: 50%;
        background-color: black;

        i {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
    }
}

.grid-tagged {
    position: absolute;
    height: 32px;
    top: 0;
    right: 0;
    color: #00d1b2;
    font-size: 1rem;
    padding: 5px;
    cursor: pointer;

    .tag-icon {
        position: relative;
        height: 30px;
        width: 30px;
        border-radius: 50%;
        background-color: black;

        i {
            position: absolute;
            top: 52%;
            left: 52%;
            transform: translate(-50%, -50%);
        }
    }

    .tooltip-text {
        min-width: max-content;
        transform: translate(-50%, -5px);
    }

    &:hover {
        transform: scale(1.05);
    }
}

.photo-tags {
    position: absolute;
    top: 105%;
    right: 50%;
    width: 250px;
    padding: 10px;
    background: ghostwhite;
    box-shadow: 2px 2px 2px 1px rgba(0, 0, 0, 0.2);
    border-radius: 5px;
    transform: translateX(50%);
    z-index: 10;
}

.photos-info {
    display: flex;
    gap: 8px;
    align-items: center;

    .info-icon {
        display: flex;
        justify-content: center;
        align-items: center;
        color: white;
        background-color: #00d1b2;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        i {
            margin-top: 2px;
        }
    }
}

.bottom-actions {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: center;
    margin-top: 16px;
    gap: 8px;

    .bottom-navigation {
        display: flex;
        flex-direction: row;
    }

    .bottom-right-actions {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }
}

/* Laptop and above */
@media (min-width: 1027px)
{
    .my-photos-grid-container {
        grid-template-rows: repeat(3, 1fr);
        grid-template-columns: repeat(10, 1fr);
        grid-row-gap: 1em;
        grid-column-gap: 1em;
    }

    .bottom-actions {
        flex-direction: row;
        gap: 0;

        .bottom-right-actions {
            flex-direction: row;
        }
    }
}

.fade-enter-active, .fade-leave-active {
    transition: opacity .3s;
}
.fade-enter, .fade-leave-to {
    opacity: 0;
}

</style>
