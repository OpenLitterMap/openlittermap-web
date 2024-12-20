<template>
    <section class="hero is-info is-fullheight">
        <div class="columns centered">
            <div class="column"></div>
            <div class="column is-half-tablet is-one-third-desktop is-one-quarter-fullhd">
                <p class="title is-1 has-text-centered">Reset Password</p>
                <div class="panel-body">

                    <form class="form-horizontal" role="form" @submit.prevent="submit">
                        <div class="field with-x-spacing">
                            <label class="label has-text-white" for="email">E-Mail Address</label>

                            <div class="control has-icons-left" :class="processing ? 'is-loading' : ''">
                                <input
                                    id="email"
                                    type="email"
                                    class="input"
                                    :class="validationErrors ? 'is-danger' : ''"
                                    name="email"
                                    v-model="email"
                                    @input="clearErrors"
                                    required
                                    autofocus
                                    placeholder="you@email.com"
                                />
                                <span class="icon is-small is-left">
                                  <i class="fa fa-envelope"></i>
                                </span>

                                <p v-if="validationErrors"
                                   class="help has-text-white has-text-weight-bold"
                                >{{ validationErrors }}</p>
                            </div>
                        </div>

                        <div class="field has-text-centered">
                            <div class="control">
                                <button
                                    type="submit"
                                    class="button is-primary"
                                    :class="processing ? 'is-loading' : ''"
                                    :disabled="processing"
                                >
                                    Send Password Reset Link
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="column"></div>
        </div>
    </section>
</template>

<script>
export default {
    name: 'Email',
    data () {
        return {
            email: '',
            processing: false
        };
    },
    computed: {
        /**
         * Has errors from user
         */
        validationErrors () {
            let errors = this.$store.state.user.errors;
            return errors && errors.email
                ? errors.email[0]
                : null;
        },
    },
    methods: {
        async submit () {
            this.processing = true;

            await this.$store.dispatch('SEND_PASSWORD_RESET_LINK', this.email);

            this.processing = false;
        },

        clearErrors () {
            this.$store.commit('errors', []);
        }
    }
};
</script>

<style scoped>
.centered {
    width: 100%;
    margin: 12rem auto;
}

.with-x-spacing {
    padding-right: 24px;
    padding-left: 24px;
}
</style>
