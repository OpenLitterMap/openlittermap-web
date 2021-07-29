<template>
	<div class="container mt3">

		<loading v-if="loading" :active.sync="loading" :is-full-page="true" />

	    <div v-else>

            <!-- Todo , add extra loaded statement here -->
	    	<div v-if="this.photosAwaitingVerification === 0 && this.photosNotProcessed === 0">
	    		<p class="title is-3">All done.</p>
	    	</div>

	    	<div v-else>

				<h1 class="title is-2 has-text-centered" style="margin-bottom: 1em;">
					#{{ this.photo.id }} Uploaded {{ this.uploadedTime }}
				</h1>
			  	<p class="subtitle is-5 has-text-centered" style="margin-bottom: 4em;">
			  		{{ this.photo.display_name }}
			  	</p>
				<!-- todo - verification bar -->

				<div class="columns">

				  	<!-- Left - remaining verification & other actions -->
				  	<div class="column has-text-centered">
						<p class="subtitle is-5">Uploaded, not tagged: {{ this.photosNotProcessed }}</p>
						<p class="subtitle is-5">Tagged, awaiting verification: {{ this.photosAwaitingVerification }}</p>

						<div style="padding-top: 20%;">
							<p>Accept data, verify, but delete the image.</p>
					    <button :class="delete_verify_button" @click="verifyDelete" :disabled="processing">
					    	Verify & Delete
					    </button>

					    <p>Delete the image.</p>
					    <button :class="delete_button" @click="adminDelete" :disabled="processing">
					    	DELETE
					    </button>

                        <br>

                        <button @click="clearRecentTags">Clear recent tags</button>

                        </div>
					</div>

				  	<!-- Middle - image -->
					<div class="column is-half" style="text-align: center;">
			      		<img :src="this.photo.filename" width="300" height="250" />
			    	</div>

				  	<!-- Right - Tags -->
				  	<div class="column has-text-centered" style="position: relative;">

                        <!-- The list of tags associated with this image-->
                        <Tags :admin="true" />

						<div style="padding-top: 3em;">
							<button class="button is-medium is-dark" @click="clearTags">Clear user input</button>
							<p>To undo this, just refresh the page</p>
						</div>
				  	</div>
				</div>

		    	<div class="has-text-centered mb1">
					<button :class="verify_correct_button" :disabled="processing" @click="verifyCorrect">VERIFY CORRECT</button>

					<button class="button is-large is-danger" :disabled="processing" @click="incorrect">FALSE</button>
				</div>

				<!-- Add / edit tags -->
                <add-tags :admin="true" :id="photo.id" />

				<div style="padding-top: 1em; text-align: center;">
					<p class="strong">Update the image and save the new data</p>
					<button :class="update_new_tags_button" @click="updateNewTags" :disabled="checkUpdateTagsDisabled">
						Update with new tags
					</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'

import AddTags from '../../components/Litter/AddTags'
import Tags from '../../components/Litter/Tags'

import moment from 'moment'

export default {
	name: 'VerifyPhotos',
	components: { Loading, AddTags, Tags },
	async created ()
	{
	    this.loading = true;

        this.$store.dispatch('GET_NEXT_ADMIN_PHOTO');

        this.loading = false;
	},
	data ()
	{
		return {
			loading: true,
			processing: false,
			btn: 'button is-large is-success',
			// button classes
			deleteButton: 'button is-large is-danger mb1',
			deleteVerify: 'button is-large is-warning mb1',
			verifyClass: 'button is-large is-success mb1',
		};
	},
	computed: {

		/**
		 * Return true to disable when processing or if no new tags exist
		 */
		checkUpdateTagsDisabled ()
		{
			if (this.processing || this.$store.state.litter.hasAddedNewTag === false) return true;

			return false;
		},

		/**
		 *
		 */
		delete_button ()
		{
			return this.processing ? this.deleteButton + ' is-loading' : this.deleteButton;
		},

		/**
		 *
		 */
		delete_verify_button ()
		{
			return this.processing ? this.deleteVerify + ' is-loading' : this.deleteVerify;
		},

        /**
         * The photo we are verifying
         */
        photo ()
        {
            return this.$store.state.admin.photo;
        },

        /**
         * Total number of photos that are uploaded and not tagged
         */
        photosNotProcessed ()
        {
            return this.$store.state.admin.not_processed;
        },

        /**
         * Total number of photos that are waiting to be verified
         */
        photosAwaitingVerification ()
        {
            return this.$store.state.admin.awaiting_verification;
        },

		/**
		 *
		 */
		update_new_tags_button ()
		{
			return this.processing ? this.verifyClass + ' is-loading' : this.verifyClass;
		},

        /**
         *
         */
        uploadedTime ()
        {
            return moment(this.photo.created_at).format('LLL');
        },

		/**
		 *
		 */
		verify_correct_button ()
		{
			return this.processing ? this.btn + ' is-loading' : this.btn;
		},
	},
	methods: {

		/**
		 * Delete the image and its records
		 */
		async adminDelete (id)
		{
			this.processing = true;

			await this.$store.dispatch('ADMIN_DELETE_IMAGE');

    		this.processing = false;
		},

		/**
		 * Reset the tags the user has submitted
		 */
		clearTags ()
		{
			this.$store.commit('setAllTagsToZero', this.photo.id);
		},

        /**
         * Remove the users recent tags
         */
        clearRecentTags ()
        {
            this.$store.commit('initRecentTags', {});

            this.$localStorage.remove('recentTags');
        },

		/**
		 * Send the image back to the use
		 */
  		async incorrect ()
  		{
			this.processing = true;

			await this.$store.dispatch('ADMIN_RESET_TAGS');

			this.processing = false;
        },

		/**
		 * The users tags were correct !
		 */
		async verifyCorrect ()
		{
			this.processing = true;

			await this.$store.dispatch('ADMIN_VERIFY_CORRECT');

			this.processing = false;
		},

		// Verify an updated image and delete the image
		async verifyDelete ()
		{
			this.processing = true;

    		await this.$store.dispatch('ADMIN_VERIFY_DELETE');

    		this.processing = false;
  		},

		/**
		 * Update the data and keep the image
		 */
		async updateNewTags ()
		{
			this.processing = true;

            await this.$store.dispatch('ADMIN_UPDATE_WITH_NEW_TAGS');

            this.processing = false;
  		}
	}
}
</script>

<style scoped>

    .strong {
        font-weight: 600;
    }

</style>
