import Vue from 'vue'
import Vuex from 'vuex'

import { admin } from './modules/admin'
import { createaccount } from './modules/createaccount'
import { donate } from './modules/donate'
import { globalmap } from './modules/globalmap'
import { locations } from './modules/locations'
import { litter } from './modules/litter'
import { modal } from './modules/modal'
import { payments } from './modules/payments'
import { photos } from './modules/photos'
import { subscriber} from './modules/subscriber'
import { user } from './modules/user'

Vue.use(Vuex)

export default new Vuex.Store({
    modules: {
        admin,
        createaccount,
        donate,
        globalmap,
        locations,
        litter,
        modal,
        payments,
        photos,
        subscriber,
        user
    }
});
