<template>
    <div class="control has-text-centered">

        <!-- Categories -->
    	<div class="select" id="litter-items">
			<select v-model="category">
				<option v-for="cat in categories" :value="cat">{{ cat.title }}</option>
			</select>
		</div>

		<br class="hide-br" />

        <!-- Items -->
	    <div class="select" id="litter-category">
			<select v-model="item">
				<option v-for="i in items" :value="i">{{ i.title }}</option>
			</select>
		</div>

		<br class="hide-br" />

        <!-- Quantity -->
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
			@click="addTag"
		>Add Tag</button>

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
		>submit</button>

		<!-- Only show these on mobile <= 768px -->
		<div class="show-mobile">
			<br>
			<show-tags />

			<div class="custom-buttons">
				<profile-delete :photoid="id" />
    	    	<presence :itemsr="true" />
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

import { categories } from '../../extra/categories'

import Presence from './Presence'
import ProfileDelete from './ProfileDelete'
import ShowTags from './ShowTags'

export default {
    name: 'AddTags',
	props: ['id'], // photo.id
	components: {
        Presence,
        ProfileDelete,
        ShowTags
	},
	created ()
    {
        // We need to initialize with translated title
        this.$store.commit('changeCategory', {
            id: 11,
            key: 'smoking',
            title: this.$i18n.t('litter.categories.smoking')
        });

        // We need to initialize with translated title
        this.$store.commit('changeItem', {
            id: 0,
            key: 'butts',
            title: this.$i18n.t('litter.smoking.butts')
        });
    },
	data ()
    {
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
    computed: {

        /**
         * Get / Set the current category
         *
         * @value { id: 0, key: 'category', title: 'Translated Category' };
         */
        category: {
            get () {
                return this.$store.state.litter.category;
            },
            set (cat) {
                this.$store.commit('changeCategory', cat);
            }
        },

        /**
         * Categories is imported and the key is used to return the translated title
         */
        categories ()
        {
            return categories.map(cat => {
                return {
                    id: cat.id,
                    key: cat.key,
                    title: this.$i18n.t('litter.categories.' + cat.key)
                };
            });
        },

        /**
         * Get / Set the current item (category -> item)
         */
        item: {
            get () {
                return this.$store.state.litter.item;
            },
            set (i) {
                this.$store.commit('changeItem', i);
            }
        },

        /**
         * Litter items for the selected category
         */
        items ()
        {
            return this.$store.state.litter.items.map(item => {
               return {
                   id: item.id,
                   key: item.key,
                   title: this.$i18n.t('litter.' + this.category.key + '.' + item.key )
               };
            });
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
         *
         */
        presence ()
        {
            return this.$store.state.litter.presence;
        },

        /**
         *
         */
        checkDecr ()
        {
            return this.quantity == 1 ? true : false;
        },

        /**
         *
         */
        checkIncr ()
        {
            return this.quantity == 100 ? true : false;
        },

        /**
         *
         */
        checkItems ()
        {
            return Object.keys(this.$store.state.litter.items).length == 0 ? true : false;
        }
    },
    methods: {

		/**
		 * Increment the quantity
		 */
		incr ()
        {
			this.quantity++;
		},

		/**
		 * Decrement the quantity
		 */
		decr ()
        {
			this.quantity--;
		},

		/**
		 * Add data to the collection
		 */
        addTag ()
		{
        	this.$store.commit('addTag', {
        		category: this.category,
        		item: this.item,
        		quantity: this.quantity,
        	});

            // var button = document.getElementById('submitbutton');
            // button.disabled = false;
            // this.quantity = "1";
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
