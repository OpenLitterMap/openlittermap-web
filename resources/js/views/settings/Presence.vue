<template>
	<div style="padding-left: 1em; padding-right: 1em;">
		<h1 class="title is-4">Toggle Litter Presence</h1>
		<hr>
		<p>By default we assume that all litter is still remaining. However if you pick up as you go, change this default value and we will record your litter as picked up.</p>
		<p>You can also change the value of each litter item when you are tagging them.</p>
		<br>
		<p><b>Current Status:</b></p><p><b :style="this.color">{{ this.computedPresence }}</b></p>
		<br>
		<div class="columns">
			<div class="column is-one-third is-offset-1">
				<div class="row">
					<button class="button is-info" @click="toggle">Toggle Presence</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
export default {
    name: 'Presence',
    mounted ()
    {
        this.$store.commit('initPresence', parseInt(this.user.items_remaining));
    },
    methods: {
        toggle() {
            axios.post('/en/settings/toggle')
            .then(response => {
                console.log(response);
                window.location.href = window.location.href;
            })
            .catch(error => {
                console.log(error);
            })
        }
    },

    computed: {
        presence() {
            return this.$store.state.presence;
        },
        color() {
            return this.presence ? 'color:red' : 'color:green';
        },
        computedPresence() {
            return this.presence == 0 ? "Your litter is logged as picked up." : "Your litter is logged as not picked up.";
        }
    }
}
</script>
