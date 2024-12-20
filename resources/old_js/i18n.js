import Vue from 'vue'
import VueI18n from 'vue-i18n'
Vue.use(VueI18n)

import { langs } from './langs'

export default new VueI18n({
    locale: 'en',
    fallbackLocale: 'en',
    messages: langs
});