<template>
	<div class="sidebar-menu scrollbar-hidden">
		<transition-group name="list" mode="out-in">
			<span v-for="(event, index) in events" :key="getKey(event)" class="list-item">

                <ImageUploaded
                    v-if="event.type === 'image'"
                    @click="removeEvent(index)"
                    :key="index"
                    :country="event.country"
                    :state="event.state"
                    :city="event.city"
                    :team-name="event.teamName"
                ></ImageUploaded>

				<div v-else-if="event.type === 'country'" class="event" style="background-color: #4bb0e0;">
					<aside class="grid-img">
						<i class="fa fa-flag" />
					</aside>
					<div class="grid-main">
						<strong>New Country</strong>
						<p>Say hello to <i>{{ event.country }}</i></p>
					</div>
				</div>

				<div v-else-if="event.type === 'state'" class="event" style="background-color: #4bb0e0;">
					<aside class="grid-img">
						<i class="fa fa-flag" />
					</aside>
					<div class="grid-main">
						<strong>New State</strong>
						<p>Say hello to <i>{{ event.state }}</i></p>
					</div>
				</div>

				<div v-else-if="event.type === 'city'" class="event" style="background-color: #4bb0e0;">
					<aside class="grid-img">
						<i class="fa fa-flag" />
					</aside>
					<div class="grid-main">
						<strong>New City</strong>
						<p>Say hello to <i>{{ event.city }}</i></p>
					</div>
				</div>

				<div v-else-if="event.type === 'new-user'" class="event" style="background-color: #f1c40f;">
					<aside class="grid-img">
						<i class="fa fa-user" />
					</aside>
					<div class="grid-main">
						<p class="new-user-text-wide">A new user has signed up!</p>
					</div>
				</div>

                <div v-else-if="event.type === 'team-created'" class="event" style="background-color: #e256fff0;">
					<aside class="grid-img">
						<i class="fa fa-users" />
					</aside>
					<div class="grid-main">
						<p>A new Team has been created!</p>
                        <i>Say hello to <strong>{{ event.name }}</strong>!</i>
					</div>
				</div>

                <div v-else-if="event.type === 'littercoin-mined'" class="event" style="background-color: #e256fff0;">
					<aside class="grid-img">
						<img src="/assets/icons/mining.png" class="ltr-icon" />
					</aside>
					<div class="grid-main">
						<p>A Littercoin has been mined!</p>
                        <i>Reason: <span class="ltr-strong">{{ getLittercoinReason(event.reason) }}</span></i>
					</div>
				</div>

				<div v-else />
			</span>
		</transition-group>
	</div>
</template>

<script>
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import ImageUploaded from './Notifications/ImageUploaded';

export default {
	name: 'live-events',
    components: {ImageUploaded},
    channel: 'main',
	echo: {
	    'ImageUploaded': (payload, vm) => {

	        document.title = "OpenLitterMap (" + (vm.events.length + 1) + ")";

			vm.events.unshift({
				type: 'image',
				city: payload.city,
				state: payload.state,
				country: payload.country,
				imageName: payload.imageName,
                teamName: payload.teamName,
                countryCode: payload.countryCode
			});
		},
		'NewCountryAdded': (payload, vm) => {

            document.title = "OpenLitterMap (" + (vm.events.length + 1) + ")";

            vm.events.unshift({
				type: 'country',
				country: payload.country,
				countryId: payload.countryId
			})
		},
		'NewStateAdded': (payload, vm) => {

            document.title = "OpenLitterMap (" + (vm.events.length + 1) + ")";

            vm.events.unshift({
				type: 'state',
				state: payload.state,
				stateId: payload.stateId
			})
		},
		'NewCityAdded': (payload, vm) => {

            document.title = "OpenLitterMap (" + (vm.events.length + 1) + ")";

            vm.events.unshift({
				type: 'city',
				city: payload.city,
				cityId: payload.cityId
			})
		},
		'UserSignedUp': (payload, vm) => {

            document.title = "OpenLitterMap (" + (vm.events.length + 1) + ")";

            vm.events.unshift({
				type: 'new-user',
				now: payload.now
			})
		},
        'TeamCreated': (payload, vm) => {

            document.title = "OpenLitterMap (" + (vm.events.length + 1) + ")";

            vm.events.unshift({
                type: 'team-created',
                name: payload.name
            });
        },
        '.App\\Events\\Littercoin\\LittercoinMined': (payload, vm) => {

            document.title = "OpenLitterMap (" + (vm.events.length + 1) + ")";

            vm.events.unshift({
                type: 'littercoin-mined',
                reason: payload.reason,
                userId: payload.userId
            });
        }
	},
	data ()
    {
		return {
			events: []
		};
	},
	methods: {

	    removeEvent(index) {
	        this.events.splice(index, 1);
        },

        /**
         * Return a unique key for each event
         */
		getKey (event)
		{
			if (event.type === 'image') return event.type + event.imageName;

			else if (event.type === 'country') return event.type + event.countryId;

			else if (event.type === 'state') return event.type + event.stateId;

			else if (event.type === 'city') return event.type + event.cityId;

			else if (event.type === 'new-user') return event.type + event.now;

			else if (event.type === 'team-created') return event.type + event.name;

			else if (event.type === 'littercoin-mined') return event.type + event.userId + event.now;

			return this.events.length;
		},

        /**
         * Using the LittercoinMined event key,
         *
         * Todo - return translated string
         */
        getLittercoinReason (reason)
        {
            if (reason === 'verified-box')
            {
                return '100 OpenLitterAI boxes verified';
            }

            else if (reason === '100-images-verified')
            {
                return '100 images verified';
            }
        }
    }
}
</script>

<style lang="scss">

    .list-enter-active, .list-leave-active {
        transition: all 1s ease;
    }
    .list-leave-active {
        transition: all .3s ease;
    }

    .list-enter, .list-leave-to {
        transform: translateX(100px);
        opacity: 0;
    }

    .list-item {
        display: grid;
    }

    .new-user-text-narrow {
        display: none;
    }

    .sidebar-menu {
        position: absolute;
        top: 70px;
        right: 10px;
        width: 20rem;
        max-height: 80vh;
        overflow-y: scroll;
        z-index: 999;
    }

    .grid-img {
        padding: 16px;
    }

    .grid-main {
        padding-top: 10px;
        padding-bottom: 10px;
    }

    @media (max-width: 1024px) {
        .sidebar-menu {
            width: 18rem;
            font-size: 0.8rem;
        }
        .grid-img {
            padding: 12px;
        }
        .grid-main {
            padding-top: 8px;
            padding-bottom: 8px;
        }
    }

    @media (max-width: 768px) {
        .sidebar-menu {
            width: 16rem;
        }
        .city-name {
            display: none;
        }
    }

    @media (max-width: 640px) {
        .sidebar-menu {
            width: 12rem;
            max-height: 74vh;
        }
    }

    .sidebar-title {
        padding: 20px;
        text-align: center;
        font-size: 24px;
        font-weight: 700;
    }

    .event {
        border-radius: 8px;
        margin-bottom: 10px;
        display: flex;
        cursor: pointer;
    }

    .event-title {
        padding: 10px;
    }

    .event-subtitle {

    }

    .ltr-icon {
        max-width: 55%;
        padding-top: 0.5em;
    }

    .ltr-strong {
        font-weight: 600;
    }

</style>
