<template>
	<div style="padding-left: 1em; padding-right: 1em;">
		<h1 class="title is-4">Change My Privacy</h1>
		<hr>
		<br>
		<div class="columns">
			<div class="column one-third is-offset-1">
				<div class="field">

                    <!-- Maps -->
					<h1 class="title is-4">Maps:</h1>
				    <label class="checkbox">
				    	<input type="checkbox" v-model="maps_name" />
				      	Credit my name
				    </label>
				    <br>
				    <label class="checkbox">
				    	<input type="checkbox" v-model="maps_username" />
				    	Credit my username
				    </label>
				    <br>
				    <br>
				    <h1 class="title is-6" v-show="maps_name" style="margin-bottom: 5px;">
						<strong style="color: green;">
							Your name is set to appear on each of the images you upload to the maps.
						</strong>
					</h1>
					<h1 class="title is-6" v-show="maps_username">
						<strong style="color: green;">
							Your username is set to appear on each of the images you upload to the maps.
						</strong>
					</h1>
					<br v-show="maps_name || maps_username">

					<h1 class="title is-6" v-show="! maps_name && ! maps_username">
						<strong style="color: red;">
							Your name and username will not appear on the maps.
						</strong>
					</h1>

					<!-- Leaderboards -->
					<h1 class="title is-4">Leaderboards:</h1>
				    <label class="checkbox">
				    	<input type="checkbox" v-model="leaderboard_name" />
				      	Credit my name
				    </label>
				    <br>
				    <label class="checkbox">
				    	<input type="checkbox" v-model="leaderboard_username" />
				    	Credit my username
				    </label>
				    <br>
				    <br>
				    <h1 class="title is-6" v-show="leaderboard_name" style="margin-bottom: 5px;">
						<strong style="color: green;">
							Your name is set to appear in any leaderboards you qualify for.
						</strong>
					</h1>
					<h1 class="title is-6" v-show="leaderboard_username">
						<strong style="color: green;">
							Your username is set to appear in any leaderboards you qualify for.
						</strong>
					</h1>
					<br v-show="leaderboard_name || leaderboard_username">

					<h1 class="title is-6"
						v-show="! leaderboard_name && ! leaderboard_username">
						<strong style="color: red;">
							Your name and username will not appear on the Leaderboards.
						</strong>
					</h1>

					<!-- Created By -->
					<h1 class="title is-4">Created By:</h1>
				    <label class="checkbox">
				    	<input type="checkbox" v-model="createdby_name" />
				      	Credit my name
				    </label>
				    <br>
				    <label class="checkbox">
				    	<input type="checkbox" v-model="createdby_username" />
				    	Credit my username
				    </label>
				    <br>
				    <br>
					<h1 class="title is-6" v-show="createdby_name" style="margin-bottom: 5px;">
						<strong style="color: green;">
							Your name is set to appear on any locations you create.
						</strong>
					</h1>
					<h1 class="title is-6" v-show="createdby_username" style="margin-bottom: 5px;">
						<strong style="color: green;">
							Your username is set to appear on any locations you create.
						</strong>
					</h1>
					<br v-show="createdby_name || createdby_username">
					<h1 class="title is-6"
						v-show="! createdby_name && ! createdby_username">
						<strong style="color: red;">
							Your name and username will not appear in the Created By section of any locations you add to the database.
						</strong>
					</h1>
				</div>

                <br>
				<button class="button is-medium is-danger" @click="toggle">Update</button>
			</div>
		</div>
	</div>
</template>

<script>
export default {
    name: 'Privacy',
    computed: {

        /**
         *
         */
        createdby_name: {
            get () {
                return this.user.show_name_createdby;
            },
            set (v) {
                this.$store.commit('show_name_createdby', v);
            }
        },

        /**
         *
         */
        createdby_username: {
            get () {
                return this.user.show_username_createdby;
            },
            set (v) {
                this.$store.commit('show_username_createdby', v);
            }
        },

        /**
         *
         */
        leaderboard_name: {
            get () {
                return this.user.show_name;
            },
            set (v) {
                this.$store.commit('show_name', v);
            }
        },

        /**
         *
         */
        leaderboard_username: {
            get () {
                return this.user.show_username;
            },
            set (v) {
                this.$store.commit('show_username', v);
            }
        },

        /**
         *
         */
        maps_name: {
            get () {
                return this.user.show_name_maps;
            },
            set (v) {
                this.$store.commit('show_name_maps', v);
            }
        },

        /**
         *
         */
        maps_username: {
            get () {
                return this.user.show_username_maps;
            },
            set (v) {
                this.$store.commit('show_username_maps', v);
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
         *
         */
        toggle ()
        {
            console.log('toggle');
            // axios({
            //     method: 'POST',
            //     url: '/en/settings/privacy/update',
            //     data: {
            //         mapsName: this.mapsName,
            //         mapsUsername: this.mapsUsername,
            //         leaderboardsName: this.leaderboardsName,
            //         leaderboardsUsername: this.leaderboardsUsername,
            //         createdByName: this.createdByName,
            //         createdByUsername: this.createdByUsername
            //         // insta: this.vsocialmedia
            //     }
            // })
            // .then(response => {
            //     // console.log(response);
            //     // window.location.href = window.location.href;
            // })
            // .catch(error => {
            //     console.log(error);
            // });
        }
    }
}
</script>
