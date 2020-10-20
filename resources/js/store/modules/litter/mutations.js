import Vue from 'vue'
import i18n from '../../../i18n'
import { categories } from '../../../extra/categories'
import { litterkeys } from '../../../extra/litterkeys'
import { init } from './init'

export const mutations = {

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
     * todo - refactor
     */
    adminCreated (state, payload)
    {
        Vue.set(state.items, payload.item, payload.quantity);
        Vue.set(state.categories[payload.category], payload.item, payload.quantity);
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
     */
    changeCategory (state, payload)
    {
        state.category = payload;

        state.items = litterkeys[payload.key];

        state.item = {
            id: litterkeys[payload.key][0].id,
            key: litterkeys[payload.key][0].key,
            title: i18n.t('litter.' + payload.key + '.' + litterkeys[payload.key][0].key)
        }
    },

    /**
     * Change the currently selected item. Category -> item
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
        console.log({ payload });
        let tags = {};

        categories.map(category => {
            if (payload.hasOwnProperty(category.key) && payload[category.key])
            {
                litterkeys[category.key].map(item => {

                    if (payload[category.key][item.key])
                    {
                        tags = {
                            ...tags,
                            [category.key]: {
                                ...tags[category.key],
                                [item.key]: payload[category.key][item.key]
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
     * Remove a tag from tags
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
        let categories = Object.assign({}, state.categories);

        categories[payload.category][payload.tag] = 0;

        state.categories = categories;
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
        state.items = {};
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
    */
    setAllItemsToZero (state)
   {
        let categories = Object.assign({}, state.categories);

        Object.entries(categories).map(keys => {
            let category = keys[0];
            let tags = keys[1]; // { Cans: 1, Bottles: 2 }

            if (Object.keys(categories[category]).length > 0) {
                Object.entries(tags).map(tag => {
                    categories[category][tag[0]] = 0;
                    state.items[tag[0]] = 0;
                });
            }
        });

        state.categories = Object.assign({}, categories);
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
        state.presence = ! state.presence;
    },

    /**
     *
     */
    toggleSubmit (state)
    {
  	    state.submitting = ! state.submitting;
    }

}
