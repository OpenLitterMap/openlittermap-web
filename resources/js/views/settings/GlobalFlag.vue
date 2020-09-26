<template>
	<div style="padding-left: 1em; padding-right: 1em;">
        <h1 class="title is-4">{{ $t('settings.globalFlag.show-flag') }}</h1>
        <hr>
        <br>

        <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

        <div v-else class="columns">
            <div class="column is-offset-1">

                <p class="title is-5 mb20 green">{{ $t('settings.globalFlag.top-10') }}</p>

                <div v-show="this.$store.state.user.user.global_flag" class="mb20">
                    <p class="strong">{{ $t('settings.globalFlag.global-top-10-challenge') }}: {{ this.getSelected() }}</p>
                </div>

                <p class="mb20">{{ $t('settings.globalFlag.action-select') }}</p>
                <p class="mb20">{{ $t('settings.globalFlag.select-country') }}</p>

                <vue-simple-suggest
                    ref="vss"
                    :filter-by-query="true"
                    :list="getCountries()"
                    :min-length="0"
                    :max-suggestions="0"
                    mode="select"
                    :placeholder=" $t('settings.globalFlag.select-country')"
                    :styles="autoCompleteStyle"
                    v-model="country"
                    @focus="onFocus()"
                    @suggestion-click="onSuggestion()"
                />

                <button
                    :disabled="processing"
                    :class="button"
                    @click="save"
                >{{ $t('settings.globalFlag.save-flag') }}</button>
            </div>
        </div>
    </div>
</template>

<script>
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'

import VueSimpleSuggest from 'vue-simple-suggest'
import 'vue-simple-suggest/dist/styles.css'

export default {
    name: 'GlobalFlag',
    components: { Loading, VueSimpleSuggest },
    async created ()
    {
        this.loading = true;

        await this.$store.dispatch('GET_COUNTRIES_FOR_FLAGS');

        this.loading = false;
    },
    data ()
    {
        return {
            btn: 'button mt1 is-primary is-medium',
            country: '',
            processing: false,
            loading: true,
            autoCompleteStyle: {
                vueSimpleSuggest: "position-relative width-50",
                inputWrapper: "",
                defaultInput : "input",
                suggestions: "position-absolute list-group z-1000 custom-class-overflow width-50",
                suggestItem: "list-group-item"
            }
        };
    },
    computed: {

        /**
         * Dynamic button class
         */
        button ()
        {
            return this.processing ? this.btn + ' is-loading' : this.btn;
        },

        /**
         *
         */
        countries ()
        {
            return this.$store.state.user.countries;
        }
    },
    methods: {

        /**
         * List of available countries to choose flag from
         */
        getCountries ()
        {
            return Object.values(this.countries);
        },

        /**
         * Currently selected flag, if choosen
         */
        getSelected ()
        {
            if (this.$store.state.user.user.global_flag) return this.countries[this.$store.state.user.user.global_flag];

            return false;
        },

        /**
         * Show all suggestions (not just ones filtered by text input)
         */
        onFocus ()
        {
            this.$refs.vss.suggestions = this.$refs.vss.list;
        },

        /**
         * Hacky solution. Waiting on fix. https://github.com/KazanExpress/vue-simple-suggest/issues/311
         * An item has been selected from the list. Blur the input focus.
         */
        onSuggestion ()
        {
            this.$nextTick(function() {
                Array.prototype.forEach.call(document.getElementsByClassName('input'), function(el) {
                    el.blur();
                });
            });
        },

        /**
         * Dispatch request to save selected flag
         */
        async save ()
        {
            this.processing = true;

            let selected = Object.keys(this.countries).find(key => this.countries[key] === this.country);

            await this.$store.dispatch('UPDATE_GLOBAL_FLAG', selected);

            this.processing = false;
        }
    }
}
</script>

<style lang="scss">

	.green {
		color: #2ecc71;
	}

	.strong {
		font-weight: 600;
	}

	.width-50 {
		width: 50%;
	}
</style>
