<template>
	<div class="container mt4pc">

		<loading v-if="loading" :active.sync="loading" :is-full-page="true" />

	    <div v-else>

	    	<div v-if="this.photosAwaitingVerification == 0 && this.photosNotProcessed == 0">
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
					    <button :class="computeDeleteVerify" @click="verifyDelete" :disabled="disabled">
					    	Verify & Delete
					    </button>

					    <p>Delete the image.</p>
					    <button :class="computeDeleteButton" @click="adminDelete" :disabled="disabled">
					    	DELETE
					    </button>
						</div>
					</div>

				  	<!-- Middle - image -->
					<div class="column is-half" style="text-align: center;">
			      		<img :src="this.photo.filename" width="300" height="250" />
			    	</div>

				  	<!-- Right - data -->
				  	<div class="column has-text-centered" style="position: relative;">
						<admin-items />

						<div style="padding-top: 3em;">
							<button class="button is-medium is-dark" @click="clearItems">Clear user input</button>
							<p>To undo this, just refresh the page</p>
						</div>
				  	</div>
				</div>

		    	<div class="has-text-centered mb1em">
					<button :class="getVerifyClass" :disabled="disabled" @click="verifyCorrect">VERIFY CORRECT</button>
					<button class="button is-large is-danger" :disabled="disabled" @click="incorrect">FALSE</button>
				</div>

				<!-- Add new items into the collection -->
				<admin-add />

				<div style="padding-top: 1em; text-align: center;">
					<p class="strong">Update the image and save the new data</p>
					<button :class="computeVerifyButton" @click="verifyKeep" :disabled="checkUpdateTagsDisabled">
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

import AdminItems from '../../components/AdminItems'
import AdminAdd from '../../components/AdminAdd'

import moment from 'moment'

export default {
	name: 'VerifyPhotos',
	components: { Loading, AdminItems, AdminAdd },
	async created ()
	{
		await this.getData();
	},
	data ()
	{
		return {
			disabled: false,
			loading: true,
			processing: false,
			button: 'button is-large is-success',
			photo: {},
			photosNotProcessed: 0,
			photosAwaitingVerification: 0,
			// button classes
			deleteButtonClass: 'button is-large is-danger',
			deleteVerifyClass: 'button is-large is-warning mb20',
			verifyClass: 'button is-large is-success mb20',
		};
	},
	computed: {

		/**
		 * Return true if disabled is true
		 * Return true if no new tags exist
		 */
		checkUpdateTagsDisabled ()
		{
			if (this.disabled || this.$store.state.litter.hasAddedNewTag == false) return true;

			return false;
		},

		/**
		 *
		 */
		computeDeleteButton ()
		{
			return this.processing ? this.deleteButtonClass + ' is-loading' : this.deleteButtonClass;
		},

		/**
		 *
		 */
		computeDeleteVerify ()
		{
			return this.processing ? this.deleteVerifyClass + ' is-loading' : this.deleteVerifyClass;
		},

		/**
		 *
		 */
		computeVerifyButton ()
		{
			return this.processing ? this.verifyClass + ' is-loading' : this.verifyClass;
		},

		/**
		 *
		 */
		getVerifyClass ()
		{
			return this.processing ? this.button + ' is-loading' : this.button;
		},

		/**
		 *
		 */
		uploadedTime ()
		{
			return moment(this.photo.created_at).format('LLL');
		},
	},
	methods: {

		/**
		 * Delete the image and its records
		 */
		async adminDelete (id)
		{
			this.disabled = true;
			this.processing = true;

    		await axios.post('/admin/destroy', {
        		photoId: this.photo.id
    		})
    		.then(async response => {
      			await this.getData();
			}).catch(error => {
        		console.log(error);
        		alert('Error!');
    		});
		},

		/**
		 * Reset the tags the user has submitted
		 */
		clearItems ()
		{
			this.$store.commit('setAllItemsToZero');
		},

		/**
		 * Get the next image & tags to verify
		 */
		async getData ()
		{
			this.loading = true;

			// clear previous input
			this.$store.commit('resetLitter');

			await axios.get('/admin/get-image')
			.then(resp => {
				console.log('get_data', resp);
				this.photo = resp.data.photo;
				if (resp.data.photoData) {
					this.$store.commit('initAdminItems', JSON.parse(resp.data.photoData));
				}
				this.photosNotProcessed = resp.data.photosNotProcessed;
				this.photosAwaitingVerification = resp.data.photosAwaitingVerification
				this.disabled = false;
				this.processing = false;
				this.loading = false;
				// console.log('photo', this.photo);
			})
			.catch(err => {
				console.error(err);
			});

			// this.loading = false;
		},

		/**
		 * Send the image back to the use
		 */
  		async incorrect ()
  		{
			this.disabled = true;
			this.processing = true;

		    await axios.post('/admin/incorrect', {
		    	photoId: this.photo.id
		    })
		    .then(response => {
		    	if (response.status == 200) this.getData();
		    }).catch(error => {
		      	console.log(error);
		      	alert('Error! Please try again');
		    });
		  },


		/**
		 * The users tags were correct !
		 */
		async verifyCorrect ()
		{
			this.disabled = true;
			this.processing = true;

			await axios.post('/admin/verifykeepimage', {
				photoId: this.photo.id
			})
			.then(resp => {
				console.log(resp);
				if (resp.status == 200) this.getData();
			})
			.catch(err => {
				console.error(err);
			});
		},

		// Verify an updated image and delete the image
		async verifyDelete ()
		{
			this.disabled = true;
			this.processing = true;

    		await axios.post('/admin/contentsupdatedelete', {
 				photoId: id, categories: categories
 			}).then(response => {
 				if (response.status == 200) this.getData();
    		}).catch(error => {
       			console.log(error);
       			alert('Error! Please try again');
    		});
  		},

		/**
		 * Update the data and keep the image
		 */
		async verifyKeep (id)
		{
			this.disabled = true;
			this.processing = true;

    		await axios.post('/admin/contentsupdatekeep', {
      			photoId: this.photo.id,
      			categories: this.$store.state.litter.categories
    		}).then(response => {
    			if (response.status == 200) this.getData();
    		}).catch(error => {
      			console.log(error);
      			alert('Error! Please try again');
    		});
  		}
	}
}
</script>

<style lang="scss">

    .flex {
        display: flex;
    }

    .flex-1 {
        flex: 1;
    }

    .mt4pc {
        margin-top: 4%;
    }

    .mb1em {
        margin-bottom: 1em;
    }

    .mb20 {
        margin-bottom: 20px;
    }

    .strong {
        font-weight: 600;
    }

</style>
