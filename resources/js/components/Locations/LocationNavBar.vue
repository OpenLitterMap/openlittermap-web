<template>
<div class="container w100">
	<br>
    <div class="control locations-control">
        <div class="select">
            <select v-model="category" @change="onChange(category)">
                <option v-for="cat in catnames" :key="cat">{{ cat }}</option>
            </select>
        </div>
    </div>
</div>
</template>

<script>
export default {
    name: 'LocationNavbar',
    data() {
        return {
            'category': this.$t('location.most-data'),
			'catnames': [
				'A-Z',
				this.$t('location.most-data'),
				this.$t('location.most-data-person')
			]
        }
    },
    methods: {
        onChange() {
            this.$emit('selectedCategory', this.category);
        }
    }
}
</script>

<style scoped>
    .locations-control {
            text-align: right;
        }

    /* Small devices */
    @media screen and (max-width: 768px)
        {

            .locations-control {
                text-align: center;
            }
        }
</style>