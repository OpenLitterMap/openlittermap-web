<template>
    <div class="box-wrapper" @click="selectBox">

        <div :class="selected ? 'box selected-box' : 'box'"
             :style="{top: this.top + 'px', left: this.left + 'px', width: this.width + 'px', height: this.height + 'px'}">

            <div class="inner-box">
                <div v-show="selected" class="top-node" @mousedown.self="selectNode('activeTop')" @mousemove="dragTop" @mouseup="deselectNode('activeTop')" />
                <div v-show="selected" class="left-node" />
                <div v-show="selected" class="bottom-node" />
                <div v-show="selected" class="right-node" />
            </div>

        </div>
    </div>
</template>

<script>
    export default {
        name: 'Box',
        props: [
            'top',
            'left',
            'width',
            'height',
            'index',
            'selected',
            'activeTop',
            'activeLeft',
            'activeBottom',
            'activeRight'
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
             * A node is being repositioned
             * Should only fire when this index is selected + this node is active
             */
            dragTop (e)
            {
                if (this.selected && this.activeTop)
                {
                    // console.log('drag', e.offsetY - this.top);
                    this.$emit('repositionTop', e.offsetY, this.index);
                }
            },

            /**
             * This box (index) has been selected
             * @emit selected event to parent
             */
            selectBox ()
            {
                this.$emit('select');
            },

            /**
             * Select a node (Top, left, bottom, right)
             */
            selectNode (node)
            {
                this.$emit('activate', node, this.index);
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

    .top-node {
        position: absolute;
        top: -6px;
        left: 50%;
        height: 10px;
        width: 10px;
        background-color: #90ee90;
        cursor: grab;
    }

    .left-node {
        position: absolute;
        top: 50%;
        left: -6px;
        height: 10px;
        width: 10px;
        background-color: #90ee90;
        cursor: grab;
    }

    .bottom-node {
        position: absolute;
        bottom: -6px;
        left: 50%;
        height: 10px;
        width: 10px;
        background-color: #90ee90;
        cursor: grab;
    }

    .right-node {
        position: absolute;
        top: 50%;
        right: -6px;
        height: 10px;
        width: 10px;
        background-color: #90ee90;
    }
</style>