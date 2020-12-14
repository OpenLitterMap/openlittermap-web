<template>
    <div>

        <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

        <div v-else>
            <h1 class="title is-2 has-text-centered mt1em">Add bounding box to image # {{ this.imageId }}</h1>

                <div style="text-align: center;">

                    <div id="image-wrapper"
                         :style="image"
                         @mousedown.self="mousedownSelf"
                         @mousemove="mouseMove"
                         @mouseup="mouseUp"
                    >

                        <Box
                            v-if="drawingBox.active"
                            :geom="drawingBox.geom"
                        />

                        <Box
                            v-for="(box, i) in boxes"
                            :key="i"
                            :geom="box.geom"
                            :index="i"
                            :selected="box.selected"
                            :activeTop="box.activeTop"
                            :activeLeft="box.activeLeft"
                            :activeBottom="box.activeBottom"
                            :activeRight="box.activeRight"
                            @select="selectBox"
                            @activate="activate"
                            @deselectNode="deselectNode"
                            @dragEnd="dragEnd"
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

const minBoxSize = 43;

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
                geom: [0, 0, 0, 0]                      // box top, left, width, height
            },
            boxes: [],
            activatedBox: -1,
            activatedNode: 0,
            currentX: 0,
            currentY: 0,
            dir: [[0, 1],[1, 0],[0, 1],[1, 0]], 
            apply: [[1, 0, 0, -1],[0, 1, -1, 0],[0, 0, 0, 1],[0, 0, 1, 0]], 
            dragBox: -1,
            startPos: [0, 0],
            startGeom: [0, 0, 0, 0],
            c: [1, 0, 1, 0]
        };
    },
    computed: {

        /**
         * Filename of the image from the database
         */
        image ()
        {
            //return 'backgroundImage: url(' + this.$store.state.admin.filename + ')';
            return 'backgroundImage: url(assets/plastic_bottles.jpg)';
        },

        /**
         * The ID of the image being edited
         */
        imageId ()
        {
            return 1;//this.$store.state.admin.id;
        },

        /**
         * Boolean
         */
        loading ()
        {
            return 0;//this.$store.state.admin.loading;
        }

    },
    methods: {

        /**
         * Activate a node on a box-index for reordering
         */
        activate (index, nodeIndex, pageX, pageY)
        {
            this.activatedBox = index;
            this.activatedNode = nodeIndex;
            this.currentX = pageX;
            this.currentY = pageY;

            this.startPos = [pageX, pageY];
            this.startGeom = this.boxes[index].geom
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
            this.boxes[index][node] = false;
        },

        /**
         * Drag and draw the box (Top-left to bottom-right)
         */
        mouseMove (e)
        {
            // Record the relative movement of the mouse since the last call:
            var move = [e.pageX - this.currentX, e.pageY - this.currentY];
            this.currentX = e.pageX;
            this.currentY = e.pageY;
            var newPos = [e.pageX, e.pageY];

            // Drawing box mode:
            if (this.drawingBox.active)
            {
                var newWidth = e.offsetX - this.drawingBox.geom[1];
                var newHeight = e.offsetY - this.drawingBox.geom[0];

                if(e.target.id != "image-wrapper")
                {
                    newWidth = e.offsetX;
                    newHeight = e.offsetY;
                }

                this.drawingBox = {
                    ...this.drawingBox,
                    geom: [this.drawingBox.geom[0], this.drawingBox.geom[1], newWidth, newHeight]
                };
            }

            // Box shape adjustment mode:
            if (this.activatedBox != -1)
            {
                var diff = newPos[this.c[this.activatedNode]] - this.startPos[this.c[this.activatedNode]];

                var newWidth = this.startGeom[2] + this.apply[this.activatedNode][2] * diff;
                var newHeight = this.startGeom[3] + this.apply[this.activatedNode][3] * diff;

                if((newWidth > minBoxSize)&&(newHeight > minBoxSize))
                {
                    this.boxes[this.activatedBox].geom = [
                        this.startGeom[0] + this.apply[this.activatedNode][0] * diff,
                        this.startGeom[1] + this.apply[this.activatedNode][1] * diff,
                        newWidth,
                        newHeight
                    ];
                }
            }

            // Box dragging mode:
            if (this.dragBox != -1)
            {
                this.boxes[this.dragBox].geom = [
                    this.boxes[this.dragBox].geom[0] + move[1], 
                    this.boxes[this.dragBox].geom[1] + move[0],
                    this.boxes[this.dragBox].geom[2],
                    this.boxes[this.dragBox].geom[3]
                ];
            }
        },

        /**
         * A box has been selected
         */
        selectBox (i, pageX, pageY)
        {
            if(this.activatedBox == -1)
            {
                // Record the box which has been selected, and set all other boxes to be deselected:
                for(var b = 0; b < this.boxes.length; b++) this.boxes[b].selected = false;
                this.boxes[i].selected = true;

                this.dragBox = i;
                this.currentX = pageX;
                this.currentY = pageY;
            }
        },

        /**
         *
         */
        mousedownSelf (e)
        {
            // The user has clicked outside all the boxes, so deselect all boxes:
            for(var b = 0; b < this.boxes.length; b++) this.boxes[b].selected = false;

            // Now activate box drawing mode:
            this.drawingBox = {
                active: true,
                geom: [e.offsetY, e.offsetX, 0, 0]
            };
        },

        /**
         * When a box dragging event ends: 
         */
        dragEnd()
        {
            this.dragBox = -1;
        },

        /**
         *
         */
        mouseUp ()
        {
            if (this.drawingBox.active)
            {
                if (this.drawingBox.geom[2] > 5)
                {
                    //console.log(this.drawingBox.geom);

                    this.boxes.push({
                        geom: this.drawingBox.geom,
                        selected: false,
                        activeTop: false,
                        activeLeft: false,
                        activeBottom: false,
                        activeRight: false,
                    });
                }
                this.drawingBox = {
                    active: false, 
                    geom: [0, 0, 0, 0]
                }
            }

            this.activatedBox = -1;
            this.dragEnd();
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
        margin: 0 auto;
    }
    
</style>