<template>
    <div @click="deactivate" class="relative h100">
        <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

        <div v-else class="columns mt1">

            <Boxes />

            <div class="column is-one-third">
                <h1 class="title is-2 has-text-centered">{{ getTitle }}</h1>

                <div class="display-inline-grid" @click.stop>

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
                            v-show="! box.hidden"
                            :minw="5"
                            :minh="5"
                            :stickSize="stickSize"
                            :parentLimitation="true"
                            :z="box.id"
                            @clicked="activated(box.id)"
                            @dragging="dragging"
                            @resizing="resize"
                            @resizestop="resizestop"
                        ><p class="box-tag">{{ boxText(box.id, box.showLabel, box.category, box.tag) }}</p></VueDragResize>
                    </div>

                    <add-tags
                        :id="imageId"
                        v-show="isAdmin"
                        :annotations="true"
                        :isVerifying="isVerifying"
                        :show-custom-tags="false"
                    />

                </div>
            </div>

            <div class="column is-2 is-offset-1 has-text-centered">

                <!-- The list of tags associated with this image -->
                <Tags :admin="isAdmin" />

                <button
                    v-if="isAdmin"
                    :class="updateButton"
                    @click="update"
                    :disabled="disabled"
                >Update Tags</button>

                <button
                    v-else
                    :class="wrongTagsButton"
                    @click="wrongTags"
                    :disabled="disabled"
                >Wrong Tags</button>

                <button
                    :class="skipButton"
                    @click="skip"
                    :disabled="disabled"
                >Cannot use this image</button>

                <!-- Todo - Go Back button (reverse skip) -->
            </div>
        </div>

        <div class="littercoin-pos">
            <p>Your boxes: {{ this.usersBoxCount }}</p>
            <p>Total Boxes: {{ this.totalBoxCount }}</p>
            <p>Littercoin earned: {{ this.littercoinEarned }}</p>
            <p>Next Littercoin: {{ this.littercoinProgress }}</p>
        </div>
    </div>
</template>

<script>
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import Boxes from '../../components/Admin/Boxes.vue'
import Tags from '../../components/Litter/Tags.vue'
import AddTags from '../../components/Litter/AddTags.vue'
import BrandsBox from '../../components/Admin/Bbox/BrandsBox.vue'

import VueDragResize from 'vue-drag-resize'
import ClickOutside from 'vue-click-outside'

export default {
    name: 'BoundingBox',
    components: {
        Loading,
        Tags,
        AddTags,
        Boxes,
        VueDragResize,
        BrandsBox
    },
    directives: {
        ClickOutside
    },
    async created ()
    {
        if (window.innerWidth < 1000)
        {
            this.isMobile = true;
            this.stickSize = 30;
        }

        if (window.location.href.includes('verify'))
        {
            this.isVerifying = true;
            this.$store.dispatch('GET_NEXT_BOXES_TO_VERIFY');
        }
        else
        {
            this.$store.dispatch('GET_NEXT_BBOX');
        }
    },
    data ()
    {
        return {
            stickSize: 6,
            skip_processing: false,
            update_processing: false,
            wrong_tags_processing: false,
            isMobile: false,
            isVerifying: false
        };
    },
    mounted ()
    {
        document.addEventListener("keydown", (e) => {

            const key = e.key;

            // dont need this anymore?
            // if (key === "Backspace")
            // {
            //     this.$store.commit('removeActiveBox');
            // }

            if (key === "ArrowUp")
            {
                e.preventDefault();
                this.$store.commit('moveBoxUp');
            }

            else if (key === "ArrowRight")
            {
                e.preventDefault();
                this.$store.commit('moveBoxRight');

            }
            else if (key === "ArrowDown")
            {
                e.preventDefault();
                this.$store.commit('moveBoxDown');
            }

            else if (key === "ArrowLeft")
            {
                e.preventDefault();
                this.$store.commit('moveBoxLeft');
            }

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
         * Return true to disable all buttons
         */
        disabled ()
        {
            return (this.skip_processing || this.update_processing || this.wrong_tags_processing);
        },

        /**
         * Return main title
         */
        getTitle ()
        {
            return this.isVerifying
                ? `Verify boxes for image # ${this.imageId}`
                : `Add bounding box to image # ${this.imageId}`;
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
        isAdmin ()
        {
            return (this.$store.state.user.admin || this.$store.state.user.helper);
        },

        /**
         * Total number of Littercoins the user has earned
         */
        littercoinEarned ()
        {
            return this.$store.state.user.user.littercoin_owed + this.$store.state.user.user.littercoin_allowance;
        },

        /**
         * Number of boxes the user has left to verify to earn a Littercoin
         */
        littercoinProgress ()
        {
            return this.$store.state.user.user.bbox_verification_count + "%"
        },

        /**
         * Boolean
         */
        loading ()
        {
            return this.$store.state.admin.loading;
        },

        /**
         * Add spinner when processing
         */
        skipButton ()
        {
            let str = 'button is-medium is-warning mt1 ';

            return this.skip_processing ? str + ' is-loading' : str;
        },

        /**
         * Total count of all boxes submitted by all users
         */
        totalBoxCount ()
        {
            return this.$store.state.bbox.totalBoxCount;
        },

        /**
         * Total number of boxes submitted by the current user
         */
        usersBoxCount ()
        {
            return this.$store.state.bbox.usersBoxCount;
        },

        /**
         * Add spinner when processing
         */
        updateButton ()
        {
            let str = 'button is-medium is-primary mt1 ';

            return this.update_processing ? str + 'is-loading' : str;
        },

        /**
         * Add spinner when processing
         */
        wrongTagsButton ()
        {
            let str = 'button is-medium is-primary mt1 ';

            return this.wrong_tags_processing ? str + 'is-loading' : str;
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
         *
         */
        boxText (id, showLabel, category, tag)
        {
            return showLabel
                ? this.$t(`litter.${category}.${tag}`)
                : id;
        },

        /**
         * Deactivate all boxes
         */
        deactivate ()
        {
            this.$store.commit('deactivateBoxes');
        },

        /**
         * Dragging active box
         */
        dragging (newRect)
        {
            this.$store.commit('updateBoxPosition', newRect);
        },

        /**
         * Resize active box
         */
        resize (newRect)
        {
            this.stickSize = 1;

            this.$store.commit('updateBoxPosition', newRect);
        },

        /**
         * When resizing stops, reset the sticks-size
         */
        resizestop ()
        {
            this.stickSize = this.isMobile ? 30 : 6;
        },

        /**
         * Skip this image
         *
         * Mark as cannot be used for bounding boxes
         */
        async skip ()
        {
            this.skip_processing = true;

            await this.$store.dispatch('BBOX_SKIP_IMAGE', this.isVerifying);

            this.skip_processing = false;
        },

        /**
         * Update the tags for this image
         */
        async update ()
        {
            this.update_processing = true;

            await this.$store.dispatch('BBOX_UPDATE_TAGS');

            this.update_processing = false;
        },

        /**
         * For a non-admin, they can mark the image with wrong tags
         *
         * Only admin can update Tags at stage 2
         */
        wrongTags ()
        {
            this.wrong_tags_processing = true;

            this.$store.dispatch('BBOX_WRONG_TAGS');

            this.wrong_tags_processing = false;
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
        border: 1px solid red;
    }

    .vdr.active:before {
        outline: 0;
    }

    .box-tag {
        background-color: red;
        position: absolute;
        top: -1.5em;
        right: 0;
        padding: 0 5px;
        margin-right: -3px;
    }

    .display-inline-grid {
        display: inline-grid;
    }

    .filler {
        width: 100%;
        height: 100%;
        position: absolute;
    }

    .littercoin-pos {
        position: fixed;
        background: white;
        bottom: 0;
        left: 1em;
        margin-bottom: 1em;
    }

</style>
