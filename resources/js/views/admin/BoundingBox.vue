<template>
    <div>

        <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

        <div v-else>
            <h1 class="title is-2 has-text-centered mt1em">Add bounding box to image # {{ this.imageId }}</h1>

                <div style="text-align: center;">

                    <div id="image-wrapper"
                         :style="image"
                         @mousedown.self="startDrawingBox"
                         @mousemove="drawBox"
                         @mouseup="stopDrawingBox"
                    >

                        <Box
                            v-if="drawingBox.active"
                            :top="drawingBox.top"
                            :left="drawingBox.left"
                            :width="drawingBox.width"
                            :height="drawingBox.height"
                        />

                        <Box
                            v-for="(box, i) in boxes"
                            :key="i + box.height"
                            :index="i"
                            :top="box.top"
                            :left="box.left"
                            :width="box.width"
                            :height="box.height"
                            :selected="box.selected"
                            :activeTop="box.activeTop"
                            :activeLeft="box.activeLeft"
                            :activeBottom="box.activeBottom"
                            :activeRight="box.activeRight"
                            @select="selectBox(i)"
                            @activate="activate"
                            @deselectNode="deselectNode"
                            @repositionTop="repositionTop"
                        />

                </div>
            </div>
        </div>
    </div>
</template>

<script>
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'

import Box from '../../components/Admin/Box'

export default {
    name: 'BoundingBox',
    components: { Loading, Box },
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
                boxes = boxes.filter(box => box.selected != true);
                this.boxes = boxes;
            }
        });
    },
    data ()
    {
        return {
            disabled: false,
            processing: false,
            drawingBox: {
                active: false,
                top: 0,
                left: 0,
                height: 0,
                width: 0
            },
            boxes: []
        };
    },
    computed: {

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
         * Activate a node on a box-index for reordering
         */
        activate (node, index)
        {
            this.boxes[index][node] = true;
        },

        /**
         * De-select any boxes
         */
        deselectBox ()
        {
            this.boxes.map(box => box.selected = false);
        },

        /**
         * One of the nodes was left go from dragging
         */
        deselectNode (node, index)
        {
            console.log('deselectNode', node, index);
            this.boxes[index][node] = false;
        },

        /**
         * Drag and draw the box (Top-left to bottom-right)
         */
        drawBox (e)
        {
            if (this.drawingBox.active)
            {
                this.drawingBox = {
                    ...this.drawingBox,
                    width: e.offsetX - this.drawingBox.left,
                    height: e.offsetY - this.drawingBox.top,
                };
            }
        },

        /**
         *
         */
        repositionTop (px, index)
        {
            console.log('repositionTop', px);
            
            if (px >= 0)
            {
                this.boxes[index].top += px;
                this.boxes[index].height -= px;
            }

            else
            {
                this.boxes[index].top -= px;
                this.boxes[index].height += px;
            }

        },

        /**
         * A box has been selected
         */
        selectBox (i)
        {
            this.boxes[i].selected = true;
        },

        /**
         *
         */
        startDrawingBox (e)
        {
            this.drawingBox = {
                width: 0,
                height: 0,
                top: e.offsetY,
                left: e.offsetX,
                active: true,
            };
        },

        /**
         *
         */
        stopDrawingBox ()
        {
            if (this.drawingBox.active)
            {
                if (this.drawingBox.width > 5)
                {
                    this.boxes.push({
                        top: this.drawingBox.top,
                        left: this.drawingBox.left,
                        height: this.drawingBox.height,
                        width: this.drawingBox.width,
                        selected: false,
                        activeTop: false,
                        activeLeft: false,
                        activeBottom: false,
                        activeRight: false,
                    });
                }

                this.drawingBox = {
                    active: false,
                    top: 0,
                    left: 0,
                    height: 0,
                    width: 0
                }
            }
        },
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
        margin: 0 auto;
    }

</style>