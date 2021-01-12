<template>
    <div class="control has-text-centered">
        <!-- Categories -->
        <b-field grouped group-multiline position="is-centered" class="mb-5">
            <b-select v-model="category">
                <option v-for="cat in categories" :key="cat.key" :value="cat">
                    {{ cat.title }}
                </option>
            </b-select>

            <!-- Items -->
            <b-select v-model="item">
                <option v-for="i in items" :key="i.key" :value="i">
                    {{ i.title }}
                </option>
            </b-select>

            <!-- Quantity -->
            <b-select v-model="quantity">
                <option v-for="int in integers">
                    {{ int }}
                </option>
            </b-select>
        </b-field>

        <div v-if="recentlyTags.length > 0" class="mb-5">
            <span>
                {{ $t('tags.recently-tags') }}
            </span>
            <transition-group name="list" class="recently-tags" tag="div">
                <div
                    v-for="tag in recentlyTags"
                    :key="tag.item.key"
                    class="litter-tag"
                    @click="changeTag(tag)"
                >
                    {{ getTagName(tag.item.key, tag.category.key) }}
                </div>
            </transition-group>
        </div>

        <div>
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
        </div>

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
                <profile-delete :photoid="id" />
                <presence :itemsr="true" />
            </div>
        </div>
    </div>
</template>

<script>
import Tags from './Tags';
import Presence from './Presence';
import ProfileDelete from './ProfileDelete';
// import VueSimpleSuggest from 'vue-simple-suggest' todo
// import 'vue-simple-suggest/dist/styles.css'
import { categories } from '../../extra/categories';
import { litterkeys } from '../../extra/litterkeys';

export default {
    name: 'AddTags',
    components: {
        Tags,
        Presence,
        ProfileDelete,
    },
    props: {
        'id': { type: Number, required: true },
        'admin': Boolean
    },
    data ()
    {
        return {
            btn: 'button is-medium is-success',
            quantity: 1,
            processing: false,
            integers: Array.from({ length: 100 }, (_, i) => i + 1),
            // autoCompleteStyle: {
            //     vueSimpleSuggest: 'position-relative flex-05 mb1',
            //     inputWrapper: '',
            //     defaultInput : 'input',
            //     suggestions: 'position-absolute list-group z-1000 custom-class-overflow flex-05',
            //     suggestItem: 'list-group-item'
            // },
        };
    },
    computed: {

        /**
         * Show spinner when processing
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
            get ()
            {
                return this.$store.state.litter.category;
            },
            set (cat)
            {
                this.$store.commit('changeCategory', cat);
                this.quantity = 1;
            }
        },

        /**
         * Categories is imported and the key is used to return the translated title
         */
        categories ()
        {
            return categories.map(cat =>
            {
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
            get ()
            {
                return this.$store.state.litter.item;
            },
            set (i)
            {
                this.$store.commit('changeItem', i);
            }
        },

        /**
         * Litter items for the selected category
         */
        items ()
        {
            return this.$store.state.litter.items.map(item =>
            {
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
        },
        recentlyTags ()
        {
            return this.$store.state.litter.recentlyTags;
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

            this.$store.commit('addRecentlyTag', {
                category: this.category,
                item: this.item,
            });
        },
        changeTag ({category, item})
        {
            this.category = category;
            this.item = item;
        },
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
         * Submit the image for verification
         * litter/actions.js
         */
        async submit ()
        {
            this.processing = true;

            await this.$store.dispatch('ADD_TAGS_TO_IMAGE');

            this.processing = false;
        },
        getTagName (tag, category)
        {
            return this.$i18n.t(`litter.${category}.${tag}`);
        },
    }
};
</script>

<style lang="scss" scoped>
@import "../../styles/variables.scss";

    .hide-br {
        display: none;
    }

    .suggest-item {
        color: black;
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

    .recently-tags {
        display: flex;
        max-width: 500px;
        margin: auto;
        flex-wrap: wrap;
        max-height: 155px;
        overflow: auto;
    }

    .litter-tag {
        cursor: pointer;
        padding: 5px;
        border-radius: 5px;
        background-color: $info;
        margin: 5px
    }

    .list-enter-active, .list-leave-active {
        transition: all 1s;
    }

    .list-enter, .list-leave-to /* .list-leave-active below version 2.1.8 */ {
        opacity: 0;
        transform: translateX(30px);
    }

</style>
