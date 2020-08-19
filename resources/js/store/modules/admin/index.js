import { actions } from './actions'
import { mutations } from './mutations'

const state = {
	id: '',
	filename: '',
	items: {},
	loading: true, // spinner
};

export const admin = {
	state,
	actions,
	mutations
};