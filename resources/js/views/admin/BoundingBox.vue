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

                        <div style="height: 500px; width: 500px; position: relative;">

                            <template v-for="box in boxes">
                                <vue-draggable-resizable
                                    :key="box.id + box.x + box.y"
                                    :x="box.x"
                                    :y="box.y"
                                    :h="box.height"
                                    :w="box.width"
                                    :parent="true"
                                    :resizeable="true"
                                    @activated="activated(box.id)"
                                    @dragging="(x, y) => onDragging(box.id, x, y)"
                                    @dragstop="(x, y) => onDragStop(box.id, x, y)"
                                    @resizing="(left, top, width, height) => onResizing(box.id, left, top, width, height)"
                                    class-name="test-class"
                                    class-name-active="my-active-class"
                                ><p>X: {{ box.x }} / Y: {{ box.y }}</p>
                                </vue-draggable-resizable>
                            </template>
                        </div>
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
import Box from '../../components/Admin/Box'
import AddTags from '../../components/Litter/AddTags';

const minBoxSize = 43;

export default {
    name: 'BoundingBox',
    components: {
        Loading,
        Box,
        AddTags,
        ImageInfo
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

        // move +1 pixels x, y with keyboard
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
            prevOffsetX: 0,
            prevOffsetY: 0,
            sync: false,
            x: 0,
            y: 0
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
        activated (id)
        {
            this.activeId = id;
        },

        /**
         * Add a new bounding box
         */
        addNewBox ()
        {
            this.boxes.push({
                id: this.boxes.length + 1,
                x: 0,
                y: 0,
                height: 100,
                width: 100,
                text: '',
                active: false
            });
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
         */
        onDragStop (id, x, y)
        {
            console.log('onDragStop', id, x, y);

            this.boxes.map(box => {

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

        /**
         *
         */
        onResizing (id, left, top, width, height)
        {
            console.log(id, left, top, width, height)
        }

        // /**
        //  *
        //  */
        // onResize (x, y, width, height)
        // {
        //     this.x = x
        //     this.y = y
        //     this.width = width
        //     this.height = height
        // },
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
