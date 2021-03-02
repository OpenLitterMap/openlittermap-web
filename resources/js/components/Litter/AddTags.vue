<template>
    <div class="control has-text-centered">

        <!-- Categories -->
        <div class="select">
            <vue-simple-suggest
                ref="categories"
                display-attribute="title"
                value-attribute="key"
                :filter-by-query="true"
                :list="categories"
                :min-length="0"
                :max-suggestions="0"
                mode="select"
                :styles="autoCompleteStyle"
                v-model="category"
                @suggestion-click="onSuggestion()"
                @focus="onFocusCategories()"
                v-click-outside="clickOutsideCategory"
            />
        </div>

        <!-- Tags per category -->
        <div class="select">
            <vue-simple-suggest
                ref="tags"
                display-attribute="title"
                value-attribute="key"
                :filter-by-query="true"
                :list="tags"
                :min-length="0"
                :max-suggestions="0"
                mode="select"
                :styles="autoCompleteStyle"
                v-model="tag"
                @suggestion-click="onSuggestion()"
                @focus="onFocusTags()"
                v-click-outside="clickOutsideTag"
            />
        </div>

        <!-- Quantity -->
        <div class="select" id="int">
            <select v-model="quantity">
                <option v-for="int in integers">{{ int }}</option>
            </select>
        </div>

        <br><br>

        <div v-if="Object.keys(recentTags).length > 0" class="mb-5">

            <p class="mb-05">{{ $t('tags.recently-tags') }}</p>

            <div v-for="category in Object.keys(recentTags)">
                <p>{{ getCategoryName(category) }}</p>
                 <transition-group name="list" class="recent-tags" tag="div" :key="category">
                        <div
                            v-for="tag in Object.keys(recentTags[category])"
                            class="litter-tag"
                            :key="tag"
                            @click="addRecentTag(category, tag)"
                        ><p>{{ getTagName(category, tag) }}</p></div>
                </transition-group>
            </div>
        </div>

        <div>
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
        </div>

        <br>

        <button
            v-show="!admin"
            :disabled="checkTags"
            :class="button"
            @click="submit"
        >{{ $t('common.submit') }}</button>

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
import VueSimpleSuggest from 'vue-simple-suggest';
import 'vue-simple-suggest/dist/styles.css';
import { categories } from '../../extra/categories';
import { litterkeys } from '../../extra/litterkeys';
import ClickOutside from 'vue-click-outside';

export default {
    name: 'AddTags',
    components: {
        Tags,
        Presence,
        ProfileDelete,
        VueSimpleSuggest
    },
    directives: {
        ClickOutside
    },
    props: {
        'id': { type: Number, required: true },
        'admin': Boolean
    },
    created ()
    {
        if (this.$localStorage.get('recentTags'))
        {
            this.$store.commit('initRecentTags', JSON.parse(this.$localStorage.get('recentTags')));
        }
    },
    data ()
    {
        return {
            btn: 'button is-medium is-success',
            quantity: 1,
            processing: false,
            integers: Array.from({ length: 100 }, (_, i) => i + 1),
            autoCompleteStyle: {
                vueSimpleSuggest: 'position-relative',
                inputWrapper: '',
                defaultInput : 'input',
                suggestions: 'position-absolute list-group',
                suggestItem: 'list-group-item'
            },
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
         * @value category (smoking)
         */
        category: {
            get () {
                return {
                    key: this.$store.state.litter.category,
                    title:  this.$i18n.t('litter.categories.' + this.$store.state.litter.category)
                }
            },
            set (cat) {
                if (cat) {
                    this.$store.commit('changeCategory', cat.key);
                    this.$store.commit('changeTag', litterkeys[cat.key][0].key);
                    this.quantity = 1;
                }
            }
        },

        /**
         * Categories is imported and the key is used to return the translated title
         */
        categories ()
        {
            return categories.map(cat => {
                return {
                    key: cat,
                    title: this.$i18n.t('litter.categories.' + cat)
                };
            });
        },

        /**
         * Disable decrement if true
         */
        checkDecr ()
        {
            return this.quantity === 1;
        },

        /**
         * Disable increment if true
         */
        checkIncr ()
        {
            return this.quantity === 100;
        },

        /**
         * Disable button if true
         */
        checkTags ()
        {
            if (this.processing) return true;

            return Object.keys(this.$store.state.litter.tags).length === 0;
        },

        /**
         * Has the litter been picked up, or is it still there?
         */
        presence ()
        {
            return this.$store.state.litter.presence;
        },

        /**
         * The most recent tags the user has applied
         */
        recentTags ()
        {
            return this.$store.state.litter.recentTags;
        },

        /**
         * Get / Set the current tag (category -> tag)
         */
        tag: {
            get () {
                return {
                    key: this.$store.state.litter.tag,
                    title: this.$i18n.t(`litter.${this.category.key}.${this.$store.state.litter.tag}`)
                }
            },
            set (i) {
                if (i) {
                    this.$store.commit('changeTag', i.key);
                }
            }
        },

        /**
         * Litter tags for the selected category
         */
        tags ()
        {
            return litterkeys[this.category.key].map(tag => {
                return {
                    key: tag,
                    title: this.$i18n.t(`litter.${this.category.key}.${tag}`)
                };
            });
        },
    },
    methods: {

        /**
         * When a recent tag was applied, we update the category + tag
         *
         * Todo - Persist this to local browser cache with this.$localStorage.set('recentTags', keys)
         * Todo - Click and hold recent tag to update this.category and this.tag
         * Todo - Allow the user to pick their top tags in Settings and load them on this page by default
         *        (New - PopularTags, bottom-left)
         */
        addRecentTag (category, tag)
        {
            let quantity = 1;

            if (this.$store.state.litter.tags.hasOwnProperty(category))
            {
                if (this.$store.state.litter.tags[category].hasOwnProperty(tag))
                {
                    quantity = (this.$store.state.litter.tags[category][tag] + 1);
                }
            }

            this.$store.commit('addTag', {
                category,
                tag,
                quantity
            });
        },

        /**
         * Add or increment a tag
         *
         * tags: {
         *     smoking: {
         *         butts: 1
         *     }
         * }
         */
        addTag ()
        {
            this.$store.commit('addTag', {
                category: this.category.key,
                tag: this.tag,
                quantity: this.quantity
            });

            this.quantity = 1;

            this.$store.commit('addRecentTag', {
                category: this.category.key,
                tag: this.tag
            });

            this.$localStorage.set('recentTags', JSON.stringify(this.recentTags));
        },

        /**
         * When we click on the category input, the text is removed
         *
         * When we click outside, we reset it
         */
        clickOutsideCategory ()
        {
            this.$refs.categories.setText(
                this.$i18n.t(`litter.categories.${this.category.key}`)
            );
        },

        /**
         * When we click on the category input, the text is removed
         *
         * When we click outside, we reset it
         */
        clickOutsideTag ()
        {
            this.$refs.tags.setText(
                this.$i18n.t(`litter.${this.category.key}.${this.$store.state.litter.tag}`)
            );
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
         * Return translated category name for recent tags
         */
        getCategoryName (category)
        {
            return this.$i18n.t(`litter.categories.${category}`);
        },

        /**
         * Return translated litter.key name for recent tags
         */
        getTagName (category, tag)
        {
            return this.$i18n.t(`litter.${category}.${tag}`);
        },

        /**
         * The input field has been selected.
         * Show all suggestions, not just those limited by text.
         *
         * Clear the input field to allow the user to begin typing
         */
        onFocusCategories ()
        {
            this.$refs.categories.suggestions = this.$refs.categories.list;
            this.$refs.categories.setText('');
        },

        /**
         * The input field has been selected.
         * Show all suggestions, not just those limited by text.
         *
         * Clear the input field to allow the user to begin typing
         */
        onFocusTags ()
        {
            this.$refs.tags.suggestions = this.$refs.tags.list;
            this.$refs.tags.setText('');
        },

        /**
         * Hacky solution. Waiting on fix. https://github.com/KazanExpress/vue-simple-suggest/issues/311
         * An item has been selected from the list. Blur the input focus.
         */
        onSuggestion ()
        {
            this.$nextTick(function() {
                Array.prototype.forEach.call(document.getElementsByClassName('input'), function(el) {
                    el.blur();
                });
            });
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
        }
    }
};
</script>

<style lang="scss" scoped>
@import "../../styles/variables.scss";

    .hide-br {
        display: none;
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

    .recent-tags {
        display: flex;
        max-width: 50em;
        margin: auto;
        flex-wrap: wrap;
        overflow: auto;
        justify-content: center;
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

    .list-enter, .list-leave-to {
        opacity: 0;
        transform: translateX(30px);
    }


</style>
