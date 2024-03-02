import { actions } from './actions'
import { mutations } from './mutations'

const state = {
    filterDateFrom: '',
    filterDateTo: '',
    filterResultString: '',
    filterTag: '',
    filterCustomTag: '',
    paginationAmount: 25
};

export const filterPhotos = {
    state,
    actions,
    mutations
};
