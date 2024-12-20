<template>
    <section class="hero is-info is-fullheight">
        <div class="columns centered">
            <div class="column"/>
            <div class="column is-half-tablet is-one-third-desktop is-one-quarter-fullhd">
                <p class="title is-1 has-text-centered">Contact Us</p>
                <div class="panel-body">

                    <form class="form-horizontal"
                          role="form"
                          @submit.prevent="submit"
                          @keydown="clearErrors($event.target.name)"
                    >

                        <div class="field with-x-spacing">
                            <label class="label has-text-white" for="to">To</label>

                            <div class="control">
                                <input
                                    type="text"
                                    class="input"
                                    value="info@openlittermap.com"
                                    readonly
                                />
                            </div>
                        </div>

                        <div class="field with-x-spacing">
                            <label class="label has-text-white" for="name">Your Name (optional)</label>

                            <div class="control has-icons-left" :class="processing ? 'is-loading' : ''">
                                <input
                                    id="name"
                                    type="text"
                                    class="input"
                                    :class="hasError('name') ? 'is-danger' : ''"
                                    name="name"
                                    v-model="name"
                                    autofocus
                                />
                                <span class="icon is-small is-left">
                                  <i class="fa fa-user" />
                                </span>

                                <p v-if="hasError('name')"
                                   class="help has-text-white has-text-weight-bold"
                                >{{ getError('name') }}</p>
                            </div>
                        </div>

                        <div class="field with-x-spacing">
                            <label class="label has-text-white" for="email">Your Email</label>

                            <div class="control has-icons-left" :class="processing ? 'is-loading' : ''">
                                <input
                                    id="email"
                                    type="email"
                                    class="input"
                                    :class="hasError('email') ? 'is-danger' : ''"
                                    name="email"
                                    v-model="email"
                                    required
                                    placeholder="you@email.com"
                                />
                                <span class="icon is-small is-left">
                                  <i class="fa fa-envelope" />
                                </span>

                                <p v-if="hasError('email')"
                                   class="help has-text-white has-text-weight-bold"
                                >{{ getError('email') }}</p>
                            </div>
                        </div>

                        <div class="field with-x-spacing">
                            <label class="label has-text-white" for="subject">Subject</label>

                            <div class="control has-icons-left" :class="processing ? 'is-loading' : ''">
                                <input
                                    id="subject"
                                    type="text"
                                    class="input"
                                    :class="hasError('subject') ? 'is-danger' : ''"
                                    name="subject"
                                    v-model="subject"
                                    required
                                />
                                <span class="icon is-small is-left">
                                  <i class="fa fa-info" />
                                </span>

                                <p v-if="hasError('subject')"
                                   class="help has-text-white has-text-weight-bold"
                                >{{ getError('subject') }}</p>
                            </div>
                        </div>

                        <div class="field with-x-spacing">
                            <label class="label has-text-white" for="message">Message</label>

                            <div class="control has-icons-left" :class="processing ? 'is-loading' : ''">
                                <textarea
                                    id="message"
                                    class="textarea"
                                    :class="hasError('message') ? 'is-danger' : ''"
                                    v-model="message"
                                    required
                                />

                                <p v-if="hasError('message')"
                                   class="help has-text-white has-text-weight-bold"
                                >{{ getError('message') }}</p>
                            </div>
                        </div>

                        <div class="field with-x-spacing">
                            <div class="control recaptcha">
                                <vue-recaptcha
                                    :sitekey="computedKey"
                                    v-model="g_recaptcha_response"
                                    :loadRecaptchaScript="true"
                                    @verify="recaptcha"
                                />
                                <p v-if="hasError('g-recaptcha-response')"
                                   class="help has-text-white has-text-weight-bold"
                                >{{ getError('g-recaptcha-response') }}</p>
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
                                    Send Email
                                </button>
                            </div>
                        </div>
                    </form>

                </div>
            </div>
            <div class="column"/>
        </div>
    </section>
</template>

<script>
import VueRecaptcha from 'vue-recaptcha';

export default {
    name: 'ContactUs',
    components: {VueRecaptcha},
    data () {
        return {
            name: '',
            email: '',
            subject: '',
            message: '',
            g_recaptcha_response: '',
            processing: false
        };
    },
    computed: {
        errors () {
            return this.$store.state.user.errors;
        },

        /**
         * Key to return for google-recaptcha
         */
        computedKey () {
            return process.env.MIX_GOOGLE_RECAPTCHA_KEY;
        },
    },
    methods: {
        async submit () {
            this.processing = true;

            await this.$store.dispatch('SEND_EMAIL_TO_US', {
                name: this.name,
                email: this.email,
                subject: this.subject,
                message: this.message,
                "g-recaptcha-response": this.g_recaptcha_response
            });

            this.processing = false;
        },

        clearErrors (error) {
            this.$store.commit('deleteUserError', error);
        },

        hasError (key) {
            return this.errors.hasOwnProperty(key);
        },

        getError (key) {
            return this.errors[key][0];
        },

        /**
         * Google re-captcha has been verified
         */
        recaptcha (response) {
            this.g_recaptcha_response = response;
        },
    }
};
</script>

<style scoped>
    .centered {
        width: 100%;
        margin: 6rem auto;
    }

    /* Mobile view */
    @media (max-width: 768px) {
        .centered {
            margin: 2rem auto;
        }
    }

    .with-x-spacing {
        padding-right: 24px;
        padding-left: 24px;
    }

    .recaptcha {
        display: flex;
        flex-direction: column;
        align-items: center;
    }
</style>
