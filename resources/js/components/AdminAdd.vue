<template>
  	<div class="control has-text-centered">
  		<div class="select">
			<select v-model="category">
				<option v-for="cat, i in catnames">{{ cat }}</option>
			</select>
		</div>

	  	<div class="select">
			<select v-model="item">
				<option v-for="i in litterlang">{{ i }}</option>
			</select>
		</div>

		<div class="select" style="margin-bottom: 1em;">
			<select v-model="quantity">
				<option v-for="int in integers">{{ int }}</option>
			</select>
		</div>
		<br>

		<button
			:disabled="checkDecr"
			class="button is-medium is-danger" 
			@click="decr"
		>-</button>

		<button class="button is-medium is-success" @click="plus">Add Data</button>

		<button
			:disabled="checkIncr"
			class="button is-medium is-dark" 
			@click="incr"
		>+</button>
	</div>
</template>

<script>
/****
 *** Import Litter in Various Languages
 **  - Todo: Make this dynamic for all languages ===
 *   - right now we only need verification in English
 */
// eg - import { this.locale } from './langs/' . { this.locale } . 'js';
import { en } from './langs/en.js';

export default {
	name: 'AdminAdd',
	created () {
		this.$store.commit('setLang', {
			categoryNames: en.cats,
			currentCategory: 'Smoking',
		    litterlang: en['Smoking'],
		  	currentItem: en['Smoking'][0]
		});
	},
	data ()
	{
		return {
			quantity: 1,
    		submitting: false,
	        integers: [
	          0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 
	          11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 
	          21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 
	          31, 32, 33, 34, 35, 36, 37, 38, 39, 40,
	          41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 
	          51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 
	          61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 
	          71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 
	          81, 82, 83, 84, 85, 86, 87, 88, 89, 90, 
	          91, 92, 93, 94, 95, 96, 97, 98, 99, 100
	        ],
		};
	},
	methods: {
		/**
		 * Decrement the quantity
		 */
		decr () {
			this.quantity--;
		},

		/**
		 * Increment the quantity
		 */
		incr () {
			this.quantity++;
		},

		/**
		 * Add data to the collection
		 */
	 	plus () {
		  	// Need the Key "Smoking" when Value = "Fumar"
		  	// let reverse;
		  	// if (this.lang == "en") reverse = this.category;
		  	this.$store.commit('addItem', { 
		  		category: this.category,
		  		item: this.item,
		  		quantity: this.quantity,
		  		reverse: this.category // change this to reverse for multi-lang
		  	});
		    
	    	this.quantity = 1;
		 },
	},
	computed: {
		/**
		 * @category Smoking, Alcohol..
		 */
		category: {
			get () {
				return this.$store.state.litter.currentCategory;
			},
			set (cat) {
				this.$store.commit('changeCategory', {
					cat,
					litterlang: en[cat],
		  			currentItem: en[cat][0]
				});
			}
		},

		/**
		 * 
		 */
		catnames () {
			return this.$store.state.litter.categoryNames;
		},

		/**
		 * 
		 */
		checkDecr () {
			return this.quantity == 0 ? true : false;
		},
		
		/**
		 * 
		 */
		checkIncr () {
			return this.quantity == 100 ? true : false;
		},

		/** 
		 * Get / Set the current item 
		 */
		item: {
			get () {
				return this.$store.state.litter.currentItem;
			},
			set (i) {
				this.$store.commit('changeItem', {i});
			}
		},

		/**
		 * 
		 */
		items () {
			return this.$store.state.litter.items;
		},


		/**
		 * 
		 */
		lang: {
			set () {
				return this.locale;
			},
			get () {
				return this.locale;
			}
		},

		/** 
		 * Get / Set the items for the current language 
		 */
		litterlang () {
			return this.$store.state.litter.litterlang;
		},

		/**
		 * 
		 */
		presence () {
			return this.$store.state.litter.presence;
		},

		/**
		 * 
		 */
		stuff () {
			return this.$store.state.litter.stuff;
		}
	}
}
</script>