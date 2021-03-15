<template>
    <div @click="deactivate">
        <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

        <div v-else class="columns mt1">

            <ImageInfo />

            <div class="column is-one-third">
                <h1 class="title is-2 has-text-centered">Add bounding box to image #{{ this.imageId }}</h1>

                <div class="has-text-centered" @click.stop>

                    <div
                        id="image-wrapper"
                        ref="img"
                        :style="image"
                    >
                        <VueDragResize
                            v-for="box in boxes"
                            :key="box.id"
                            :w="box.width"
                            :h="box.height"
                            :x="box.left"
                            :y="box.top"
                            :isActive="box.active"
                            :minw="10"
                            :minh="10"
                            :parentLimitation="true"
                            :z="box.id"
                            @clicked="activated(box.id)"
                            @resizing="resize"
                            @dragging="resize"
                        ><p class="box-tag">{{ box.id }}</p></VueDragResize>
                    </div>

                    <add-tags
                        :id="imageId"
                        :annotations="true"
                    />

                </div>
            </div>

            <div class="column is-2 is-offset-1 has-text-centered">

                <!-- The list of tags associated with this image-->
                <Tags :admin="true" />

                <button class="button is-medium is-primary mt1" @click="update">Update Tags</button>

                <button class="button is-medium is-warning mt1" @click="skip">Cannot use this image</button>

                <!-- Todo - Go Back button (reverse skip) -->
            </div>
        </div>
    </div>
</template>

<script>
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import ImageInfo from '../../components/Admin/ImageInfo'
import Tags from '../../components/Litter/Tags'
import AddTags from '../../components/Litter/AddTags'

import VueDragResize from 'vue-drag-resize'
import ClickOutside from 'vue-click-outside';

export default {
    name: 'BoundingBox',
    components: {
        Loading,
        Tags,
        AddTags,
        ImageInfo,
        VueDragResize
    },
    directives: {
        ClickOutside
    },
    async created ()
    {
        this.$store.dispatch('GET_NEXT_BBOX');
    },
    mounted ()
    {
        // delete selected box on backspace
        document.addEventListener("keydown", (e) => {

            const key = e.key;

            if (key === "Backspace")
            {
                this.$store.commit('removeActiveBox');
            }

            // Todo
            // else if (key === "ArrowUp")
            // {
            //     this.$store.commit('moveBoxUp');
            // }
            // else if (key === "ArrowRight")
            // {
            //     this.$store.commit('moveBoxRight');
            // }
            // else if (key === "ArrowDown")
            // {
            //     this.$store.commit('moveBoxDown');
            // }
            // else if (key === "ArrowLeft")
            // {
            //     this.$store.commit('moveBoxLeft');
            // }
        });
    },
    computed: {

        /**
         * Array of bounding boxes
         */
        boxes ()
        {
            return this.$store.state.bbox.boxes;
        },

        /**
         * Filename of the image from the database
         */
        image ()
        {
            return 'backgroundImage: url(' + this.$store.state.admin.filename + ')';
        },

        /**
         * The ID of the image being edited
         */
        imageId ()
        {
            return this.$store.state.admin.id;
        },

        /**
         * Boolean
         */
        loading ()
        {
            return this.$store.state.admin.loading;
        }

    },
    methods: {

        /**
         * Box.active => True
         */
        activated (id)
        {
            this.$store.commit('activateBox', id);
        },

        /**
         * Deactivate all boxes
         */
        deactivate ()
        {
            this.$store.commit('deactivateBoxes');
        },

        /**
         * Resize active box
         */
        resize (newRect)
        {
            this.$store.commit('updateBoxPosition', newRect);
        },

        /**
         * Skip this image
         *
         * Mark as cannot be used for bounding boxes
         */
        async skip ()
        {
            // commit skip_processing true

            await this.$store.dispatch('BBOX_SKIP_IMAGE');

            // commit skip_processing false
        },

        /**
         * Update the tags for this image
         */
        async update ()
        {
            // commit bbox_update true

            await this.$store.dispatch('BBOX_UPDATE_TAGS');

            // commit bbox_update false
        }
    }
}
</script>

<style>

    #image-wrapper {
        height: 500px;
        width: 500px;
        background-repeat: no-repeat;
        position: relative;
        background-size: 500px 500px;
        margin: 0 auto 1em auto;
    }

    .vdr {
        border: 3px solid red;
    }

    .vdr.active:before {
        outline: 0;
    }

    .test-class {
        border: 3px solid red;
        position: relative;
    }

    .box-tag {
        background-color: red;
        position: absolute;
        top: -1.5em;
        right: 0;
        padding: 0 5px;
        margin-right: -3px;
    }

    .filler {
        width: 100%;
        height: 100%;
        position: absolute;
    }

</style>
