<template>
	<div>
		<p class="mb1em">Click a tag to reset it</p>
		<ul>
			<li v-for="category in tags" class='admin-item'>
				<span class="category">{{ category['category'] }}:</span>
				<br>
				<span
					v-for="tags in Object.entries(category['tags'])"
					v-html="getTags(tags)"
					class="tag is-large is-info litter-tag"
					@click="removeTag(category.category, tags[0])"
				/>
			</li>
		</ul>
	</div>
</template>

<script>
export default {
	name: 'AdminItems',
	computed: {
		/**
		 * Get available list of categories + tags for this image
		 * was -> return this.$store.state.litter.items;
		 */
		tags ()
		{
			let tags = [];

			Object.entries(this.$store.state.litter.categories).map(entries => {
				if (Object.keys(entries[1]).length > 0)
				{
					tags.push({
						category: entries[0],
						tags: entries[1]
					});
				}
			});

			return tags;
		}
	},

	methods: {

		/**
		 * Return Name: Value from tags
		 */
		getTags (tags)
		{
			return tags[0] + ": " + tags[1] + " <br>";
		},

		/**
		 * Remove tag (string) at this category
		 */
		removeTag (category, tag)
		{
			this.$store.commit('resetTag', {
				category,
				tag
			});
		}
	}
}
</script>

<style lang="scss">
.admin-item {
  	margin-bottom: 0.5em;
  	font-size: 20px;
  	font-weight: 600;
}
.category {
  	font-size: 1.25em;
}
.litter-tag {
  	margin-bottom: 10px;
  	width: 100%;
}
</style>
