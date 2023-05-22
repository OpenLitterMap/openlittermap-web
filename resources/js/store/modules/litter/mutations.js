import { categories } from '../../../extra/categories'
import { litterkeys } from '../../../extra/litterkeys'
import { init } from './init'
import i18n from "../../../i18n";
// import { MAX_RECENTLY_TAGS } from '../../../constants'

export const mutations = {

    /**
     * Add a tag that was just used, so the user can easily use it again on the next image
     */
    addRecentTag (state, payload)
    {
        let tags = Object.assign({}, state.recentTags);

        tags = {
            ...tags,
            [payload.category]: {
                ...tags[payload.category],
                [payload.tag]: 1 // quantity not important
            }
        }

        // Sort them alphabetically by translated values
        const sortedTags = tags;
        Object.entries(tags).forEach(([category, categoryTags]) => {
            const sorted = Object.entries(categoryTags).sort(function (a, b) {
                const first = i18n.t(`litter.${category}.${a[0]}`);
                const second = i18n.t(`litter.${category}.${b[0]}`);
                return first === second ? 0 : first < second ? -1 : 1;
            });
            sortedTags[category] = Object.fromEntries(sorted);
        });

        state.recentTags = sortedTags;
    },

    /**
     * Add a Tag to a photo.
     *
     * This will set Photo.id => Category => Tag.key: Tag.quantity
     *
     * state.tags = {
     *     photo.id = {
     *         category.key = {
     *             tag.key: tag.quantity
     *         }
     *     }
     * }
     */
    addTag (state, payload)
    {
        state.hasAddedNewTag = true; // Enable the Update Button

        let tags = Object.assign({}, state.tags);

        tags = {
            ...tags,
            [payload.photoId]: {
                ...tags[payload.photoId],
                [payload.category]: {
                    ...(tags[payload.photoId] ? tags[payload.photoId][payload.category] : {}),
                    [payload.tag]: payload.quantity
                }
            }
        };

        state.tags = tags;
    },

    /**
     * Add a Custom Tag to a photo.
     */
    addCustomTag (state, payload)
    {
        let tags = Object.assign({}, state.customTags);

        if (!tags[payload.photoId]) {
            tags[payload.photoId] = [];
        }

        // Case-insensitive check for existing tags
        if (tags[payload.photoId].find(tag => tag.toLowerCase() === payload.customTag.toLowerCase()) !== undefined)
        {
            state.customTagsError = i18n.t('tags.tag-already-added');
            return;
        }

        if (tags[payload.photoId].length >= 3)
        {
            state.customTagsError = i18n.t('tags.tag-limit-reached');
            return;
        }

        tags[payload.photoId].unshift(payload.customTag);

        // Also add this tag to the recent custom tags
        if (state.recentCustomTags.indexOf(payload.customTag) === -1)
        {
            state.recentCustomTags.push(payload.customTag);
            // Sort them alphabetically
            state.recentCustomTags.sort(function(a, b) {
                return a === b ? 0 : a < b ? -1 : 1;
            });
        }

        // And indicate that a new tag has been added
        state.hasAddedNewTag = true; // Enable the Update Button
        state.customTagsError = ''; // Clear the error
        state.customTags = tags;
    },

    /**
     * Clear the tags object (When we click next/previous image on pagination)
     */
    clearTags (state, photoId)
    {
        if (photoId !== null) {
            delete state.tags[photoId];
            delete state.customTags[photoId];
        } else {
            state.tags = Object.assign({});
            state.customTags = Object.assign({});
        }

        state.hasAddedNewTag = false; // Disable the Admin Update Button
    },

    /**
     * Update the currently selected category
     * Update the items for that category
     * Select the first item
     *
     * payload = key "smoking"
     */
    changeCategory (state, payload)
    {
        state.category = payload;
    },

    /**
     * Change the currently selected tag
     *
     * One category has many tags
     */
    changeTag (state, payload)
    {
        state.tag = payload;
    },

    /**
     * Change the currently selected custom tag
     */
    changeCustomTag (state, payload)
    {
        state.customTag = payload;
    },

    setCustomTagsError (state, payload)
    {
        state.customTagsError = payload;
    },

    /**
     * Data from the user to verify
     * map database column name to frontend string
     */
    initAdminItems (state, payload)
    {
        let tags = {};

        categories.map(category => {
            if (payload.hasOwnProperty(category) && payload[category])
            {
                litterkeys[category].map(item => {

                    if (payload[category][item])
                    {
                        tags = {
                            ...tags,
                            [payload.id]: {
                                ...tags[payload.id],
                                [category]: {
                                    ...(tags[payload.id] ? tags[payload.id][category] : {}),
                                    [item]: payload[category][item]
                                }
                            }
                        };
                    }
                });
            }
        });

        state.tags = tags;
    },

    /**
     * Data from the user to verify
     * map database column name to frontend string
     */
    initAdminCustomTags (state, payload)
    {
        state.customTags = {
            [payload.id]: payload.custom_tags.map(t => t.tag)
        };
    },

    /**
     * When AddTags is created, we check localStorage for the users recentTags
     */
    initRecentTags (state, payload)
    {
        state.recentTags = payload;
    },

    /**
     * When AddTags is created, we check localStorage for the users recentCustomTags
     */
    initRecentCustomTags (state, payload)
    {
        state.recentCustomTags = payload;
    },

    /**
     * Remove a tag from a category
     * If category is empty, delete category
     */
    removeTag (state, payload)
    {
        let tags = Object.assign({}, state.tags);

        delete tags[payload.photoId][payload.category][payload.tag_key];

        if (Object.keys(tags[payload.photoId][payload.category]).length === 0)
        {
            delete tags[payload.photoId][payload.category];
        }

        state.tags = tags;
    },

    /**
     * Remove a recent tag
     * If category is empty, delete category
     */
    removeRecentTag (state, payload)
    {
        let tags = Object.assign({}, state.recentTags);

        delete tags[payload.category][payload.tag];

        if (Object.keys(tags[payload.category]).length === 0)
        {
            delete tags[payload.category];
        }

        state.recentTags = tags;
    },

    /**
     * Admin
     * Change photo[category][tag] = 0;
     */
    resetTag (state, payload)
    {
        let tags = Object.assign({}, state.tags);

        tags[payload.photoId][payload.category][payload.tag_key] = 0;

        state.tags = tags;
        state.hasAddedNewTag = true; // activate update_with_new_tags button
    },

    /**
     * Remove a custom tag
     */
    removeCustomTag (state, payload)
    {
        let tags = Object.assign({}, state.customTags);

        tags[payload.photoId] = tags[payload.photoId].filter(tag => tag !== payload.customTag);

        state.customTags = tags;
        state.hasAddedNewTag = true; // activate update_with_new_tags button
    },

    /**
     * Remove a recent custom tag
     */
    removeRecentCustomTag (state, payload)
    {
        let tags = Object.assign([], state.recentCustomTags);

        tags = tags.filter(tag => tag !== payload);

        state.recentCustomTags = tags;
    },

    /**
     * Reset the user object (when we logout)
     */
    resetState (state)
    {
        Object.assign(state, init);
    },

    /**
     * Reset empty state
     */
    resetLitter (state)
    {
        state.categories = {
            'Alcohol': {},
            'Art': {},
            'Brands': {},
            'Coastal': {},
            'Coffee': {},
            'Dumping': {},
            'Drugs': {},
            'Food': {},
            'Industrial': {},
            'Other': {},
            'Sanitary': {},
            'Smoking': {},
            'SoftDrinks': {},
            'TrashDog': {}
        }
    },

    /**
     * Set all existing items to 0
     *
     * Admin @ reset
     */
    setAllTagsToZero (state, photoId)
    {
        let original_tags = Object.assign({}, state.tags[photoId]);

        Object.entries(original_tags).map(keys => {

            let category = keys[0]; // alcohol
            let category_tags = keys[1]; // { cans: 1, beerBottle: 2 }

            if (Object.keys(original_tags[category]).length > 0)
            {
                Object.keys(category_tags).map(tag => {
                    original_tags[category][tag] = 0;
                });
            }
        });

        state.tags = {
            ...state.tags,
            [photoId]: original_tags
        };
    },

    /**
     * When the user object is created (page refresh or login), we set the users default presence value here
     * If the litter is picked up, this value will be 'true'
     */
    set_default_litter_picked_up (state, payload)
    {
        state.pickedUp = payload;
    },

    /**
     *
     */
    setLang (state, payload)
    {
        state.categoryNames = payload.categoryNames;
        state.currentCategory = payload.currentCategory;
        state.currentItem = payload.currentItem;
        state.litterlang = payload.litterlang;
    },

    /**
     *
     */
    togglePickedUp (state)
    {
        state.pickedUp = !state.pickedUp;
    },
    /**
     *
     */
    toggleSubmit (state)
    {
        state.submitting = !state.submitting;
    }
};
