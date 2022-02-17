import { init } from './init';
import { actions } from './actions';
import { getters } from './getters';
import { mutations } from './mutations';

const state = Object.assign({}, init);

import { public_profile } from './public_profile';

export const user = {
    state,
    actions,
    getters,
    mutations,
    modules: {
	    public_profile
    }
};
