require('./bootstrap');
import Vue from 'vue';
// import LoginModal from './components/LoginModal.vue';

window.axios = axios;

const vm = new Vue({
	el: '#vuemap',
	data: {
		showLoginModal: false,
	},
	components: {
		// LoginModal
	}
});