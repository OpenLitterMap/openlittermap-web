import Vue from 'vue'
import i18n from '../../../i18n'
import { categories } from '../../../extra/categories'
import { litterkeys } from '../../../extra/litterkeys'

export const mutations = {

    /**
     * Add a Tag.
     *
     * This will set Category => Tag.id => Tag.key, Tag.quantity
     *
     * state.tags = {
     *     category.key = {
     *         tag.id: {
     *             tag.key,
     *             tag.quantity
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
            [payload.category.key]: {
                ...tags[payload.category.key],
                [payload.item.id]: {
                    key: payload.item.key,
                    q: payload.quantity,
                }
            }
        };

        state.tags = tags;
    },

    /**
     *
     */
    adminCreated (state, payload)
    {
        Vue.set(state.items, payload.item, payload.quantity);
        Vue.set(state.categories[payload.category], payload.item, payload.quantity);
    },

    /**
     *
     */
    clearItems (state)
    {
        state.items = {};
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
     * Change the currently seleted item. Category -> item
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
        categories.map(category => {
            if (payload.hasOwnProperty(category))
            {
                Object.entries(payload[category]).map(items => {
                    if (items[1])
                    {
                        let name = litterkeys[items[0]];
                        if (name)
                        {
                            Vue.set(state.items, name, items[1]);
                            Vue.set(state.categories[category], name, items[1]);
                        }
                    }
                });
            }
        });
    },

    /**
     * When AddTags has been created, we need to initialize the correct translated values
     */
    initLitter (state, payload)
    {
        console.log('initLitter', payload);
    },

    /**
     *
     */
    initPresence (state, payload)
    {
        state.presence = payload;
    },

    /**
     *
     */
    removeItem (state, payload)
    {
        Vue.delete(state.items, payload.item);
        Vue.delete(state.categories[payload.category], payload.item);
    },

    /**
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
