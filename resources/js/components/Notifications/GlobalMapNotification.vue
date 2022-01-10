<template>
    <div
        class="event"
        :class="'event-' + color"
        @click="$emit('click', $event)"
    >
        <div class="event-image">
            <slot name="image"></slot>
        </div>
        <div class="event-content">
            <slot name="content"></slot>
        </div>
    </div>
</template>

<script>
export default {
    name: 'GlobalMapNotification',
    props: {
        color: {
            type: String,
            default: 'green'
        }
    }
};
</script>

<style lang="scss" scoped>

    .event {
        border-radius: 8px;
        margin-bottom: 10px;
        padding-right: 16px;
        display: flex;
        cursor: pointer;
    }

    .event-green {
        background-color: #88d267;
    }

    .event-blue {
        background-color: #4bb0e0;
    }

    .event-yellow {
        background-color: #f1c40f;
    }

    .event-pink {
        background-color: #e256fff0;
    }

    .event-image {
        padding: 16px;
        margin: auto 0;
    }

    .event-content {
        padding-top: 10px;
        padding-bottom: 10px;
    }

    @media (max-width: 1024px) {
        .event {
            padding-right: 12px;
        }
        .event-image {
            padding: 12px;
            margin: auto 0;
        }
        .event-content {
            padding-top: 8px;
            padding-bottom: 8px;
        }
    }

</style>
