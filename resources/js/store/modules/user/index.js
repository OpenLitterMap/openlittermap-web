import { init } from './init';
import { actions } from './actions';
import { getters } from './getters';
import { mutations } from './mutations';

import { filterPhotos } from './filterPhotos';

const state = Object.assign({}, init);

export const user = {
    state,
    actions,
    getters,
    mutations,
    modules: {
        filterPhotos
    }
};
