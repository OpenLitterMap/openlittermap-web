<template>
    <div class="control has-text-centered">

        <!-- Categories -->
    	<div class="select" id="litter-items">
			<select v-model="category">
				<option v-for="cat in categories" :value="cat">{{ cat.title }}</option>
			</select>
		</div>

        <!-- Items -->
	    <div class="select" id="litter-category">
			<select v-model="item">
				<option v-for="i in items" :value="i">{{ i.title }}</option>
			</select>
		</div>

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
		>{{ $t('tags.add-tag') }}</button>

		<button
			:disabled="checkIncr"
			class="button is-medium is-dark"
			@click="incr"
		>+</button>

		<br>
		<br>

		<button
			:disabled="checkItems"
			:class="button"
			@click="submit"
		>{{ $t('common.submit') }}</button>

		<!-- Only show these on mobile <= 768px -->
		<div class="show-mobile">
			<br>
			<tags />

			<div class="custom-buttons">
				<profile-delete :photoid="  id" />
    	    	<presence :itemsr="true" />
    	    </div>
    	</div>
	</div>
</template>

<script>
import Tags from './Tags'
import Presence from './Presence'
import ProfileDelete from './ProfileDelete'
import { categories } from '../../extra/categories'

export default {
    name: 'AddTags',
	props: ['id'], // photo.id
	components: {
        Tags,
        Presence,
        ProfileDelete
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
		    btn: 'button is-medium is-success',
			quantity: 1,
    		processing: false,
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
         * Add ' is-loading' when processing
         */
        button ()
        {
            return this.processing ? this.btn + ' is-loading' : this.btn;
        },

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
                this.quantity = 1;
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
         * Has the litter been picked up, or is it still there?
         */
        presence ()
        {
            return this.$store.state.litter.presence;
        },

        /**
         * Disable decrement if true
         */
        checkDecr ()
        {
            return this.quantity == 1 ? true : false;
        },

        /**
         * Disable increment if true
         */
        checkIncr ()
        {
            return this.quantity == 100 ? true : false;
        },

        /**
         * Disable button if true
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

            this.quantity = 1;
            // this.disabled = false
        },

        /**
         * Submit the image for verification
         * litter/actions.js
         */
        async submit ()
        {
            this.processing = true;

            await this.$store.dispatch('ADD_TAGS_TO_IMAGE');

            this.processing = false;
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
