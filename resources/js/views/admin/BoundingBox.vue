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

                            <!-- activated -->

                            <vue-draggable-resizable
                                v-for="box in boxes"
                                :key="box.id"
                                :x="box.x"
                                :y="box.y"
                                :w="100"
                                :h="100"
                                @dragging="onDrag"
                                @resizing="onResize"
                                :parent="true"
                                class-name="test-class"
                                class-name-active="my-active-class"
                            >
                                <p>X: {{ x }} / Y: {{ y }} - Width: {{ width }} / Height: {{ height }}</p>
                            </vue-draggable-resizable>
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
                boxes = boxes.filter(box => box.selected != true);
                this.boxes = boxes;
            }
        });
    },
    data ()
    {
        return {
            processing: false,
            boxes: [
                { id: 1, x: 0, y: 0, text: '' }
            ],
            // vue draggable
            width: 0,
            height: 0,
            x: 0,
            y: 0
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
         * Add a new bounding box
         */
        addNewBox ()
        {
            this.x = 0;
            this.y = 0;
            this.width = 0;
            this.height = 0;

            this.boxes.push({
                id: this.boxes.length + 1,
                x: 0,
                y: 0,
                text: ''
            });
        },

        /**
         *
         */
        onDrag (x, y)
        {
            this.x = x
            this.y = y
        },

        /**
         *
         */
        onResize (x, y, width, height)
        {
            this.x = x
            this.y = y
            this.width = width
            this.height = height
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
        margin: 0 auto 1em auto;
    }

    .test-class {
        border: 3px solid green;
    }

    .my-active-class {
        border: 3px solid red;
    }

</style>
