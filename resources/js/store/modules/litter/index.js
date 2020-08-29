import { actions } from './actions'
// import { getters } from './getters'
import { mutations } from './mutations'

const state = {
	// Litter Data can be in en, es... on the frontend
	// but it is processed in english on the backend
    category: {}, // currently selected category.
    hasAddedNewTag: false, // Has the admin added a new tag yet? If FALSE, disable "Update With New Tags button"
    presence: null, // true = remaining
    item: {}, // currently selected item
    items: [], // items for the currently selected category
    loading: false,
    photos: {}, // paginated photos object
    tags: {}, // added tags go here -> { smoking: { butts: q, lighters: q }, alcohol: { beer_cans: q } ... };
    // oldtags: {
    //     'Alcohol': {},
    //     'Art': {},
    //     'Brands': {},
    //     'Coastal': {},
    //     'Coffee': {},
    //     'Dumping': {},
    //     'Drugs': {},
    //     'Food': {},
    //     'Industrial': {},
    //     'Other': {},
    //     'Sanitary': {},
    //     'Smoking': {},
    //     'SoftDrinks': {},
    //     'TrashDog': {}
    // },
    submitting: false
}

export const litter = {
	state,
	actions,
	// getters,
	mutations
}
