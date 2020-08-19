<template>
	<div style="padding-left: 1em; padding-right: 1em;">
		<h1 class="title is-4">Change My Password</h1>
		<hr>
		<br>
		<div class="columns">
			<div class="column is-one-third is-offset-1">
				<div class="row">
					<!-- Change password -->
					<form action="/settings/general/update" method="POST" @submit.prevent="changepw" @keydown="form.errors.clear($event.target.name)">

						<!-- {{ method_field('PATCH') }} -->
						<input type="hidden" name="csrf-token" :value="csrfToken">

						<!-- Old Password -->
	                    <label for="oldpassword">Enter old password</label>
	                    <br>
	                    <span v-if="form.errors.has('oldpassword')" v-text="form.errors.get('oldpassword')" style="color:red;"></span>
	                    <div class="field">
	                    	<div class="control has-icons-left">
		                        <input id="oldpassword" type="password" name="oldpassword" class="input" placeholder="* * * * * * *" v-model="form.oldpassword">
		                        <span class="icon is-small is-left">
		      						<i class="fa fa-key"></i>
		    					</span>
	                        </div>
	                    </div>

	                    <!-- New Password -->
						<label for="password">Enter new password</label>
						<br>
						<span v-if="form.errors.has('password')" v-text="form.errors.get('password')" style="color: red;"></span>
						<div class="field">
	                        <div class="control has-icons-left">
								<input id="password" type="password" name="password" class="input" placeholder="Enter a strong password" v-model="form.password" />
								<span class="icon is-small is-left">
		      						<i class="fa fa-key"></i>
		    					</span>
	                        </div>
						</div>

						<!-- Repeat Password -->
						<label for="password_confirmation">Confirm your new password</label>
						<br>
						<span v-if="form.errors.has('password_confirmation')" v-text="form.errors.get('password_confirmation')" style="color: red;"></span>
						<div class="field">
							<div class="control has-icons-left">
								<input type="password" name="password_confirmation" id="password_confirmation" class="input"  placeholder="Repeat your strong password" v-model="form.password_confirmation" />
								<span class="icon is-small is-left">
		      						<i class="fa fa-key"></i>
		    					</span>
							</div>
						</div>
						<br>
						<p v-show="this.successmessage">{{ this.successmessage }}</p>
						<p v-show="this.errormessage" style="color: red;">{{ this.errormessage }}</p>

						<div class="col-md-12" style="text-align: center;">
							<button class="button is-medium is-info">Update Password</button>
						</div>
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
				 	// this.message = response.data;
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
		props: ['avatar', 'id', 'usersname', 'username', 'email', 'subscription'],
		data() {
			return {
				image: '',
				errormessage: '',
				successmessage: '',
				form: new Form({
					oldpassword: '',
					password: '',
					password_confirmation: '',
				}),

			};
		},

		methods: {
			changepw() {
				this.form.submit('patch', '/en/settings/details/password')
				 .then(response => { 
				 	// console.log(response);
				 	this.successmessage = response.message;
				 })
				 .catch(error => {
				 	// console.log(error);
				 	this.errormessage = response.message;
				 });
			}
		},
		computed: {
			userImage() {
				return '/uploads/' + this.id +  '/avatar/' + this.avatar;
			},
			csrfToken() {
        		return OLM.csrfToken;
			}
		}
	}
</script>
