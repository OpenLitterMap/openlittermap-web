<template>
    <section class="hero fullheight is-primary is-bold tag-container">

        <loading v-if="loading" v-model:active="loading" :is-full-page="true" />

        <div v-else class="pt2">
            <!-- No Image available for tagging -->
            <div v-if="photos.length === 0" class="hero-body">
                <div class="container has-text-centered">
                    <h3 class="subtitle is-1">
                        {{ $t('tags.no-tags') }}
                    </h3>
                    <router-link to="/submit">
                        <h3 class="subtitle button is-medium is-info hov">
                            {{ $t('tags.please-upload') }}
                        </h3>
                    </router-link>
                </div>
            </div>

            <!-- Image is available for tagging -->
            <div v-else>
                <div v-for="photo in photos" :key="photo.id" class="mb2">
                    <div class="columns">

                        <!-- Todo - Put this into a component -->
                        <!-- the Info box, Left -->
                        <div id="image-metadata" class="column">
                            <div class="box image-metadata-box">
                               <ul>
                                   <li class="list-group-item">
                                       {{ $t('tags.to-tag') }}: {{ remaining }}
                                   </li>
                                   <li class="list-group-item">
                                       {{ $t('tags.total-uploaded') }}: {{ user.total_images }}
                                   </li>
                                   <li v-if="photo.team" class="list-group-item">
                                       {{ $t('common.team') }}: <strong>{{ photo.team.name}}</strong>
                                   </li>
                                   <li class="list-group-item">
                                       Next Littercoin: {{ user.littercoin_progress }}%
                                   </li>
                                   <li class="list-group-item">
                                       Total Littercoin: {{ user.total_littercoin }}
                                   </li>
                               </ul>

                                <router-link to="/bulk-tag">
                                    <button class="button is-primary bulk-tag-btn">Tag in bulk</button>
                                </router-link>
                            </div>

                            <div v-if="hasRecentTags" class="box control has-text-centered">
                                <RecentTags :photo-id="photo.id" />
                            </div>

                        </div> <!-- end info box -->

                        <!-- The Image, Middle -->
                        <div class="column is-6 image-wrapper">
                            <!-- The Image -->
                            <div class="image-content">
                                <img
                                    v-img="{ sourceButton: true }"
                                    :src="photo.filename"
                                    class="img"
                                >
                            </div>

                            <!-- Add & Submit Tags -->
                            <div class="column is-10 is-offset-1 mt-4">
                                <add-tags
                                    :id="photo.id"
                                />
                            </div>
                        </div>

                        <!-- Info, Tags, Right -->
                        <div id="image-counts" class="column is-3">
                            <div class="box">
                                <!-- Photo.id, Taken on: -->
                                <p class="list-group-item">
                                    <strong>
                                        #{{ photo.id }}:
                                    </strong>

                                    {{ $t('tags.taken') }} {{ getDate(photo.datetime) }}
                                </p>

                                <!-- Coordinates. was profile6 -->
                                <p class="list-group-item">
                                    <strong>{{ $t('tags.coordinates') }}: </strong>

                                    {{ photo.lat }}, {{ photo.lon }}
                                </p>

                                <!-- Full address. was profile7 -->
                                <p class="list-group-item">
                                    <strong>{{ $t('tags.address') }}: </strong>

                                    {{ photo.display_name }}
                                </p>

                                <!-- Model of the device -->
                                <p class="list-group-item">
                                    <strong>{{ $t('tags.device') }}: </strong>

                                    {{ photo.model }}
                                </p>

                                <!-- Presence button. was profile8 -->
                                <presence
                                    :key="photo.id"
                                />

                                <!-- Delete photo button -->
                                <profile-delete
                                    :photoid="photo.id"
                                    class="mt-4"
                                />
                            </div>

                            <!-- These are the tags the user has added -->
                            <Tags :photo-id="photo.id"/>
                        </div>
                    </div>

                    <!-- Previous, Next Image-->
                    <div class="column" style="text-align: center;">
                        <div class="has-text-centered mt3em">
                            <a
                                v-show="previous_page"
                                class="pagination-previous has-background-link has-text-white"
                                @click="previousImage"
                            >{{ $t('tags.previous') }}</a>
                            <a
                                v-show="remaining > current_page"
                                class="pagination-next has-background-link has-text-white"
                                @click="nextImage"
                            >{{ $t('tags.next') }}</a>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div class="column">
                        <nav class="pagination is-centered" role="navigation" aria-label="pagination">
                            <ul class="pagination-list">
                                <li v-for="i in remaining" :key="i">
                                    <a
                                        :class="(i === current_page ? 'pagination-link is-current': 'pagination-link')"
                                        :aria-label="'page' + current_page"
                                        :aria-current="current_page"
                                        @click="goToPage(i)"
                                    >{{ i }}</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script>
import moment from 'moment';
import Loading from 'vue-loading-overlay';
import 'vue-loading-overlay/dist/vue-loading.css';
import AddTags from '../../components/Litter/AddTags.vue';
import Presence from '../../components/Litter/Presence.vue';
import Tags from '../../components/Litter/Tags.vue';
import ProfileDelete from '../../components/Litter/ProfileDelete.vue';
import RecentTags from '../../components/Litter/RecentTags.vue';

export default {
    name: 'Tag',
    components: {
        Loading,
        AddTags,
        Presence,
        Tags,
        ProfileDelete,
        RecentTags
    },
    async mounted ()
    {
        this.loading = true;

        await this.$store.dispatch('GET_CURRENT_USER');

        await this.$store.dispatch('GET_PHOTOS_FOR_TAGGING');

        this.loading = false;
    },
    data () {
        return {
            loading: true
        };
    },
    computed: {

        /**
         * Get the current page the user is on
         */
        current_page ()
        {
            return this.$store.state.photos.paginate.current_page;
        },

        /**
         * Return true and show Clear Recent Tags button if the user has recent tags
         */
        hasRecentTags ()
        {
            return Object.keys(this.$store.state.litter.recentTags).length > 0 ||
                this.$store.state.litter.recentCustomTags.length > 0;
        },

        /**
         * Paginated array of the users photos where verification = 0
         */
        photos ()
        {
            return this.$store.state?.photos?.paginate?.data;
        },

        /**
         * URL for the previous page, if it exists.
         */
        previous_page ()
        {
            return this.$store.state.photos.paginate?.prev_page_url;
        },

        /**
         * Number of photos the user has left to verify. Verification = 0
         */
        remaining ()
        {
            return this.$store.state.photos.remaining;
        },

        /**
         * Only show Previous button if current page is greater than 1
         * If current page is 1, then we don't need to show the previous page button.
         */
        show_current_page ()
        {
            return this.$store.state.photos.paginate.current_page > 1;
        },

        /**
         * Only show Previous button if next_page_url exists
         */
        show_next_page ()
        {
            return this.$store.state.photos.paginate.next_page_url;
        },

        /**
         * Currently authenticated user
         */
        user ()
        {
            return this.$store.state.user.user;
        }
    },

    methods: {
        /**
         * Format date
         */
        getDate (date)
        {
            return moment(date).format('LLL');
        },

        /**
         * Load a specific page
         */
        goToPage (i)
        {
            this.$store.dispatch('SELECT_IMAGE', i);
        },

        /**
         * Load the next image
         */
        nextImage ()
        {
            this.$store.dispatch('NEXT_IMAGE');
        },

        /**
         * Load the previous page
         */
        previousImage ()
        {
            this.$store.dispatch('PREVIOUS_IMAGE');
        }
    }
};
</script>

<style scoped lang="scss">

    .list-group-item {
        margin: 5px 0;
    }

    .img {
        max-height: 30em;
        border-radius: 5px;
    }

    .tag-container {
        padding: 0 3em;
    }

    .image-wrapper {
        text-align: center;
        .image-content {
            position: relative;
            display: inline-block;

            .delete-img{
                position: absolute;
                top: -19px;
                right: -17px;
                font-size: 40px;
            }
        }
    }

    .image-metadata-box {
        display: flex;
        flex-direction: row;
        justify-content: space-between;
    }

    @media screen and (max-width: 768px)
    {
        .img {
            max-height: 15em;
        }

        .tag-container {
            padding: 0 1em;
        }
    }

    @media screen and (max-width: 1536px)
    {
        .image-metadata-box {
            flex-direction: column;

            .bulk-tag-btn {
                margin-top: 1rem;
            }
        }
    }

</style>
