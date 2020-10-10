<template>
    <MglMap :accessToken="accessToken" :mapStyle="mapStyle" />
</template>

<script>
import Mapbox from 'mapbox-gl'
import { MglMap } from 'vue-mapbox'

export default {
    name: 'Mapbox',
    components: {
        MglMap
    },
    created ()
    {
        // We need to set mapbox-gl library here in order to use it in template
        this.mapbox = Mapbox;
    },
    computed: {

        /**
         *
         */
        accessToken ()
        {
            return 'pk.eyJ1Ijoib3BlbmxpdHRlcm1hcCIsImEiOiJja2Z6OHJpd2IwanB5MnlxZjE5Yjhnd3V2In0.PxGAadt8Txh7JukdzQCjww';
        },

        /**
         *
         */
        mapStyle ()
        {
            return '';
        }
    }
}
</script>

<style scoped>

</style>
