<template>
    <div>
        <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

        <div v-else class="columns mt1">

            <ImageInfo
                @addNewBox="addNewBox"
            />

            <div class="column is-one-third">
                <h1 class="title is-2 has-text-centered">Add bounding box to image # {{ this.imageId }}</h1>

                <div class="has-text-centered">

                    <div
                        id="image-wrapper"
                        ref="img"
                        :style="image"
                        v-click-outside="deactivate"
                    >
                        <VueDragResize
                            v-for="(box, index) in boxes"
                            :key="box.id"
                            :isActive="box.active"
                            :w="box.width"
                            :h="box.height"
                            v-on:resizing="resize"
                            v-on:dragging="resize"
                            class="test-class"
                            @clicked.prevent="activated(box.id)"
                        >
                            <p>{{ box.id }}</p>
                            <p>Top: {{ box.top }}, Left: {{ box.left }}</p>
                            <p>Width: {{ box.width }}, Height: {{ box.height }}</p>
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
                let boxes = [...this.boxes];

                boxes = boxes.filter(box => box.id !== this.activeId);

                this.boxes = boxes;
            }
        });

        // todo - move +1 pixels x, y with keyboard for currently active box
    },
    data ()
    {
        return {
            activeId: null,
            draggingId: null,
            processing: false,
            boxes: [
                { id: 1, top: 0, left: 0, height: 100, width: 100, text: '', active: false }
            ]
        };

    },
    computed: {

        /**
         * The current box.id being dragged
         */
        draggingBox ()
        {
            if (! this.draggingId) return;

            return this.boxes.find(el => el.id === this.draggingId);
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
         * When a box has been selected
         *
         * Hold box.id
         */
        activated (id)
        {
            this.boxes.map(box => {
                box.active = box.id === id;

                return box;
            });
        },

        /**
         * Add a new bounding box
         *
         * We +1 the last ID in case a box was deleted
         */
        addNewBox ()
        {
            this.boxes.push({
                id: this.boxes[this.boxes.length -1].id + 1,
                top: 0,
                left: 0,
                height: 100,
                width: 100,
                text: '',
                active: false
            });
        },

        /**
         * Deactivate all boxes
         */
        deactivate ()
        {
            this.boxes.map(box => box.active = false);
        },


        /**
         * Resize active box
         */
        resize (newRect)
        {
            this.boxes.map(box => {

                if (box.active)
                {
                    box.width = newRect.width;
                    box.height = newRect.height;
                    box.top = newRect.top;
                    box.left = newRect.left;
                }

                return box;
            });
        }
    }
}
</script>

<style scoped>

    .mt1em {
        margin-top: 1em;
    }

    #image-wrapper {
        height: 500px;
        width: 500px;
        background-repeat: no-repeat;
        position: relative;
        background-size: 500px 500px;
        margin: 0 auto 1em auto;
    }

    .test-class {
        border: 3px solid green;
        background: #00b89c;
    }

    .my-active-class {
        border: 3px solid red;
    }

</style>
