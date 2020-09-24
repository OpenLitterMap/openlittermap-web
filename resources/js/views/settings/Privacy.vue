<template>
	<div style="padding-left: 1em; padding-right: 1em;">
		<h1 class="title is-4">{{ $t('settings.privacy.privacy1') }}</h1>
		<hr>
		<br>
		<div class="columns">
			<div class="column one-third is-offset-1">
				<div class="field">

                    <!-- Maps -->
					<h1 class="title is-4">{{ $t('settings.privacy.privacy2') }}:</h1>
				    <label class="checkbox">
				    	<input type="checkbox" v-model="maps_name" />
				      	{{ $t('settings.privacy.privacy3') }}
				    </label>
				    <br>
				    <label class="checkbox">
				    	<input type="checkbox" v-model="maps_username" />
				    	{{ $t('settings.privacy.privacy4') }}
				    </label>
				    <br>
				    <br>
				    <h1 class="title is-6" v-show="maps_name" style="margin-bottom: 5px;">
						<strong style="color: green;">
							{{ $t('settings.privacy.privacy5') }}.
						</strong>
					</h1>
					<h1 class="title is-6" v-show="maps_username">
						<strong style="color: green;">
							{{ $t('settings.privacy.privacy6') }}.
						</strong>
					</h1>
					<br v-show="maps_name || maps_username">

					<h1 class="title is-6" v-show="! maps_name && ! maps_username">
						<strong style="color: red;">
							{{ $t('settings.privacy.privacy7') }}.
						</strong>
					</h1>

					<!-- Leaderboards -->
					<h1 class="title is-4">{{ $t('settings.privacy.privacy8') }}:</h1>
				    <label class="checkbox">
				    	<input type="checkbox" v-model="leaderboard_name" />
				      {{ $t('settings.privacy.privacy19') }}
				    </label>
				    <br>
				    <label class="checkbox">
				    	<input type="checkbox" v-model="leaderboard_username" />
				    	{{ $t('settings.privacy.privacy10') }}
				    </label>
				    <br>
				    <br>
				    <h1 class="title is-6" v-show="leaderboard_name" style="margin-bottom: 5px;">
						<strong style="color: green;">
							{{ $t('settings.privacy.privacy11') }}.
						</strong>
					</h1>
					<h1 class="title is-6" v-show="leaderboard_username">
						<strong style="color: green;">
							{{ $t('settings.privacy.privacy12') }}.
						</strong>
					</h1>
					<br v-show="leaderboard_name || leaderboard_username">

					<h1 class="title is-6"
						v-show="! leaderboard_name && ! leaderboard_username">
						<strong style="color: red;">
							{{ $t('settings.privacy.privacy13') }}.
						</strong>
					</h1>

					<!-- Created By -->
					<h1 class="title is-4">{{ $t('settings.privacy.privacy14') }}:</h1>
				    <label class="checkbox">
				    	<input type="checkbox" v-model="createdby_name" />
				      	{{ $t('settings.privacy.privacy3') }}
				    </label>
				    <br>
				    <label class="checkbox">
				    	<input type="checkbox" v-model="createdby_username" />
				    	{{ $t('settings.privacy.privacy4') }}
				    </label>
				    <br>
				    <br>
					<h1 class="title is-6" v-show="createdby_name" style="margin-bottom: 5px;">
						<strong style="color: green;">
							{{ $t('settings.privacy.privacy15') }}
						</strong>
					</h1>
					<h1 class="title is-6" v-show="createdby_username" style="margin-bottom: 5px;">
						<strong style="color: green;">
							{{ $t('settings.privacy.privacy16') }}.
						</strong>
					</h1>
					<br v-show="createdby_name || createdby_username">
					<h1 class="title is-6"
						v-show="! createdby_name && ! createdby_username">
						<strong style="color: red;">
							{{ $t('settings.privacy.privacy17') }}.
						</strong>
					</h1>
				</div>

                <br>
				<button :class="button" :disabled="processing" @click="submit">{{ $t('settings.privacy.privacy18') }}</button>
			</div>
		</div>
	</div>
</template>

<script>
export default {
    name: 'Privacy',
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
         * Show personal name on the createdBy sections of any locations the user added
         */
        createdby_name: {
            get () {
                return this.user.show_name_createdby;
            },
            set (v) {
                this.$store.commit('privacy', {
                    column: 'show_name_createdby',
                    v
                });
            }
        },

        /**
         * Show username on the createdBy sections of any locations the user added
         */
        createdby_username: {
            get () {
                return this.user.show_username_createdby;
            },
            set (v) {
                this.$store.commit('privacy', {
                    column: 'show_username_createdby',
                    v
                });
            }
        },

        /**
         * Show personal name on any leaderboard the user qualifies for
         */
        leaderboard_name: {
            get () {
                return this.user.show_name;
            },
            set (v) {
                this.$store.commit('privacy', {
                    column: 'show_name',
                    v
                });
            }
        },

        /**
         * Show username on any leaderboard the user qualifies for
         */
        leaderboard_username: {
            get () {
                return this.user.show_username;
            },
            set (v) {
                this.$store.commit('privacy', {
                    column: 'show_username',
                    v
                });
            }
        },

        /**
         * Show personal name on any datapoints on any maps the user uploads data to
         */
        maps_name: {
            get () {
                return this.user.show_name_maps;
            },
            set (v) {
                this.$store.commit('privacy', {
                    column: 'show_name_maps',
                    v
                });
            }
        },

        /**
         * Show username on any datapoints on any maps the user uploads data to
         */
        maps_username: {
            get () {
                return this.user.show_username_maps;
            },
            set (v) {
                this.$store.commit('privacy', {
                    column: 'show_username_maps',
                    v
                });
            }
        },

        /**
         * Currently authenticated user
         */
        user ()
        {
            return this.$store.state.user.user;
        }
    },
    methods: {

        /**
         * Dispatch request to save all settings
         */
        async submit ()
        {
            this.processing = true;

            await this.$store.dispatch('SAVE_PRIVACY_SETTINGS');

            this.processing = false;
        }
    }
}
</script>
