<template>
    <div v-show="showUnsubscribed" id="emaildiv" class="notification is-success">
        <div class="welcome-msg">You have unsubscribed from the good news! Hope you check back again soon.</div>
        <div>
            <span class="icon is-small mt-1" @click="hide">
                <a class="delete" />
            </span>
        </div>
    </div>
</template>

<script>
export default {
    name: 'Unsubscribed',
    props: ['showUnsubscribed'], // todo, this this.
    methods: {

        /**
         * Delete the welcome div when a user verifies their email address
         *
         * todo - animate the close with a transition
         */
        hide ()
        {
            this.showUnsubscribed = false;
        }
    }
}
</script>

<style scoped>

    #emaildiv {
        display: flex;
        justify-content: center;
        margin-bottom: -20px;
        z-index: 999;
        text-align: center;
    }

    .welcome-msg {
        margin-right: 10px;
    }
</style>
