<template>
    <div @click="deactivate">
        <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

        <div v-else class="columns mt1">

            <ImageInfo />

            <div class="column is-one-third">
                <h1 class="title is-2 has-text-centered">Add bounding box to image # {{ this.imageId }}</h1>

                <div class="has-text-centered">

                    <div
                        id="image-wrapper"
                        ref="img"
                        :style="image"
                        @click.stop
                    >
                        <!-- Todo - add @clicked.stop -->
                        <VueDragResize
                            v-for="box in boxes"
                            :key="box.id"
                            :isActive="box.active"
                            :w="box.width"
                            :h="box.height"
                            v-on:resizing="resize"
                            v-on:dragging="resize"
                            class="test-class"
                            @clicked="activated(box.id)"
                            :minw="5"
                            :minh="5"
                        >
                            <p class="box-tag">{{ box.id }}</p>
                        </VueDragResize>
                    </div>

                    <add-tags
                        :id="imageId"
                        :annotations="true"
                    />
                </div>
            </div>

            <div class="column is-one-third" />
        </div>
    </div>
</template>

<script>
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import ImageInfo from '../../components/Admin/ImageInfo'
import AddTags from '../../components/Litter/AddTags'

import VueDragResize from 'vue-drag-resize'
import ClickOutside from 'vue-click-outside';

export default {
    name: 'BoundingBox',
    components: {
        Loading,
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
            else if (key === "ArrowUp")
            {
                this.$store.commit('moveBoxUp');
            }
            else if (key === "ArrowRight")
            {
                this.$store.commit('moveBoxRight');
            }
            else if (key === "ArrowDown")
            {
                this.$store.commit('moveBoxDown');
            }
            else if (key === "ArrowLeft")
            {
                this.$store.commit('moveBoxLeft');
            }
        });
    },
    // data ()
    // {
    //     return {
    //         activeId: null
    //     };
    // },
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
            console.log('deactivate');
            this.$store.commit('deactivateBoxes');
        },

        /**
         * Resize active box
         */
        resize (newRect)
        {
            this.$store.commit('updateBoxPosition', newRect);
        }
    }
}
</script>

<style scoped>

    #image-wrapper {
        height: 500px;
        width: 500px;
        background-repeat: no-repeat;
        position: relative;
        background-size: 500px 500px;
        margin: 0 auto 1em auto;
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

</style>
