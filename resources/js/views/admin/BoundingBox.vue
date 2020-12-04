<template>
    <div>

        <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

        <div v-else>
            <h1 class="title is-2 has-text-centered mt1em">Add bounding box to image # {{ this.imageId }}</h1>

                <div style="text-align: center;">

                    <div id="image-wrapper"
                         :style="image"
                         @mousedown.self="startDrawingBox"
                         @mousemove="mouseMove"
                         @mouseup="mouseUp"
                    >

                        <Box
                            v-if="drawingBox.active"
                            :geom="drawingBox.geom"
                        />

                        <Box
                            v-for="(box, i) in boxes"
                            :key="i + box.height"
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
                            @repositionTop="repositionTop"
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
                geom: [0, 0, 0, 0]
            },
            boxes: [],
            activatedBox: -1,
            activatedNode: 0,
            currentX: 0,
            currentY: 0,
            dir: [[0, 1],[1, 0],[0, 1],[1, 0]],
            apply: [[1, 0, 0, -1],[0, 1, -1, 0],[0, 0, 0, 1],[0, 0, 1, 0]], 
            dragBox: -1
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
        activate (index, nodeIndex, pageX, pageY)
        {
            this.activatedBox = index;
            this.activatedNode = nodeIndex;
            this.currentX = pageX;
            this.currentY = pageY;
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

            // Drawing box mode:
            if (this.drawingBox.active)
            {
                this.drawingBox = {
                    ...this.drawingBox,
                    geom: [this.drawingBox.geom[0], this.drawingBox.geom[1], e.offsetX - this.drawingBox.geom[1], e.offsetY - this.drawingBox.geom[0]]
                };
            }

	       // Box shape adjustment mode:
           if (this.activatedBox != -1)
           {
                var diff = move[0] * this.dir[this.activatedNode][0] + move[1] * this.dir[this.activatedNode][1];

                this.boxes[this.activatedBox].geom = [
                    this.boxes[this.activatedBox].geom[0] + this.apply[this.activatedNode][0] * diff,
                    this.boxes[this.activatedBox].geom[1] + this.apply[this.activatedNode][1] * diff,
                    this.boxes[this.activatedBox].geom[2] + this.apply[this.activatedNode][2] * diff,
                    this.boxes[this.activatedBox].geom[3] + this.apply[this.activatedNode][3] * diff
                ];
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
                this.boxes[i].selected = true;
                this.dragBox = i;
                this.currentX = pageX;
                this.currentY = pageY;
            }
        },

        /**
         *
         */
        startDrawingBox (e)
        {
            this.drawingBox = {
                active: true,
                geom: [e.offsetY, e.offsetX, 0, 0]
            };
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
            dragEnd();
        },

        /**
         * When a box dragging event ends: 
         */
        dragEnd()
        {
            this.dragBox = -1;
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