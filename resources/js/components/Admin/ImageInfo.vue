<template>
    <div class="column is-one-third pl3 pt7">
        <p>Image info</p>
        <p>Height: </p>
        <p>Width: </p>
        <p>Tags: </p>

        <br>

        <button class="button is-medium is-primary" @click="addNewBox">
            Add Box
        </button>
    </div>
</template>

<script>
export default {
    name: 'ImageInfo',
    methods: {

        /**
         * Add a new bounding box
         */
        addNewBox ()
        {
            this.$emit('addNewBox');
        }
    }
};
</script>

<style scoped>

</style>
