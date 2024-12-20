<template name="anonymous-presence">
    <div v-show="showEmailConfirmed" id="emaildiv" class="notification is-success">
        <div class="welcome-msg">{{ $t('home.welcome.verified') }}</div>
        <div>
            <span class="icon is-small mt-1" @click="hideEmailConfirmedBanner">
                <a class="delete" />
            </span>
        </div>
    </div>
</template>

<script>
export default {
    props: ['showEmailConfirmed'], // todo - fix this.
    methods: {
        /**
         * Delete the welcome div when a user verifies their email address
         *
         * todo - animate the close with a transition
         */
        hideEmailConfirmedBanner ()
        {
            this.showEmailConfirmed = false;
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
