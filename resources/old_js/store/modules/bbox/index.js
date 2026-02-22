import { actions } from './actions'
import { mutations } from './mutations'

const state = {
    boxes: [
        {
            id: 1,
            width: 100,
            height: 100,
            top: 0,
            left: 0,
            active: true,
            category: null,
            tag: null,
            brand: null,
            showLabel: false,
            hidden: false
        }
    ],
    brands: [],
    hasChanged: false, // for verification
    selectedBrandIndex: null,
    totalBoxCount: 0,
    usersBoxCount: 0
};

export const bbox = {
    state,
    actions,
    mutations
};
