import { categories } from '../../../extra/categories'
import { litterkeys } from '../../../extra/litterkeys'
import { init } from './init'
import { MAX_RECENTLY_TAGS } from '../../../constants'

export const mutations = {

    /**
     * Add a tag that was just used, so the user can easily use it again on the next image
     */
    addRecentTag (state, payload)
    {
        const tags = state.recentTags.length === MAX_RECENTLY_TAGS
            ? state.recentTags.slice(1, MAX_RECENTLY_TAGS)
            : state.recentTags;

        const isTagExisted = tags.find(({ item }) => item.key === payload.item.key);

        if (isTagExisted) return;

        state.recentTags = [...tags, payload];
    },

    /**
     * Add a Tag.
     *
     * This will set Category => Tag.key: Tag.quantity
     *
     * state.tags = {
     *     category.key = {
     *         tag.key: tag.quantity
     *     }
     * }
     */
    addTag (state, payload)
    {
        state.hasAddedNewTag = true; // Enable the Update Button

        let tags = Object.assign({}, state.tags);

        tags = {
            ...tags,
            [payload.category.key]: {
                ...tags[payload.category.key],
                [payload.item.key]: payload.quantity
            }
        };

        state.tags = tags;
    },

    /**
     * Clear the tags object (When we click next/previous image on pagination)
     */
    clearTags (state)
    {
        state.tags = Object.assign({});
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
     * Change the currently selected item
     *
     * One category has many items
     */
    changeItem (state, payload)
    {
        state.item = payload;
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

                    if (payload[category][item.key])
                    {
                        tags = {
                            ...tags,
                            [category]: {
                                ...tags[category],
                                [item.key]: payload[category][item.key]
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
     * Remove a tag from a category
     * If category is empty, delete category
     */
    removeTag (state, payload)
    {
        let tags = Object.assign({}, state.tags);

        delete tags[payload.category][payload.tag_key];

        if (Object.keys(tags[payload.category]).length === 0)
        {
            delete tags[payload.category];
        }

        state.tags = tags;
    },

    /**
     * Admin
     * Change category[tag] = 0;
     */
    resetTag (state, payload)
    {
        let tags = Object.assign({}, state.tags);

        tags[payload.category][payload.tag_key] = 0;

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
    setAllItemsToZero (state)
    {
        let original_tags = Object.assign({}, state.tags);

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

        state.tags = original_tags;
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
