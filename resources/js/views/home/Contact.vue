<template>
    <section class="hero is-info is-fullheight">
        <div class="columns centered">
            <div class="column" />
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
                                  <i class="fa fa-user"></i>
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
                                  <i class="fa fa-envelope"></i>
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
                                  <i class="fa fa-info"></i>
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
            <div class="column" />
        </div>
    </section>
</template>

<script>
export default {
    name: 'Contact',
    data () {
        return {
            name: '',
            email: '',
            subject: '',
            message: '',
            processing: false
        };
    },
    computed: {
        errors () {
            return this.$store.state.user.errors;
        }
    },
    methods: {
        async submit () {
            this.processing = true;

            await this.$store.dispatch('CONTACT_US', {
                name: this.name,
                email: this.email,
                subject: this.subject,
                message: this.message
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
        }
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
</style>
