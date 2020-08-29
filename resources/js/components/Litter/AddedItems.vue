<template>
	<div>
		<ul>
	    	<li v-for="quantity, item in addedItems"
	    		class="box mobile-litter-box"
	    		@click="takeout(item)">
	    	{{ item }}: {{ quantity }}
	    </li>
		</ul>
	</div>
</template>

<script>
export default {
    name: 'AddedItems',
    computed: {

        /**
         *
         */
        addedItems ()
        {
            return [];

            // return this.$store.state.litter.tags; // was, .items
        },

        /**
         *
         */
        category ()
        {
            return this.$store.state.litter.category; // was, .selectedCategory
        }
    },
    methods: {

        /**
         *
         */
        takeout (item)
        {
            this.$store.commit('removeItem', { item, category: this.category });
        },
    }
}
</script>
