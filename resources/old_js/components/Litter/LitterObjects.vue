<template>
    <div>
        <div
            v-for="(object, index) in objects"
            :key="'obj' + index"
            class="box relative pointer"
            :class="object.id === selectedObjectId ? 'object-selected' : ''"
            @click="changeObjectSelected(object.id)"
        >
            <div class="top-right">
                <p id="deselectObject" @click.stop="deselectObject(object.id)">Deselect</p>
                <p id="deleteObject" @click="deleteObject(object.id)">Delete</p>
            </div>

            <p v-if="object.category" class="litter-tag">Category: {{ object.category }}</p>
            <p v-if="object.object" class="litter-tag">Object: {{ object.object }}</p>
            <p v-if="object.brand" class="litter-tag">Brand: {{ object.brand }}</p>
            <p v-if="object.quantity !== null" class="litter-tag">Quantity: {{ object.quantity }}</p>
            <p v-if="object.tag_type !== null" class="litter-tag">Tag Type: todo</p>
            <p v-if="object.picked_up !== null" class="litter-tag">Picked Up: {{ object.picked_up }}</p>
            <p v-if="object.materials.length > 0" class="litter-tag">Materials: todo</p>
            <p v-if="object.custom_tags.length > 0" class="litter-tag">Custom Tags: todo</p>
        </div>
    </div>
</template>

<script>
import ClickOutside from 'vue-click-outside';

export default {
    name: 'LitterObjects',
    directives: {
        ClickOutside
    },
    computed: {
        objects () {
            return this.$store.state.tags.objects;
        },

        selectedObjectId () {
            return this.$store.state.tags.selectedObjectId;
        }
    },
    methods: {
        changeObjectSelected (id) {
            this.$store.commit('changeObjectSelected', id);
        },

        deleteObject (id) {
            this.$store.commit('deleteLitterObject', id);
        },

        deselectObject (id) {
            this.$store.commit('changeObjectSelected', null);
        },
    }
}
</script>

<style scoped>

    .top-right {
        position: absolute;
        top: 10px;
        right: 15px;
        cursor: pointer;
        display: flex;
        column-gap: 1em;
        font-size: 12px;
    }

    #deselectObject:hover {
        font-weight: 600;
    }

    #deleteObject:hover {
        font-weight: 600;
    }

    .object-selected {
        border: 3px solid #0ca3e0;
    }

</style>
