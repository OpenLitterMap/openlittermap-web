import { actions } from './actions'
import { mutations } from './mutations'

const state = {
	auth: false,
    countries: {}, // options for flags => { ie: "Ireland" }
    errorLogin: '',
    errors: {},
    user: {}
};

export const user = {
	state,
	actions,
	mutations
}
