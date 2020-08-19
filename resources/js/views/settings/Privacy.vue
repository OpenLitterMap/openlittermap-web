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
				    	<input type="checkbox" v-model="mapsName" />
				      	Credit my name
				    </label>
				    <br>
				    <label class="checkbox">
				    	<input type="checkbox" v-model="mapsUsername" />
				    	Credit my username
				    </label>
				    <br>
				    <br>
				    <h1 class="title is-6" 
				    	v-show="this.user.show_name_maps == 1"
				    	style="margin-bottom: 5px;">
						<strong style="color: green;">
							Your name is set to appear on each of the images you upload to the maps.
						</strong>
					</h1>
					<h1 class="title is-6" 
						v-show="this.user.show_username_maps == 1">
						<strong style="color: green;">
							Your username is set to appear on each of the images you upload to the maps.
						</strong>
					</h1>
					<br v-show="this.user.show_name_maps || this.user.show_username_maps">

					<h1 class="title is-6"
						v-show="this.user.show_name_maps == 0 &&this.user.show_username_maps == 0">
						<strong style="color: red;">
							Your name and username will not appear on the maps.
						</strong>
					</h1>

					<!-- Leaderboards -->
					<h1 class="title is-4">Leaderboards:</h1>
				    <label class="checkbox">
				    	<input type="checkbox" v-model="leaderboardsName" />
				      	Credit my name
				    </label>
				    <br>
				    <label class="checkbox">
				    	<input type="checkbox" v-model="leaderboardsUsername" />
				    	Credit my username
				    </label>
				    <br>
				    <br>
				    <h1 class="title is-6" 
				    	v-show="this.user.show_name == 1"
				    	style="margin-bottom: 5px;">
						<strong style="color: green;">
							Your name is set to appear in any leaderboards you qualify for.
						</strong>
					</h1>
					<h1 class="title is-6" 
						v-show="this.user.show_username == 1">
						<strong style="color: green;">
							Your username is set to appear in any leaderboards you qualify for.
						</strong>
					</h1>
					<br v-show="this.user.show_name || this.user.show_username">

					<h1 class="title is-6"
						v-show="this.user.show_name == 0 &&this.user.show_username == 0">
						<strong style="color: red;">
							Your name and username will not appear on the Leaderboards.
						</strong>
					</h1>

					<!-- Created By -->
					<h1 class="title is-4">Created By:</h1>
				    <label class="checkbox">
				    	<input type="checkbox" v-model="createdByName" />
				      	Credit my name
				    </label>
				    <br>
				    <label class="checkbox">
				    	<input type="checkbox" v-model="createdByUsername" />
				    	Credit my username
				    </label>
				    <br>
				    <br>
					<h1 class="title is-6" 
				    	v-show="this.user.show_name_createdby == 1"
				    	style="margin-bottom: 5px;">
						<strong style="color: green;">
							Your name is set to appear on any locations you create.
						</strong>
					</h1>
					<h1 class="title is-6" 
						v-show="this.user.show_username_createdby == 1"
						style="margin-bottom: 5px;">
						<strong style="color: green;">
							Your username is set to appear on any locations you create.
						</strong>
					</h1>
					<br v-show="this.user.show_name_createdby || this.user.show_username_createdby">
					<h1 class="title is-6"
						v-show="this.user.show_name_createdby == 0 &&this.user.show_username_createdby == 0">
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
		props: ['user'],
		created() {
			// this.currentUser = JSON.parse(this.user);

			// Maps
			if (this.user.show_name_maps) {
				this.mapsName = true;
			} else {
				this.mapsName = false;
			}
			if (this.user.show_username_maps) {
				this.mapsUsername = true;
			} else {
				this.mapsUsername = false;
			}

			// Leaderboards
			if (this.user.show_name) {
				this.leaderboardsName = true;
			} else {
				this.leaderboardsName = false;
			}
			if (this.user.show_username) {
				this.leaderboardsUsername = true
			} else {
				this.leaderboardsUsername = false;
			}

			// Created By
			if (this.user.show_name_createdby) {
				this.createdByName = true;
			} else {
				this.createdByName = false;
			}
			if (this.user.show_username_createdby) {
				this.createdByUsername = true;
			} else {
				this.createdByUsername = false;
			}
		},
		data() {
			return {
				// currentUser: null,
				// Maps
				mapsName: null,
				mapsUsername: null,
				// Created by
				createdByName: null,
				createdByUsername: null,
				// Leaderboards
				leaderboardsName: null,
				leaderboardsUsername: null,
			}
		},
		methods: {
			toggle() {
				axios({
					method: 'POST',
					url: '/en/settings/privacy/update',
					data: {
						mapsName: this.mapsName,
						mapsUsername: this.mapsUsername,
						leaderboardsName: this.leaderboardsName,
						leaderboardsUsername: this.leaderboardsUsername,
						createdByName: this.createdByName,
						createdByUsername: this.createdByUsername
						// insta: this.vsocialmedia
					}
				})
				.then(response => {
					// console.log(response);
					window.location.href = window.location.href;
				})
				.catch(error => {
					console.log(error);
				});
			}
		}
	}
</script>