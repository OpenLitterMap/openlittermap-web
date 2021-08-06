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
                    <h3 class="subtitle button is-medium is-info hov">
                        <router-link to="/submit">
                            {{ $t('tags.please-upload') }}
                        </router-link>
                    </h3>
                </div>
            </div>

            <!-- Image is available for tagging -->
            <div v-else>
                <div v-for="photo in photos" :key="photo.id" class="mb2">
                    <h2 class="taken">
                        <strong style="color: #fff;">#{{ photo.id }}</strong>
                        <!-- was profile.profile5 "Uploaded". now "taken on" -->
                        {{ $t('tags.taken') }}: {{ getDate(photo.datetime) }}
                    </h2>

                    <div class="columns">

                        <!-- Todo - Put this into a component -->
                        <!-- the Info box, Left -->
                        <div id="image-metadata" class="column">
                            <div class="box">

                                <!-- Coordinates. was profile6 -->
                                <p><strong>{{ $t('tags.coordinates') }}: </strong>{{ photo.lat }}, {{ photo.lon }}</p>
                                <br>

                                <!-- Full address. was profile7 -->
                                <p><strong>{{ $t('tags.address') }}: </strong>{{ photo.display_name }}</p>
                                <br>

                                <!-- Presence button. was profile8 -->
                                <div>
                                    <strong>{{ $t('tags.picked-up-title') }}</strong>
                                    <presence />
                                </div>
                                <br>

                                <!-- Model of the device -->
                                <p><strong>{{ $t('tags.device') }}: </strong>{{ photo.model }}</p>
                                <br>

                                <!-- Delete photo button -->
                                <profile-delete :photoid="photo.id" />

                                <!-- Clear recent tags -->
                                <div v-show="hasRecentTags">
                                    <br>
                                    <p class="strong">{{ $t('tags.clear-tags') }}</p>

                                    <button @click="clearRecentTags">{{ $t('tags.clear-tags-btn') }}</button>
                                </div>
                            </div>
                        </div> <!-- end info box -->

                        <!-- The Image, Middle -->
                        <div class="column is-6 image-wrapper">
                            <!-- The Image -->
                            <div class="image-content">
                                <img :src="photo.filename" class="img">
                            </div>

                            <!-- Add & Submit Tags -->
                            <div class="column is-10 is-offset-1 mt-4">
                                <add-tags :id="photo.id" />
                            </div>
                        </div>

                        <!-- Info, Tags, Right -->
                        <div id="image-counts" class="column is-3">
                            <div class="box">
                                <!-- was profile14, 15-->
                                <li class="list-group-item">
                                    {{ $t('tags.to-tag') }}: {{ remaining }}
                                </li>
                                <li class="list-group-item">
                                    {{ $t('tags.total-uploaded') }}: {{ user.total_images }}
                                </li>
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
import AddTags from '../../components/Litter/AddTags';
import Presence from '../../components/Litter/Presence';
import Tags from '../../components/Litter/Tags';
import ProfileDelete from '../../components/Litter/ProfileDelete';

export default {
    name: 'Tag',
    components: {
        Loading,
        AddTags,
        Presence,
        Tags,
        ProfileDelete
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
            return Object.keys(this.$store.state.litter.recentTags).length > 0;
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
         * Remove the users recent tags
         */
        clearRecentTags ()
        {
            this.$store.commit('initRecentTags', {});

            this.$localStorage.remove('recentTags');
        },

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

    .img {
        max-height: 30em;
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

    .taken {
        color: #fff;
        font-weight: 600;
        font-size: 2.5rem;
        line-height: 1.25;
        margin-bottom: 1em;
        text-align: center;
    }

    @media screen and (max-width: 768px)
    {
        .img {
            max-height: 15em;
        }

        .tag-container {
            padding: 0 1em;
        }

        .taken {
            display: none;
        }
    }

</style>
