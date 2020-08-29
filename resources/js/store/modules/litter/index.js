import { actions } from './actions'
// import { getters } from './getters'
import { mutations } from './mutations'

const state = {
	// Litter Data can be in en, es... on the frontend
	// but it is processed in english on the backend
	categoryNames: {}, // keys for Different Languages "Smoking", "Fumar"
    categories: [],
    // We use these keys to return the translated value
    categoryKeys: [
        'alcohol',
        'art',
        'brands',
        'coastal',
        'coffee',
        'dumping',
        'food',
        'industrial',
        'other',
        'sanitary',
        'softdrinks',
        'smoking'
    ],
    category: 11, // currently selected category. Use categoryKeys[category] to get key
    currentItem: "",
    hasAddedNewTag: false, // Has the admin added a new tag yet? If FALSE, disable "Update With New Tags button"
    litterlang: null, //  "Cigarette Butts, Lighters" in language
    presence: null, // true = remaining
    litterKeys: [], // items for currently selected category index
    items: {},
    language: "en",
    loading: false,
    photos: {}, // paginated photos object
    tags: {
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
    },
    submitting: false
}

export const litter = {
	state,
	actions,
	// getters,
	mutations
}
