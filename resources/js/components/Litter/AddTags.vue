<template>
    <div>
        <!-- Search all tags -->
        <div class="flex flex-column-mobile">
            <div class="is-flex-grow-3 search-container">
                <div class="select is-fullwidth">
                    <vue-simple-suggest
                        ref="search"
                        display-attribute="title"
                        value-attribute="key"
                        :filter-by-query="true"
                        :list="allTags"
                        :min-length="1"
                        :max-suggestions="0"
                        mode="input"
                        :styles="autoCompleteStyle"
                        :placeholder="$t('tags.search-all-tags')"
                        :controls="{
                            autocomplete: [32],
                        }"
                        @focus="onFocusSearch"
                        @select="search"
                    />
                </div>
            </div>
            <div v-if="showCustomTags" class="is-flex-grow-1">
                <input
                    class="input is-fullwidth"
                    :class="customTagsError ? 'is-danger' : ''"
                    ref="customTagsInput"
                    type="text"
                    min="3"
                    max="100"
                    :placeholder="$t('tags.search-custom-tags')"
                    @focus="onFocusCustomTags"
                    @keydown.enter.exact="searchCustomTag"
                >
                <p v-if="customTagsError" class="help has-text-left">{{ customTagsError }}</p>
            </div>
        </div>

        <div class="control has-text-centered mt-4">

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
                v-show="! admin && this.id !== 0"
                :disabled="!hasAddedTags"
                :class="button"
                type="submit"
                @click.prevent="submit"
            >
                <span class="tooltip-text is-size-6">Ctrl (⌘) + Enter</span>
                {{ $t('common.submit') }}
            </button>

            <!-- Only show these on mobile <= 768px, and when not using AddManyTagsToPhotos (id = 0) -->
            <div class="show-mobile" v-show="this.id !== 0">
                <br>
                <tags :photo-id="id"/>

                <div class="box custom-buttons">
                    <profile-delete
                        :photoid="id"
                    />
                    <presence
                        :itemsr="true"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import Tags from './Tags.vue';
import Presence from './Presence.vue';
import ProfileDelete from './ProfileDelete.vue';
import VueSimpleSuggest from 'vue-simple-suggest';
import 'vue-simple-suggest/dist/styles.css';
import { categories } from '../../extra/categories';
import { litterkeys } from '../../extra/litterkeys';
import ClickOutside from 'vue-click-outside';

// When this.id === 0, we are using AddManyTagsToManyPhotos
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
        'admin': Boolean,
        'annotations': { type: Boolean, required: false },
        'isVerifying': { type: Boolean, required: false },
        'showCustomTags': { type: Boolean, required: false, default: true }
    },
    mounted ()
    {
        if (this.$localStorage.get('recentTags'))
        {
            this.$store.commit('initRecentTags', JSON.parse(this.$localStorage.get('recentTags')));
        }

        if (this.$localStorage.get('recentCustomTags'))
        {
            this.$store.commit('initRecentCustomTags', JSON.parse(this.$localStorage.get('recentCustomTags')));
        }

        this.$store.commit('setCustomTagsError', '');

        window.addEventListener('keydown', this.listenForSearchFocusEvent);
        window.addEventListener('keydown', this.listenForSubmitEvent);
        window.addEventListener('keydown', this.listenForArrowKeys);

        this.$nextTick(function () {
            this.$refs.search.input.focus();
        });
    },
    data ()
    {
        return {
            btn: 'button is-medium is-success tooltip',
            quantity: 1,
            processing: false,
            integers: Array.from({ length: 100 }, (_, i) => i + 1),
            autoCompleteStyle: {
                vueSimpleSuggest: 'position-relative',
                inputWrapper: '',
                defaultInput : 'input',
                suggestions: 'position-absolute list-group search-fixed-height',
                suggestItem: 'list-group-item has-text-left'
            }
        };
    },
    computed: {
        /**
         * Litter tags for all categories, used by the Search field
         */
        allTags ()
        {
            let results = [];

            categories.forEach(cat => {
                if (litterkeys.hasOwnProperty(cat)) {
                    results = [
                        ...results,
                        ...litterkeys[cat].map(tag => {
                            return {
                                key: cat + ':' + tag,
                                title: this.$i18n.t('litter.categories.' + cat) + ': ' + this.$i18n.t(`litter.${cat}.${tag}`)
                            };
                        })
                    ];
                }
            });

            // Merge recent custom tags with historic custom tags
            // and filter out duplicates
            const customTags = [...new Set([
                ...this.recentCustomTags,
                ...this.previousCustomTags
            ])];

            results = results.concat(customTags.map(tag => {
                return {
                    key: 'custom-' + tag,
                    title: tag,
                    custom: true
                };
            }))

            return results;
        },

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
                    this.$store.commit('changeTag', litterkeys[cat.key][0]);
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
         * Get / Set the current custom tag
         */
        customTag: {
            get () {
                return this.$store.state.litter.customTag;
            },
            set (i) {
                if (i) this.$store.commit('changeCustomTag', i.trim());
            }
        },

        // /**
        //  * When adding tags to a bounding box,
        //  *
        //  * We should disable the addTag button if a box is not selected
        //  */
        // disabled ()
        // {
        //     if (! this.annotations) return false;
        //
        //     let disable = true;
        //
        //     this.$store.state.bbox.boxes.forEach(box => {
        //         if (box.active) disable = false;
        //     });
        //
        //     return disable;
        // },

        /**
         * Disable increment if true
         */
        checkIncr ()
        {
            return this.quantity === 100;
        },

        /**
         * The latest error related to custom tags
         */
        customTagsError ()
        {
            return this.$store.state.litter.customTagsError;
        },

        /**
         * Disable button if false
         */
        hasAddedTags ()
        {
            if (this.processing) return false;

            let tags = this.$store.state.litter.tags;
            let customTags = this.$store.state.litter.customTags;
            let hasTags = tags && tags[this.id] && Object.keys(tags[this.id]).length;
            let hasCustomTags = customTags && customTags[this.id] && customTags[this.id].length;

            return hasTags || hasCustomTags;
        },

        /**
         * All the custom tags that this user has submitted
         */
        previousCustomTags ()
        {
            return this.$store.state.photos.previousCustomTags;
        },

        /**
         * The most recent tags the user has applied
         */
        recentTags ()
        {
            return this.$store.state.litter.recentTags;
        },

        /**
         * The most recent tags the user has applied
         */
        recentCustomTags ()
        {
            return this.$store.state.litter.recentCustomTags;
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
         * Add or increment a tag
         *
         * Also used by Admin/BBox to add annotations to an image
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
                photoId: this.id,
                category: this.category.key,
                tag: this.tag.key,
                quantity: this.quantity
            });

            this.quantity = 1;

            this.$store.commit('addRecentTag', {
                category: this.category.key,
                tag: this.tag.key
            });

            this.$localStorage.set('recentTags', JSON.stringify(this.recentTags));
        },

        addCustomTag (tag)
        {
            this.customTag = tag;

            this.$store.commit('addCustomTag', {
                photoId: this.id,
                customTag: this.customTag
            });

            this.$localStorage.set('recentCustomTags', JSON.stringify(this.recentCustomTags));
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
         * Change to previous/next image if they exist
         */
        listenForArrowKeys (event)
        {
            if (event.keyCode === 37)
            {
                if (this.$store.state.photos.paginate?.prev_page_url)
                {
                    this.$store.dispatch('PREVIOUS_IMAGE');
                }
            }

            if (event.keyCode === 39)
            {
                if (this.$store.state.photos.paginate?.next_page_url)
                {
                    this.$store.dispatch('NEXT_IMAGE');
                }
            }
        },

        /**
         * If the user hits Ctrl + Enter, submit the tags
         */
        listenForSubmitEvent (event)
        {
            if (
                (event.ctrlKey || event.metaKey) &&
                event.key.toLowerCase() === 'enter' &&
                this.hasAddedTags &&
                (! this.admin && this.id !== 0)
            ) {
                event.preventDefault();
                event.stopPropagation();
                this.submit();
            }
        },

        /**
         * If the user hits Ctrl + Space bar, search all tags
         */
        listenForSearchFocusEvent (event)
        {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === ' ') {
                this.$refs.search.input.focus();
                event.preventDefault();
            }
        },

        /**
         * Clear the input field to allow the user to begin typing
         */
        onFocusSearch ()
        {
            this.$refs.search.setText('');
        },

        /**
         * Clear the custom tags input field to allow the user to begin typing
         */
        onFocusCustomTags ()
        {
            this.$refs.customTagsInput.value = '';
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
         *
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
         * Sets the category and tag from the search results
         */
        search (input)
        {
            if (input.custom) {
                this.addCustomTag(input.title);
            } else {
                let searchValues = input.key.split(':');

                this.category = {key: searchValues[0]};
                this.tag = {key: searchValues[1]};

                this.addTag();
            }

            this.$nextTick(function () {
                this.onFocusSearch();
            });
        },

        /**
         * Adds a new custom tag
         */
        searchCustomTag ()
        {
            let customTag = this.$refs.customTagsInput.value;

            if (customTag.length < 3) {
                this.$store.commit('setCustomTagsError', this.$i18n.t('tags.custom-tags-min'));
                return;
            }

            if (customTag.length > 100) {
                this.$store.commit('setCustomTagsError', this.$i18n.t('tags.custom-tags-max'));
                return;
            }

            this.addCustomTag(customTag);

            this.$nextTick(function () {
                this.onFocusCustomTags();
            });
        },

        /**
         * Submit the image for verification
         *
         * add_tags_to_image => users
         * add_boxes_to_image => admins
         * verify_boxes => admins
         *
         * litter/actions.js
         */
        async submit ()
        {
            this.processing = true;

            let action = '';

            if (this.annotations)
            {
                action = this.isVerifying
                    ? 'VERIFY_BOXES'
                    : 'ADD_BOXES_TO_IMAGE'
            }
            else
            {
                action = 'ADD_TAGS_TO_IMAGE';
            }

            await this.$store.dispatch(action);

            this.processing = false;
        }
    },

    destroyed ()
    {
        window.removeEventListener('keydown', this.listenForArrowKeys);
        window.removeEventListener('keydown', this.listenForSearchFocusEvent);
        window.removeEventListener('keydown', this.listenForSubmitEvent);
    }
};
</script>

<style lang="scss" scoped>

    @import "../../styles/variables.scss";

    .hide-br {
        display: none;
    }

    .is-flex-grow-3 {
        flex-grow: 3;
    }

    .is-flex-grow-1 {
        flex-grow: 1;
    }

    .search-container {
        margin-right: 4px;
    }

    .custom-buttons {
        display: flex;
        gap: 1rem;
        justify-content: space-between;
        align-items: center;
        flex-direction: row;
    }

    @media (max-width: 500px)
    {
        .hide-br {
            display: block;
        }
        .v-select {
            margin-top: 10px;
        }
        .flex-column-mobile {
            flex-direction: column;
        }
        .search-container {
            margin-right: 0;
            margin-bottom: 4px;
        }
        .custom-buttons {
            flex-direction: column;
        }
    }

    @media (min-width: 768px)
    {
        .show-mobile {
            display: none !important;
        }
    }

    button:focus {
        outline: 2px solid lightskyblue;
        outline-offset: 2px;
    }

</style>
