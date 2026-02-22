<template name="anonymous-presence">
	<div>
        <strong :style="{ color: remaining ? 'red' : 'green' }">Status: <slot>{{ remaining }}</slot></strong>
		<button class="button" :class="{ 'is-success': remaining, 'is-danger': ! remaining }" @click="togglePresence">
	</div>
</template>

<script>
	export default {
        data() {
            return {
                remaining: true
            }
        },

        methods: {
            togglePresence() {
                this.remaining = ! this.remaining
            }
        }
    }
</script>
