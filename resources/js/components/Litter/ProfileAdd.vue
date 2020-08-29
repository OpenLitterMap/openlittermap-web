<template>
    <div class="control has-text-centered">
    	<div class="select" id="litter-items">
			<select v-model="category">
				<option v-for="cat, i in catnames">{{ cat }}</option>
			</select>
		</div>
		<br class="hide-br" />
	    <div class="select" id="litter-category">
			<select v-model="item">
				<option v-for="i in litterlang">{{ i }}</option>
			</select>
		</div>
		<br class="hide-br" />
    	<div class="select" id="int">
			<select v-model="quantity">
				<option v-for="int in integers">{{ int }}</option>
			</select>
		</div>
		<br>
		<br>
		<button
			:disabled="checkDecr"
			class="button is-medium is-danger"
			@click="decr"
		>-</button>
		<button
			class="button is-medium is-info"
			@click="plus"
		>{{ this.profile17 }}</button>
		<button
			:disabled="checkIncr"
			class="button is-medium is-dark"
			@click="incr"
		>+</button>
		<br>
		<br>
		<button
			id="submitbutton"
			:disabled="checkItems"
			class="button is-medium is-success"
			@click="submit"
			:class="[{ 'is-loading': submitting }, 'is-primary' ]"
		>{{ this.profile18 }}</button>

		<!-- Only show these on mobile <= 768px -->
		<div class="show-mobile">
			<br>
			<added-items />

			<div class="custom-buttons">
				<profile-delete :photoid="id" />
    	    	<presence :itemsr="itemsRemaining" />
    	    </div>
    	</div>
	</div>
</template>

<script>

/****
 *** Import Litter in Various Langauges
 **   === Todo - Make this dynamic for all languages ===
 */
// eg - import { this.locale } from './langs/' . { this.locale } . 'js';
// import { en } from './langs/en.js'
// import { de } from './langs/de.js'
// import { es } from './langs/es.js'
// import { fr } from './langs/fr.js'
// import { it } from './langs/it.js'
// import { ms } from './langs/ms.js'
// import { tk } from './langs/tk.js'

import Presence from './Presence'
import ProfileDelete from './ProfileDelete'
import AddedItems from './AddedItems'

export default {
	// import current locale and some local language strings
	props: ['locale', 'profile17', 'profile18', 'cat', 'id', 'itemsRemaining'],
	components: {
        Presence,
        ProfileDelete,
        AddedItems
	},
	created ()
    {
		/**
		 * todo- make this dynamic for all languages
		 */
		if (this.locale == "en") {
			this.$store.commit('setLang', {
				categoryNames: en.cats,
				currentCategory: this.cat,
			    litterlang: en[this.cat],
			  	currentItem: en[this.cat][0]
			});
		}

		if (this.locale == "de") {
			this.$store.commit('setLang', {
				categoryNames: de.cats,
				currentCategory: this.cat,
			    litterlang: de[this.cat],
			  	currentItem: de[this.cat][0]
			});
		}

		if (this.locale == "es") {
			this.$store.commit('setLang', {
				categoryNames: es.cats,
				currentCategory: this.cat,
			    litterlang: es[this.cat],
			  	currentItem: es[this.cat][0]
			});
		}

		if (this.locale == "fr") {
			this.$store.commit('setLang', {
				categoryNames: fr.cats,
				currentCategory: this.cat,
			    litterlang: fr[this.cat],
			  	currentItem: fr[this.cat][0]
			});
		}

		if (this.locale == "it") {
			this.$store.commit('setLang', {
				categoryNames: it.cats,
				currentCategory: this.cat,
			    litterlang: it[this.cat],
			  	currentItem: it[this.cat][0]
			});
		}

		if (this.locale == "ms") {
			this.$store.commit('setLang', {
				categoryNames: ms.cats,
				currentCategory: this.cat,
			    litterlang: ms[this.cat],
			  	currentItem: ms[this.cat][0]
			});
		}

		if (this.locale == "tk") {
			this.$store.commit('setLang', {
				categoryNames: tk.cats,
				currentCategory: this.cat,
			    litterlang: tk[this.cat],
			  	currentItem: tk[this.cat][0]
			});
		}
	},
	data() {
		return {
			quantity: 1,
    		submitting: false,
	        integers: [
	            1, 2, 3, 4, 5, 6, 7, 8, 9, 10,
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
		 * Increment the quantity
		 */
		incr () {
			this.quantity++;
		},

		/**
		 * Decrement the quantity
		 */
		decr () {
			this.quantity--;
		},

		/**
		 * Add data to the collection
		 */
        plus ()
		{
        	// Need the Key "Smoking" when Value = "Fumar"
        	let reverse;
        	if (this.lang == "en") {
        		reverse = this.category;
        	}

        	if (this.lang == "de") {
        		reverse = de.reverse[this.category];
        	}
        	if (this.lang == "es") {
        		reverse = es.reverse[this.category];
        	}
        	if (this.lang == "fr") {
        		reverse = fr.reverse[this.category];
        	}
        	if (this.lang == "it") {
        		reverse = it.reverse[this.category];
        	}
        	if (this.lang == "ms") {
        		reverse = ms.reverse[this.category];
        	}
        	if (this.lang == "tk") {
        		reverse = tk.reverse[this.category];
        	}
        	// console.log(reverse);
        	this.$store.commit('addItem', {
        		category: this.category,
        		item: this.item,
        		quantity: this.quantity,
        		reverse
        	});
            var button = document.getElementById('submitbutton');
            button.disabled = false;
            this.quantity = "1";
        },

        /**
         * Submit the image for verification
         */
        async submit ()
        {
            this.submitting = true;
            let id = document.getElementsByClassName('photoid')[0].id;

            await axios.post('profile/' + id, {
             	'categories': this.categories,
              	'presence': this.presence
            })
            .then(response => {
                // console.log(response);
                if (response.status == 200) {
                    this.submitting = false;
                    // todo - update XP bar
                    // ProgressBar.vue
                    alert('Excellent work! This image has been submitted for verification. THANK YOU FOR HELPING TO KEEP OUR PLANET CLEAN! WELL DONE.');
                    window.location.href = window.location.href;
                } else {
                    alert('There was a problem attributing this image. Please try again');
                }
            })
            .catch(error => console.log(error));
        },
	},
	computed: {
		/**
		 * Get / Set the current category
		 */
		category: {
			get () {
				return this.$store.state.litter.currentCategory;
			},
			set (cat) {
				// todo, refactor this dynamically
				if (this.locale == "en") {
					this.$store.commit('changeCategory', {
						cat,
						litterlang: en[cat],
			  			currentItem: en[cat][0]
					});
				}

				if (this.locale == "de") {
					this.$store.commit('changeCategory', {
						cat,
						litterlang: de[cat],
			  			currentItem: de[cat][0]
					});
				}

				if (this.locale == "es") {
					this.$store.commit('changeCategory', {
						cat,
						litterlang: es[cat],
			  			currentItem: es[cat][0]
					});
				}
				if (this.locale == "fr") {
					this.$store.commit('changeCategory', {
						cat,
						litterlang: fr[cat],
			  			currentItem: fr[cat][0]
					});
				}
				if (this.locale == "it") {
					this.$store.commit('changeCategory', {
						cat,
						litterlang: it[cat],
			  			currentItem: it[cat][0]
					});
				}
				if (this.locale == "ms") {
					this.$store.commit('changeCategory', {
						cat,
						litterlang: ms[cat],
			  			currentItem: ms[cat][0]
					});
				}
				if (this.locale == "tk") {
					this.$store.commit('changeCategory', {
						cat,
						litterlang: tk[cat],
			  			currentItem: tk[cat][0]
					});
				}
			}
		},

		/**
		 * Category names
		 */
		catnames () {
			return this.$store.state.litter.categoryNames;
		},

		/**
		 * Get / Set the current item
		 */
		item: {
			get() {
				return this.$store.state.litter.currentItem;
			},
			set(i) {
				this.$store.commit('changeItem', {i});
			}
		},

		/**
		 * Current items, without categories
		 */
		items () {
			return this.$store.state.litter.items;
		},

		/**
		 *
		 */
		lang: {
			set() {
				return this.locale;
			},
			get() {
				return this.locale;
			}
		},

		/**
		 * current language of litter types
		 */
		litterlang() {
			return this.$store.state.litter.litterlang;
		},

		/**
		 *
		 */
		presence() {
			return this.$store.state.litter.presence;
		},

		/**
		 * Data to pass to backend
		 * { Alcohol: { BeerCans: 1 }, Smoking: { CigaretteButts: 2 } }
		 */
		categories () {
			return this.$store.state.litter.categories;
		},

		/**
		 *
		 */
		checkDecr () {
			return this.quantity == 1 ? true : false;
		},

		/**
		 *
		 */
		checkIncr () {
			return this.quantity == 100 ? true : false;
		},

		/**
		 *
		 */
		checkItems () {
			return Object.keys(this.$store.state.litter.items).length == 0 ? true : false;
		}
	}
}
</script>

<style lang="scss">

    .hide-br {
        display: none;
    }

    @media (max-width: 500px)
    {
        .hide-br {
            display: block;
        }
        .select {
            margin-top: 10px;
        }
    }

    @media (min-width: 768px)
    {
        .show-mobile {
            display: none !important;
        }
    }

    .custom-buttons {
        display: flex;
        padding: 20px;
    }
</style>
