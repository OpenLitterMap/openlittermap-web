<template name="added-items">
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
		computed: {
			addedItems() {
				return this.$store.state.litter.items;
			},
			category() {
				return this.$store.state.litter.currentCategory;
			}
		},
		methods: {
		    takeout(item) {
		        this.$store.commit('removeItem', { item, category: this.category });
		    },
		}
	}
</script>