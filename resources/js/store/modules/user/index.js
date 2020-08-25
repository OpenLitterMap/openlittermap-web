import { actions } from './actions'
import { mutations } from './mutations'

const state = {
	auth: false,
    errorLogin: '',
    errors: {}
};

export const user = {
	state,
	actions,
	mutations
}
