<template>
    <div style="padding-left: 1em; padding-right: 1em;">
        <h1 class="title is-4">
            {{ $t('settings.details.change-details') }}
        </h1>
        <hr>
        <br>
        <div class="columns">
            <div class="column is-one-third is-offset-1">
                <div class="has-text-centered">
                    <img :src="avatar" alt="user-avatar" width="100" height="100">
                    <input ref="userAvatar" type="file" hidden @change="handleAvatarChange">
                    <i class="fa fa-edit" @click="chooseAvatar" />
                </div>
                <form @submit.prevent="submit" @keydown="clearError($event.target.name)">
                    <!-- The users name -->
                    <label for="name">{{ $t('settings.details.your-name') }}</label>

                    <span
                        v-if="errorExists('name')"
                        class="error"
                        v-text="getFirstError('name')"
                    />

                    <div class="field">
                        <div class="control has-icons-left">
                            <input
                                id="name"
                                v-model="name"
                                type="text"
                                name="name"
                                class="input"
                                :placeholder="name"
                                required
                            >
                            <span class="icon is-small is-left">
                                <i class="fa fa-user" />
                            </span>
                        </div>
                    </div>

                    <!-- The users username-->
                    <label for="username">{{ $t('settings.details.unique-id') }}</label>

                    <span
                        v-if="errorExists('username')"
                        class="error"
                        v-text="getFirstError('username')"
                    />

                    <div class="field">
                        <div class="control has-icons-left">
                            <input
                                id="username"
                                v-model="username"
                                type="text"
                                name="username"
                                class="input"
                                :placeholder="username"
                                required
                            >
                            <span class="icon is-small is-left">
                                @
                            </span>
                        </div>
                    </div>

                    <!-- The users email -->
                    <label for="email">{{ $t('settings.details.email') }}</label>

                    <span
                        v-if="errorExists('email')"
                        class="error"
                        v-text="getFirstError('email')"
                    />

                    <div class="field mb2">
                        <div class="control has-icons-left">
                            <input
                                id="email"
                                v-model="email"
                                type="email"
                                name="email"
                                class="input"
                                :placeholder="email"
                                required
                            >
                            <span class="icon is-small is-left">
                                <i class="fa fa-envelope" />
                            </span>
                        </div>
                    </div>

                    <button :class="button" :disabled="processing">
                        {{ $t('settings.details.update-details') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'Details',
    data ()
    {
        return {
            btn: 'button is-medium is-info',
            processing: false
        };
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
         * The users email address
         */
        email: {
            get () {
                return this.user.email;
            },
            set (v) {
                this.$store.commit('changeUserEmail', v);
            }
        },

        /**
         * Errors object created from failed request
         */
        errors ()
        {
            return this.$store.state.user.errors;
        },

        /**
         * The users name
         */
        name: {
            get () {
                return this.user.name;
            },
            set (v) {
                this.$store.commit('changeUserName', v);
            }
        },
        avatar: {
            get () {
                return this.user.avatar;
            }
        },

        /**
         * The currently authenticated user
         */
        user ()
        {
            return this.$store.state.user.user;
        },

        /**
         * The users username
         */
        username: {
            get () {
                return this.user.username;
            },
            set (v) {
                this.$store.commit('changeUserUsername', v);
            }
        }
    },
    methods: {

        /**
         * Clear an error with this key
         */
        clearError (key)
        {
            if (this.errors[key]) this.$store.commit('deleteUserError', key);
        },

        /**
         * Get the first error from errors object
         */
        getFirstError (key)
        {
            return this.errors[key][0];
        },

        /**
         * Check if any errors exist for this key
         */
        errorExists (key)
        {
            return this.errors.hasOwnProperty(key);
        },
        chooseAvatar ()
        {
            const inputFile = this.$refs.userAvatar;
            inputFile.click();
        },
        async handleAvatarChange (e) {
            const uploadAvatar = e.target.files[0];

            if(uploadAvatar){
                // this.avatar = URL.createObjectURL(uploadAvatar);
                const formData = new FormData();

                formData.append('avatar', uploadAvatar);
                this.processing = true;

                await this.$store.dispatch('UPDATE_AVATAR', formData);

                this.processing = false;

                await this.$store.dispatch('GET_CURRENT_USER');
            }
        },
        /**
         * Update the users personal details (Name, Username, Email)
         */
        async submit ()
        {
            this.processing = true;

            await this.$store.dispatch('UPDATE_DETAILS');

            this.processing = false;
        }
    }
};
</script>
