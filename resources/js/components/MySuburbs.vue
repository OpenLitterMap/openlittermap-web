<template name="my-suburbs">
	<select>
		<option v-for="suburb in this.suburbs" :value="suburb.city_id">
			{{ suburb.suburb }}
		</option>
	</select>
</template>

<script>
	export default {
		
		props: ['suburbs'],

		created() {
			console.log('my suburbs created!');
		}
	}
</script>