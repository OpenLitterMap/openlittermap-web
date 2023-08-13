<template>
    <div class="h100">
        <div class="columns h100">

            <div class="column is-half">
                <MerchantMap />
            </div>

            <div class="column is-half pt5 pl5">

                <div
                    v-if="can('approve merchants')"
                    class="mb3"
                >
                    <button
                        @click="changeComponent('CreateMerchant')"
                        class="button is-small is-info"
                    >Create Merchants</button>

                    <button
                        @click="changeComponent('ApproveMerchant')"
                        class="button is-small is-warning"
                    >Approve Merchants</button>
                </div>

                <component
                    :is="selectedComponent"
                />
            </div>
        </div>
    </div>
</template>

<script>
import MerchantMap from "../../components/Littercoin/Merchants/MerchantMap";
import CreateMerchant from "../../components/Littercoin/Merchants/CreateMerchant";
import ApproveMerchant from "../../components/Littercoin/Merchants/ApproveMerchant";

export default {
    name: "Merchants",
    components: {
        MerchantMap,
        CreateMerchant,
        ApproveMerchant
    },
    data () {
        return {
            selectedComponent: 'CreateMerchant'
        }
    },
    methods: {
        changeComponent (str) {
            this.selectedComponent = str;
        }
    }
}
</script>

<style scoped>

</style>
