<template>
    <div class="is-flex">
        <p class="mr-2"><strong>{{ $t('tags.picked-up-title') }}</strong></p>
        <label class="switch">
            <input type="checkbox" :checked="pickedUp" @change="toggle">
            <span class="slider round"></span>
        </label>
    </div>
</template>

<script>
export default {
    name: 'Presence',
    computed: {
        /**
         * Change setting name to "picked_up"
         */
        pickedUp ()
        {
            return this.$store.state.litter.pickedUp;
        }
    },
    methods: {
        /**
         * Toggle the presence of the litter
         */
        toggle ()
        {
            this.$store.commit('togglePickedUp');
        }
    }
}
</script>

<style lang="scss" scoped>
.switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 25px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #f14668;
    -webkit-transition: .4s;
    transition: .4s;
    border-radius: 34px;
}

.slider:before {
    position: absolute;
    content: "";
    border-radius: 50%;
    height: 18px;
    width: 18px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    -webkit-transition: .4s;
    transition: .4s;
}

input:checked + .slider {
    background-color: #00d1b2;
}

input:focus + .slider {
    box-shadow: 0 0 1px #00d1b2;
}

input:checked + .slider:before {
    -webkit-transform: translateX(24px);
    -ms-transform: translateX(24px);
    transform: translateX(24px);
}
</style>
