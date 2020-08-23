<template>
	<div style="padding-left: 1em; padding-right: 1em;">
			<h1 class="title is-4">Delete My Account</h1>
			<hr>
			<p>Do you want to delete your account?</p>
			<br>
			<div class="columns">
				<div class="column is-one-third is-offset-1">
					<div class="row">
				        <span class="is-danger" v-if="deleteform.errors.has('password')" v-text="deleteform.errors.get('password')"></span>
						<form method="POST" action="/settings/delete" @submit.prevent="deleteAccount" @keydown="deleteform.errors.clear($event.target.name)">
							<input type="hidden" name="csrf-token" :value="csrfToken">
							<label for="password">Enter your password</label>
							<br>
							<p v-show="this.message" style="color: red;"> {{ this.message }}</p>
							<div class="field">
								<div class="control">
									<input type="password" name="password" id="password" placeholder="******" v-model="deleteform.password" required @keydown="clearError" class="input" />
								</div>
							</div>
							<button class="button is-danger" :class="[{ 'is-loading': this.submitting }, 'is-success' ]">Delete My Account</button>
						</form>
					</div>
				</div>
			</div>
	</div>
</template>

<script>

class Errors {

	/**
	 * Create a new errors instance
	 */
	constructor() {
		this.errors = {};
	}

	/**
	 * Get the error message for a field
	 */
	get(field){
		if(this.errors[field]){
			return this.errors[field][0];
		}
	}

	/**
	 * Determine if an error exists for a given field
	 */
	has(field){
		return this.errors.hasOwnProperty(field);
	}

	/**
	 * Record the new errors
	 */
	record(errors){
		this.errors = errors;
	}

	/**
	 * Clear one or all error fields
	 */
	clear(field){
		if (field) {
			delete this.errors[field];
			return;
		}
		// else
		this.errors = {};
	}

	/**
	 * Determine if we have any errors
	 */
	any(){
		console.log(this);
		return Object.keys(this.errors).length > 0;
	}
}

class Form {

	/**
	 * Create a new Form instance
	 */
	constructor(data) {
		this.originalData = data;

		// create data objects on the form
		for(let field in data) {
			this[field] = data[field];
		}

		this.errors = new Errors();
	}

	/**
	 * Fetch relevant data for the form
	 */
	data() {
		// // clone the object  old way
		// let data = Object.assign({}, this);
		// // delete unnecessary data
		// delete data.originalData;
		// delete data.errors;
		// return data;
		let data = {};
		// filter through the original data
		for (let property in this.originalData){
			data[property] = this[property];
		}
		return data;
	}

	/**
	 * Reset the form fields
	 */
	reset() {
		for(let field in this.originalData){
			this[field] = '';
		}
		this.errors.clear();
	}

	/**
	 * Submit the form
	 */
	submit(requestType, url) {

		// return a set up a promise
		return new Promise((resolve, reject) => {
			// submit the ajax request
			axios[requestType](url, this.data())
			  // 200
			 .then(response => {
			 	console.log('first');
			 	// use local onSuccess method then trigger Vue method with resolve
			 	console.log(response.data); // .email | .user_id
			 	// this.onSuccess(response.data);
			 	// callback with the data
			 	resolve(response.data);
			 	console.log('third')
			 })
			  // not 200
			 .catch(error => {
			 	this.onFail(error.response.data);
			 	reject(error.response.data);
			 });
		});
	}

	/**
	 * Handle a successful form submission
	 */
	onSuccess(data){
	 	console.log('second');
		// this.reset();
	};

	/**
	 * Handle a failed form submission
	 */
	onFail(errors) {
		console.log(errors);
		this.errors.record(errors);
	}
}

export default {
	// props: ['avatar', 'id', 'usersname', 'username', 'email', 'subscription'],
    name: 'Account',
	data() {
		return {
			message: '',
			submitting: false,
			deleteform: new Form({
				password: ''
			}),
		};
	},
	methods: {
		deleteAccount() {
			this.submitting = true;
			// this.deleteform.submit('post', '/settings/delete')
			axios({
				method: 'post',
				url: '/en/settings/delete',
				data: {
					password: this.deleteform.password
				}
			})

			.then(response => {
			 	console.log(response);
			 	this.submitting = false;
			 	this.message = response.data.message;
			 	this.deleteform.password = '';
			 })
			 .catch(error => {
			 	console.log(error);
			 });
		},

		clearError() {
			this.message = '';
		},

	},

	computed: {

		csrfToken() {
			// console.log($('meta[name="csrf-token"]').attr('content'));
    		// return $('meta[name="csrf-token"]').attr('content');
    		return OLM.csrfToken;
		},

	},
}
</script>
