import { actions } from './actions'
// import { getters } from './getters'
import { mutations } from './mutations'

const state = {
	// Litter Data can be in en, es... on the frontend
	// but it is processed in english on the backend
	categoryNames: {}, // keys for Different Languages "Smoking", "Fumar" 
	currentCategory: "", // Smoking / Fumar
	litterlang: null, //  "Cigarette Butts, Lighters" in language 
	currentItem: "",
	presence: null, // true = remaining
	items: {},
	language: "en",
	loading: false,
	submitting: false,
	categories: {
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
	// AdminAdd.vue
	hasAddedNewTag: false // Has the admin added a new tag yet? If FALSE, disable "Update With New Tags button"
}

export const litter = {
	state,
	actions,
	// getters,
	mutations
}