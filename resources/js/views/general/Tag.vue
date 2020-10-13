<template>
    <section class="hero fullheight is-primary is-bold tag-container">

        <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

        <div v-else style="padding-top: 2em;">

            <!-- No Image available for tagging -->
            <div v-if="photos.length == 0" class="hero-body">
                <div class="container has-text-centered">
                    <h3 class="subtitle is-1">{{ $t('tags.no-tags') }}</h3>
                    <h3 class="subtitle is-3">
                        <router-link to="/submit">
                            {{ $t('tags.please-upload') }}
                        </router-link>
                    </h3>
                </div>
            </div>

            <!-- Image is available for tagging -->
            <div v-else>

                <div v-for="photo in photos" class="mb2">

                    <h2 class="taken">
                        <strong style="color: #fff;">#{{ photo.id }}</strong>
                        <!-- was profile.profile5 "Uploaded". now "taken on" -->
                        {{ $t('tags.taken') }}: {{ getDate(photo.created_at) }}
                    </h2>

                    <div class="columns">
                        <!-- the info box, Left -->
                        <div class="column" id="image-metadata">
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
                            </div>
                        </div> <!-- end info box -->

                        <!-- The Image, Middle -->
                        <div class="column is-6" style="text-align: center;">
                            <!-- The Image -->
                            <img :src="photo.filename" class="img" />
                        </div>

                        <!-- Info, Tags, Right -->
                        <div class="column is-3" id="image-counts">
                            <div class="box">
                                <!-- was profile14, 15-->
                                <li class="list-group-item">{{ $t('tags.to-tag') }}: {{ photos.length }}</li>
                                <li class="list-group-item">{{ $t('tags.total-uploaded') }}: {{ user.photos_count }}</li>
                            </div>

                            <!-- These are the tags the user has added -->
                            <Tags />
                        </div>
                    </div>

                    <!-- Add & Submit Tags -->
                    <div class="columns">
                        <div class="column is-10 is-offset-1">
                            <add-tags :id="photo.id" />
                        </div>
                    </div>
                    <br>
                    <!-- Previous, Next Image-->
                    <div class="column" style="text-align: center;">
                        <div class="has-text-centered mt3em">
                            <a
                                :disabled="current_page <= 1"
                                class="pagination-previous has-background-link has-text-white"
                                @click="previousImage"
                            >{{ $t('tags.previous') }}</a>
                            <a
                                :disabled="current_page >= remaining"
                                class="pagination-next has-background-link has-text-white"
                                @click="nextImage"
                            >{{ $t('tags.next') }}</a>
                        </div>
                    </div>   
                    <!-- Pagination -->
                    <div class="column">
                        <nav class="pagination is-centered" role="navigation" aria-label="pagination">
                        <ul class="pagination-list">
                            <li  v-for="i in remaining" :key="i">
                                <a 
                                    :class="(i === current_page ? 'pagination-link is-current': 'pagination-link')" 
                                    :aria-label="'page' + current_page" 
                                    :aria-current="current_page"
                                    @click="goToPage(i)"
                                > {{ i }}    
                                </a>
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
import moment from 'moment'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import AddTags from '../../components/Litter/AddTags'
import ProfileDelete from '../../components/Litter/ProfileDelete'
import Presence from '../../components/Litter/Presence'
import Tags from '../../components/Litter/Tags'

export default {
    name: 'Tag',
    components: {
        Loading,
        AddTags,
        ProfileDelete,
        Presence,
        Tags
    },
    async created ()
    {
        this.loading = true;

        await this.$store.dispatch('GET_PHOTOS_FOR_TAGGING');

        this.loading = false;
    },
    data ()
    {
        return {
            loading: true
        };
    },
    computed: {
        /**
         * Paginated array of the users photos where verification = 0
         */
        photos ()
        {
            return this.$store.state.photos.photos.data;
        },
         /**
         * Get the current page the user in on
         */
        current_page () {
            return this.$store.state.photos.photos.current_page;
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
            return this.$store.state.photos.photos.current_page > 1;
        },

        /**
         * Only show Previous button if next_page_url exists
         */
        show_next_page ()
        {
            return this.$store.state.photos.photos.next_page_url;
        },

        /**
         * Total number of photos the user has uploaded. Verification = 0-3
         */
        total ()
        {
            return this.$store.state.photos.total;
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
        },
        /**
         * Load a specific page
         */
        goToPage (i)
        {
            this.$store.dispatch('SELECT_IMAGE', i);
        }
    }
}
</script>

<style scoped>

    .img {
        max-height: 30em;
    }

    .tag-container {
        padding: 0 5em;
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
