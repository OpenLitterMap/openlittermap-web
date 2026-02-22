<template>
    <section class="hero is-info is-fullheight">
        <div class="columns centered">
            <div class="column"></div>
            <div class="column is-half-tablet is-one-third-desktop is-one-quarter-fullhd">
                <p class="title is-1 has-text-centered">Reset Your Password</p>
                <div class="panel-body">
                    <form class="form-horizontal" role="form" @submit.prevent="submit">
                        <div class="field with-x-spacing">
                            <label class="label has-text-white" for="email">{{ $t('settings.details.email') }}</label>

                            <div class="control has-icons-left" :class="processing ? 'is-loading' : ''">
                                <input
                                    id="email"
                                    type="email"
                                    class="input"
                                    :class="emailErrors ? 'is-danger' : ''"
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

                                <p v-if="emailErrors"
                                   class="help has-text-white has-text-weight-bold"
                                >{{ emailErrors }}</p>
                            </div>
                        </div>

                        <div class="field with-x-spacing">
                            <label class="label has-text-white" for="password">{{ $t('settings.password.enter-new-password') }}</label>

                            <div class="control has-icons-left has-icons-right" :class="processing ? 'is-loading' : ''">
                                <input
                                    id="password"
                                    :type="isPasswordVisible ? 'text' : 'password'"
                                    class="input"
                                    :class="passwordErrors ? 'is-danger' : ''"
                                    name="password"
                                    v-model="password"
                                    @input="clearErrors"
                                    required
                                    placeholder="********"
                                />
                                <span class="icon is-small is-left">
                                  <i class="fa fa-lock"></i>
                                </span>
                                <span
                                    class="icon is-small is-right cursor-pointer"
                                    style="pointer-events: all;"
                                    @click="isPasswordVisible = !isPasswordVisible"
                                >
                                  <i class="fa" :class="isPasswordVisible ? 'fa-eye' : 'fa-eye-slash'"></i>
                                </span>

                                <p v-if="passwordErrors"
                                   class="help has-text-white has-text-weight-bold"
                                >{{ passwordErrors }}</p>
                            </div>
                        </div>

                        <div class="field with-x-spacing">
                            <label class="label has-text-white" for="password_conf">{{ $t('settings.password.confirm-new-password') }}</label>

                            <div class="control has-icons-left has-icons-right" :class="processing ? 'is-loading' : ''">
                                <input
                                    id="password_conf"
                                    :type="isPasswordConfirmationVisible ? 'text' : 'password'"
                                    class="input"
                                    :class="passwordConfirmationErrors ? 'is-danger' : ''"
                                    name="password_conf"
                                    v-model="passwordConfirmation"
                                    @input="clearErrors"
                                    required
                                    placeholder="********"
                                />
                                <span class="icon is-small is-left">
                                  <i class="fa fa-lock"></i>
                                </span>
                                <div
                                    class="icon is-small is-right cursor-pointer"
                                    style="pointer-events: all;"
                                    @click="isPasswordConfirmationVisible = !isPasswordConfirmationVisible"
                                >
                                  <i class="fa" :class="isPasswordConfirmationVisible ? 'fa-eye' : 'fa-eye-slash'"></i>
                                </div>

                                <p v-if="passwordConfirmationErrors"
                                   class="help has-text-white has-text-weight-bold"
                                >{{ passwordConfirmationErrors }}</p>
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
                                    Reset Password
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
    props: [
        'token'
    ],
    data () {
        return {
            email: this.$route.query.email,
            password: '',
            passwordConfirmation: '',
            processing: false,
            isPasswordVisible: false,
            isPasswordConfirmationVisible: false
        };
    },
    computed: {
        /**
         * Has errors from user
         */
        errors () {
            return this.$store.state.user.errors;
        },

        /**
         * Get email errors
         */
        emailErrors () {
            return this.errors.email ? this.errors.email[0] : null;
        },

        /**
         * Get password errors
         */
        passwordErrors () {
            return this.errors.password ? this.errors.password[0] : null;
        },

        /**
         * Get password confirmation errors
         */
        passwordConfirmationErrors () {
            return this.errors.password_confirmation ? this.errors.password_confirmation[0] : null;
        },
    },
    methods: {
        /**
         * Dispatch request to reset password
         */
        async submit ()
        {
            if (this.password !== this.passwordConfirmation)
            {
                let withNewError = {
                    ...this.errors,
                    password: ['The password confirmation does not match.']
                };

                this.$store.commit('errors', withNewError);
                
                return;
            }

            this.processing = true;

            await this.$store.dispatch('RESET_PASSWORD', {
                email: this.email,
                password: this.password,
                password_confirmation: this.passwordConfirmation,
                token: this.token
            });

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
.cursor-pointer {
    cursor: pointer;
}
.cursor-pointer:hover {
    color: black;
}
</style>
