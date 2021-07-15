import { categories } from '../../../extra/categories'
import { litterkeys } from '../../../extra/litterkeys'
import { init } from './init'
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

        state.recentTags = tags;
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
     * Clear the tags object (When we click next/previous image on pagination)
     */
    clearTags (state, photoId)
    {
        if (photoId !== null) {
            delete state.tags[photoId];
        } else {
            state.tags = Object.assign({});
        }
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
     * The users default presence of the litter they pick up
     * Some people leave it there, others usually pick it up
     */
    initPresence (state, payload)
    {
        state.presence = payload;
    },

    /**
     * When AddTags is created, we check localStorage for the users recentTags
     */
    initRecentTags (state, payload)
    {
        state.recentTags = payload;
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
        // state.items = {};
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
     * Presence = Is the litter picked up, or is it still there
     */
    set_default_litter_presence (state, payload)
    {
        state.presence = payload;
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
    togglePresence (state)
    {
        state.presence = !state.presence;
    },
    /**
     *
     */
    toggleSubmit (state)
    {
        state.submitting = !state.submitting;
    }
};
