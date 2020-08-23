<template>
	<div style="padding-left: 1em; padding-right: 1em;">
			<h1 class="title is-4">Show Country Flag</h1>
			<hr>
			<br>
			<div class="columns">
				<div class="column is-offset-1">

					<p class="title is-5 mb20 green">Top 10 Global OpenLitterMap Leaders only!</p>

					<div v-show="this.user.global_flag" class="mb20">
						<p class="strong">Selected: {{ this.getSelected() }}</p>
					</div>

					<p class="mb20">If you can make the top 10, you can represent your country!</p>
					<p class="mb20">Type or scroll to select from the list</p>

					<vue-simple-suggest
					    :filter-by-query="true"
					    :list="getCountries()"
					    :min-length="0"
					    :max-suggestions="0"
					    mode="select"
					    placeholder="Select your country"
					    :styles="autoCompleteStyle"
					    v-model="country">
					</vue-simple-suggest>

					<button
						:disabled="processing"
						:class="checkLoading"
						@click="save"
					>Save Flag</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import VueSimpleSuggest from 'vue-simple-suggest'
import 'vue-simple-suggest/dist/styles.css'

export default {
    name: 'GlobalFlag',
    props: ['countries', 'user'],
    components: { VueSimpleSuggest },
    data ()
    {
        return {
            country: '',
            autoCompleteStyle: {
                vueSimpleSuggest: "position-relative width-50",
                inputWrapper: "",
                defaultInput : "input",
                suggestions: "position-absolute list-group z-1000 custom-class-overflow width-50",
                suggestItem: "list-group-item"
            },
            processing: false,
            defaultClass: 'button mt20 is-primary is-medium'
        };
    },
    methods: {

        getCountries() {
            return Object.values(this.countries);
        },

        getSelected() {
            if (this.user.global_flag) {
                return this.countries[this.user.global_flag];
            }

            return false;
        },

        save() {
            this.processing = true;

            let selected = Object.keys(this.countries).find(key => this.countries[key] === this.country);

            axios.post('/en/settings/save-flag', {
                country: selected
            })
            .then(response => {
                console.log(response);
                if (response.status == 200) {
                    window.location.href = window.location.href;
                }
            })
            .catch(error => {
                console.log(error);
            });
        }
    },
    computed: {

        checkLoading() {
            return this.processing ? this.defaultClass + ' is-loading' : this.defaultClass;
        },
    }
}
</script>

<style lang="scss">

	.mb20 {
		margin-bottom: 20px;
	}

	.mt20 {
		margin-top: 20px;
	}

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
