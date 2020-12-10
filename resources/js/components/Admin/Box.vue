<template>
    <div class="box-wrapper" @mousedown="function(e) { selectBox(e) }" @mouseup="dragEnd()">

        <div :class="selected ? 'box selected-box' : 'box'"
             :style="{top: this.geom[0] + 'px', left: this.geom[1] + 'px', width: this.geom[2] + 'px', height: this.geom[3] + 'px'}">

            <div class="inner-box">
                <div v-show="selected" class="node" :style="{top: -6 + 'px', left: (0.5 * this.geom[2] - 6) + 'px'}" @mousedown="function(e) { selectNode(e, 0) }" />
                <div v-show="selected" class="node" :style="{top: (0.5 * this.geom[3] - 6) + 'px', left: -6 + 'px'}" @mousedown="function(e) { selectNode(e, 1) }"/>
                <div v-show="selected" class="node" :style="{left: (0.5 * this.geom[2] - 6) + 'px', bottom: -6 + 'px'}" @mousedown="function(e) { selectNode(e, 2) }"/>
                <div v-show="selected" class="node" :style="{top: (0.5 * this.geom[3] - 6) + 'px', right: -6 + 'px'}" @mousedown="function(e) { selectNode(e, 3) }"/>
            </div>

        </div>
    </div>
</template>

<script>
    export default {
        name: 'Box',
        props: [
            'index',
            'selected',
            'activeTop',
            'activeLeft',
            'activeBottom',
            'activeRight',
            'geom'
        ],
        methods: {

            /**
             * A node has been de-selected
             */
            deselectNode (node)
            {
                this.$emit('deselectNode', node, this.index);
            },

            /**
             * This box (index) has been selected
             * @emit selected event to parent
             */
            selectBox (e)
            {
                this.$emit('select', this.index, e.pageX, e.pageY);
            },

            /**
             * Select a node (Top, left, bottom, right)
             */
            selectNode (e, nodeIndex)
            {
                this.$emit('activate', this.index, nodeIndex, e.pageX, e.pageY);
            },

            /**
             * Select a node (Top, left, bottom, right)
             */
            dragEnd()
            {
                this.$emit('dragEnd');
            }
        }
    }
</script>

<style lang="scss" scoped>

    .box {
        position: absolute;
        border: 2px #90ee90 solid;
        background-color: transparent;

        &:hover, &.active {
            background-color: rgba(144, 238, 144, .2);
        }

        z-index: 3;
        
        &.selected-box {
            background-color: rgba(255,0,0,0.3);
            padding: 0;
        }
    }

    .inner-box {
        position: relative;
        height: 100%;
        width: 100%;
    }

    .node {
        position: absolute;
        height: 10px;
        width: 10px;
        background-color: #90ee90;
        cursor: grab;
    }

</style>