<template>
    <div class="has-text-left">
        <form
            method="post"
            @submit.prevent="submit"
            @keydown="clearError($event.target.name)"
        >
            <div class="flex">
                <p class="flex-1">Join a cleanup</p>

                <p
                    class="help is-danger"
                    v-if="errorExists('invite_link')"
                    v-text="getFirstError('invite_link')"
                />
            </div>

            <input
                class="input"
                v-model="invite_link"
                name="invite_link"
                required
                :class="errorExists('invite_link') ? 'is-danger' : ''"
                placeholder="Enter invitation code to join a cleanup"
            />

            <div class="flex mt1 jc">
                <button
                    class="button is-info is-medium"
                    :class="processing ? 'is-loading' : ''"
                    :disabled="processing"
                    type="submit"
                >
                    Join Cleanup!
                </button>
            </div>
        </form>
    </div>
</template>

<script>
import handleErrors from "../../mixins/errors/handleErrors";

export default {
    name: "JoinCleanup",
    data () {
        return {
            invite_link: "",
            processing: false
        };
    },
    mixins: [
        handleErrors
    ],
    methods: {
        /**
         * Try to join a Cleanup by invite_link
         */
        async submit ()
        {
            this.processing = true;

            await this.$store.dispatch('JOIN_CLEANUP', {
                link: this.invite_link
            });

            this.processing = false;
        }
    }
}
</script>

<style scoped>

</style>
