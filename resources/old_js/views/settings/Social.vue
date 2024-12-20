<template>
    <div style="padding-left: 1em; padding-right: 1em;">
        <h1 class="title is-4">{{ $t('settings.common.social') }}</h1>
        <hr>
        <p>{{ $t('settings.social.description') }}</p>
        <br>
        <div class="columns">
            <div class="column is-half">

                <form @submit.prevent="submit">

                    <label for="twitter">Twitter</label>
                    <div class="field">
                        <div class="control has-icons-left">
                            <input
                                type="text"
                                name="twitter"
                                id="twitter"
                                class="input"
                                placeholder="Twitter URL"
                                v-model="twitter"
                            />
                            <span class="icon is-small is-left"><i class="fa fa-twitter"/></span>
                        </div>
                        <p
                            class="help is-danger is-size-6"
                            v-if="getFirstError('social_twitter')"
                            v-text="getFirstError('social_twitter')"
                        />
                    </div>

                    <label for="facebook">Facebook</label>
                    <div class="field">
                        <div class="control has-icons-left">
                            <input
                                type="text"
                                name="facebook"
                                id="facebook"
                                class="input"
                                placeholder="Facebook URL"
                                v-model="facebook"
                            />
                            <span class="icon is-small is-left"><i class="fa fa-facebook"/></span>
                        </div>
                        <p
                            class="help is-danger is-size-6"
                            v-if="getFirstError('social_facebook')"
                            v-text="getFirstError('social_facebook')"
                        />
                    </div>

                    <label for="instagram">Instagram</label>
                    <div class="field">
                        <div class="control has-icons-left">
                            <input
                                type="text"
                                name="instagram"
                                id="instagram"
                                class="input"
                                placeholder="Instagram URL"
                                v-model="instagram"
                            />
                            <span class="icon is-small is-left"><i class="fa fa-instagram"/></span>
                        </div>
                        <p
                            class="help is-danger is-size-6"
                            v-if="getFirstError('social_instagram')"
                            v-text="getFirstError('social_instagram')"
                        />
                    </div>

                    <label for="linkedin">LinkedIn</label>
                    <div class="field">
                        <div class="control has-icons-left">
                            <input
                                type="text"
                                name="linkedin"
                                id="linkedin"
                                class="input"
                                placeholder="LinkedIn URL"
                                v-model="linkedin"
                            />
                            <span class="icon is-small is-left"><i class="fa fa-linkedin"/></span>
                        </div>
                        <p
                            class="help is-danger is-size-6"
                            v-if="getFirstError('social_linkedin')"
                            v-text="getFirstError('social_linkedin')"
                        />
                    </div>

                    <label for="reddit">Reddit</label>
                    <div class="field">
                        <div class="control has-icons-left">
                            <input
                                type="text"
                                name="reddit"
                                id="reddit"
                                class="input"
                                placeholder="Reddit URL"
                                v-model="reddit"
                            />
                            <span class="icon is-small is-left"><i class="fa fa-reddit"/></span>
                        </div>
                        <p
                            class="help is-danger is-size-6"
                            v-if="getFirstError('social_reddit')"
                            v-text="getFirstError('social_reddit')"
                        />
                    </div>


                    <label for="personal">{{ $t('settings.social.personal-website') }}</label>
                    <div class="field">
                        <div class="control has-icons-left">
                            <input
                                type="text"
                                name="personal"
                                id="personal"
                                class="input"
                                :placeholder="$t('settings.social.personal-website-url')"
                                v-model="personal"
                            />
                            <span class="icon is-small is-left"><i class="fa fa-link"/></span>
                        </div>
                        <p
                            class="help is-danger is-size-6"
                            v-if="getFirstError('social_personal')"
                            v-text="getFirstError('social_personal')"
                        />
                    </div>

                    <button :class="button" :disabled="processing">{{ $t('common.submit') }}</button>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'Social',
    data ()
    {
        return {
            btn: 'button is-medium is-info',
            processing: false,
            twitter: null,
            facebook: null,
            instagram: null,
            linkedin: null,
            reddit: null,
            personal: null,
        };
    },
    mounted ()
    {
        this.$store.commit('errors', {});

        this.twitter = this.user.settings?.social_twitter;
        this.facebook = this.user.settings?.social_facebook;
        this.instagram = this.user.settings?.social_instagram;
        this.linkedin = this.user.settings?.social_linkedin;
        this.reddit = this.user.settings?.social_reddit;
        this.personal = this.user.settings?.social_personal;
    },
    computed: {

        /**
         * Add ' is-loading' when processing
         */
        button ()
        {
            return this.processing ? this.btn + ' is-loading' : this.btn;
        },

        /**
         * Errors object created from failed request
         */
        errors ()
        {
            return this.$store.state.user.errors;
        },

        /**
         * The currently authenticated user
         */
        user ()
        {
            return this.$store.state.user.user;
        },
    },
    methods: {
        /**
         * Get the first error from errors object
         */
        getFirstError (key)
        {
            return this.errors.hasOwnProperty(key)
                ? this.errors[key][0]
                : null;
        },

        /**
         * Update the users social media account links
         */
        async submit ()
        {
            this.processing = true;

            await this.$store.dispatch('UPDATE_SETTINGS', {
                social_twitter: this.twitter,
                social_facebook: this.facebook,
                social_instagram: this.instagram,
                social_linkedin: this.linkedin,
                social_reddit: this.reddit,
                social_personal: this.personal,
            });

            this.processing = false;
        }
    }
};
</script>
