import { createI18n } from 'vue-i18n';
import { langs } from './langs';

export default createI18n({
    locale: 'en',
    fallbackLocale: 'en',
    messages: langs,
});
