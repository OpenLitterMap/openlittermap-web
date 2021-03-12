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
                            @clicked.prevent="activated(index)"
                        >
                            <p>{{ box.id }}</p>
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

export default {
    name: 'BoundingBox',
    components: {
        Loading,
        AddTags,
        ImageInfo,
        VueDragResize
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
                { id: 1, x: 0, y: 0, height: 100, width: 100, text: '', active: false }
            ],
            width: 0,
            height: 0,
            top: 0,
            left: 0
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
         *
         * Todo - clear this when click outside
         */
        activated (index)
        {
            // this.activeId = id;
            this.boxes[index].active = true;
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
                x: 0,
                y: 0,
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

        deltaX (offsetX)
        {
            const ret = offsetX - this.prevOffsetX;

            this.prevOffsetX = offsetX;

            return ret;
        },


        deltaY (offsetY)
        {
            const ret = offsetY - this.prevOffsetY;

            this.prevOffsetY = offsetY;

            return ret;
        },

        /**
         * When we stop dragging a box
         *
         * Bug:
         */
        onDragStop (id, x, y)
        {
            console.log({ y });

            this.boxes.map((box, index) => {

                if (box.id === id)
                {
                    box.x = x;
                    box.y = y;
                }

                return box;
            });

            this.draggingId = null;
            this.prevOffsetX = 0;
            this.prevOffsetY = 0;

        },

        /**
         * When dragging, update (x,y) values on that box
         */
        onDragging (id, x, y)
        {
            this.draggingId = id;

            if (! this.sync) return;

            const offsetX = x - this.draggingBox.x;
            const offsetY = y - this.draggingBox.y;

            const deltaX = this.deltaX(offsetX);
            const deltaY = this.deltaY(offsetY);

            this.boxes.map(el => {
                if (el.id !== id) {
                    el.x += deltaX;
                    el.y += deltaY;
                }

                return el;
            });
        },

        resize(newRect) {
            this.width = newRect.width;
            this.height = newRect.height;
            this.top = newRect.top;
            this.left = newRect.left;
        },

        /**
         *
         */
        onResizing (id, left, top, width, height)
        {
            // console.log(id, left, top, width, height)
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
