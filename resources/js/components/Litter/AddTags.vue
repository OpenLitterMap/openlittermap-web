<template>
    <div class="control has-text-centered">
        <!-- Categories -->
        <div id="litter-items" class="litter-select-category">
            <v-select v-model="category" :options="categories" label="title" :value="category" :clearable="false">
                <slot name="no-options">
                    Sorry, no matching options.
                </slot>
            </v-select>
        </div>

        <!-- Items -->
        <div id="litter-category" class="litter-select-items">
            <v-select v-model="item" :options="items" label="title" :value="i" :clearable="false">
                <slot name="no-options">
                    Sorry, no matching options.
                </slot>
            </v-select>
        </div>

        <!-- Quantity -->
        <div id="int" class="litter-select-quantity">
            <v-select v-model="quantity" :options="integers" :clearable="false">
                <slot name="no-options">
                    Sorry, no matching options.
                </slot>
            </v-select>
        </div>

        <br>
        <br>

        <button
            :disabled="checkDecr"
            class="button is-medium is-danger"
            @click="decr"
        >
            -
        </button>

        <button
            class="button is-medium is-info"
            @click="addTag"
        >
            {{ $t('tags.add-tag') }}
        </button>

        <button
            :disabled="checkIncr"
            class="button is-medium is-dark"
            @click="incr"
        >
            +
        </button>

        <br>
        <br>

        <button
            v-show="! admin"
            :disabled="checkItems"
            :class="button"
            @click="submit"
        >
            {{ $t('common.submit') }}
        </button>

        <!-- Only show these on mobile <= 768px -->
        <div class="show-mobile">
            <br>
            <tags />

            <div class="custom-buttons">
                <profile-delete :photoid=" id" />
                <presence :itemsr="true" />
            </div>
        </div>
    </div>
</template>

<script>
import Tags from './Tags';
import Presence from './Presence';
import ProfileDelete from './ProfileDelete';
import { categories } from '../../extra/categories';
import vSelect from "vue-select";
import { litterkeys } from '../../extra/litterkeys';

export default {
    name: 'AddTags', // photo.id, bool
    components: {
        Tags,
        Presence,
        ProfileDelete,
        vSelect
    },
    props: ['id', 'admin'],
    data ()
    {
        return {
		    btn: 'button is-medium is-success',
            quantity: 1,
    		processing: false,
	        integers: Array.from({length: 100}, (_, i) => i + 1),
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
                const findedCatName = Object.keys(litterkeys).find(key =>
                    litterkeys[key].filter(litter=> litter.key === i.key).length > 0
                );
                this.$store.commit('changeCategory', this.categories.find(category => category.key === findedCatName));
                this.$store.commit('changeItem', i);
            }
        },

        /**
         * All litter items in the system
         */
        items ()
        {
            return Object.keys(litterkeys).map((key) =>
                litterkeys[key].map(litter=> ({
                    key: litter.key ,
                    title: this.$i18n.t('litter.' + key + '.' + litter.key)
                }))
            ).flat();
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
            return this.quantity === 1 ? true : false;
        },

        /**
         * Disable increment if true
         */
        checkIncr ()
        {
            return this.quantity === 100 ? true : false;
        },

        /**
         * Disable button if true
         */
        checkItems ()
        {
            return Object.keys(this.$store.state.litter.items).length === 0 ? true : false;
        }
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
};
</script>

<style lang="scss">
@import "vue-select/src/scss/vue-select.scss";
    .hide-br {
        display: none;
    }

    .litter-select{

        &-category{
            display: inline-block;
            min-width: 130px;
        }
        &-items{
            display: inline-block;
            min-width: 270px;
        }
        &-quantity{
            display: inline-block;
            min-width: 80px;
        }
    }

    .v-select{

        .vs__dropdown {
            &-toggle{
                background-color: white;
            }
            &-menu {
                max-height: 250px
            }
        }
        .vs__no-options {
            color: black;
        }
    }


    @media (max-width: 500px)
    {
        .hide-br {
            display: block;
        }
        .v-select {
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
