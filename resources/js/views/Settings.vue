<template>
	<div class="container">
	<br>
		<div class="columns">
			<div class="column is-2">
				<aside id="panel" class="menu">
				  <p class="menu-label">
				    General
				  </p>
				  <ul class="menu-list">
				  	<!-- Todo - translate settigns -->
				    <li>
				    	<router-link to="/en/settings/password">
				    		Password
				    	</router-link>
				    </li>
				    <li>
				    	<router-link to="/en/settings/details" exact>
				    		Personal Details
				    	</router-link>
				    </li>
				    <li>
				    	<router-link to="/en/settings/account" exact>
				    		My Account
				    	</router-link>
				    </li>
				    <li>
				    	<router-link to="/en/settings/payments" exact>
				    		My Payments
				    	</router-link>
				    </li>
				    <li>
				    	<router-link to="/en/settings/privacy" exact>
				    		Privacy 
				    	</router-link>
				    </li>
				    <li>
				    	<router-link to="/en/settings/littercoin" exact>
				    		Littercoin (LTRX)
				    	</router-link>
				    </li>
				    <li>
				    	<router-link to="/en/settings/presence" exact>
				    		Presence
				    	</router-link>
				    </li>
				    <li>
				    	<router-link to="/en/settings/email" exact>
				    		Emails
				    	</router-link>
				    </li>
				    <li>
				    	<router-link to="/en/settings/show-flag">
				    		Show Flag
				    	</router-link>
				    </li>
				  </ul>
				</aside>
			</div>
			<div class="column is-three-quarters is-offset-1">
				<br>
		    	<router-view
		    		:user="user"
    				:plan="this.plan"
    				:isvalid="this.isvalid"
    				:ongraceperiod="this.ongraceperiod"
    				:ends_at="this.ends_at"
    				:plans="this.plans"
    				:countries="this.countries"
		    	></router-view>
			</div>
		</div>
	</div>
</template>

<script>
	export default {
		props: [
		  'user',
		  'plan',
		  'isvalid',
		  'ongraceperiod',
		  'ends_at',
		  'plans',
		  'countries'
		]
	}
</script>
