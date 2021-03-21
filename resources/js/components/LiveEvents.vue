<template>
	<div class="sidebar-menu">
		<transition-group name="list">
			<span v-for="event in events" :key="getKey(event)" class="list-item">

                <div v-if="event.type === 'image'" class="event" style="background-color: #88d267;">
                    <aside class="grid-img">
                        <img v-if="event.countryCode" :src="countryFlag(event.countryCode)" width="35" />

                        <i v-else class="fa fa-image" />
					</aside>
					<div class="grid-main">
						<strong>New image</strong>
						<br>
						<i class="event-subtitle city-name">{{ event.city }}, {{ event.state }}</i>
						<p class="event-subtitle">{{ event.country }}</p>

                        <p v-show="event.teamName">By Team: <strong>{{ event.teamName }}</strong></p>
					</div>
				</div>

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

				<div v-else />
			</span>
		</transition-group>
	</div>
</template>

<script>
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

export default {
	name: 'live-events',
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
        }
	},
	data ()
    {
		return {
            dir: '/assets/icons/flags/',
			events: []
		};
	},
	methods: {

	    /**
         * Return location of country_flag.png
         */
        countryFlag (iso)
        {
            if (iso)
            {
                iso = iso.toLowerCase();

                return this.dir + iso + '.png';
            }

            return '';
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

			return this.events.length;
		}
	}
}
</script>

<style lang="scss">

    .list-enter-active, .list-leave-active {
        transition: all 1s;
    }

    .list-enter, .list-leave-to {
        transform: translateX(30px);
    }

    .list-item {
        display: grid;
    }

    .list {
        &-move {
            transition: all 1s ease-in-out;
        }
    }

    .new-user-text-narrow {
        display: none;
    }

    .sidebar-menu {
        position: absolute;
        top: 0;
        width: 20%;
        margin-left: 80%;
        display: table-row;
        height: 100%;
        overflow-y: scroll;
        padding-top: 30px;
        z-index: 999;
        pointer-events: none;
    }

    @media (max-width: 910px) {
        .sidebar-menu {
            width: 25%;
            margin-left: 75%;
        }
    }

    @media (max-width: 730px) {
        .sidebar-menu {
            width: 30%;
            margin-left: 70%;
        }
    }

    @media (max-width: 620px) {
        .sidebar-menu {
            width: 35%;
            margin-left: 65%;
        }
    }

    @media (max-width: 530px) {
        .sidebar-menu {
            width: 35%;
            margin-left: 65%;
        }
        .city-name {
            display: none;
        }
    }

    @media (max-width: 500px) {
        .sidebar-menu {
            width: 45%;
            margin-left: 55%;
        }
        .city-name {
            display: none;
        }
    }

    .sidebar-title {
        padding: 20px;
        text-align: center;
        font-size: 24px;
        font-weight: 700;
    }

    .event {
        border-radius: 6px;
        width: 80%;
        margin-left: 10%;
        margin-bottom: 10px;
        display: grid;
        grid-template-columns: 1fr 3fr;
    }

    .event-title {
        padding: 10px;
    }

    .event-subtitle {

    }

    .grid-img {
        margin: auto;
        font-size: 22px;
        text-align: center;
    }

    .grid-main {
        margin-top: auto;
        margin-bottom: auto;
        padding: 10px;
    }

</style>
