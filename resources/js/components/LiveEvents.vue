<template>
	<div class="sidebar-menu scrollbar-hidden">
		<transition-group name="list" mode="out-in">
			<span
                v-for="(event, index) in events"
                :key="event.id"
                class="list-item"
            >
                <component
                    :is="event.type"
                    :country="event.country"
                    :country-code="event.countryCode"
                    :state="event.state"
                    :city="event.city"
                    :team-name="event.teamName"
                    :reason="event.reason"
                    @click="removeEvent(index)"
                ></component>
			</span>
		</transition-group>
	</div>
</template>

<script>
import ImageUploaded from './Notifications/ImageUploaded';
import NewCountryAdded from './Notifications/NewCountryAdded';
import NewStateAdded from './Notifications/NewStateAdded';
import NewCityAdded from './Notifications/NewCityAdded';
import UserSignedUp from './Notifications/UserSignedUp';
import TeamCreated from './Notifications/TeamCreated';
import LittercoinMined from './Notifications/LittercoinMined';

export default {
	name: 'live-events',
    components: {
	    LittercoinMined,
        TeamCreated,
        UserSignedUp,
        NewCityAdded,
        NewStateAdded,
        NewCountryAdded,
        ImageUploaded
    },
    channel: 'main',
	echo: {
	    'ImageUploaded': (payload, vm) => {

	        document.title = "OpenLitterMap (" + (vm.events.length + 1) + ")";

			vm.events.unshift({
				id: new Date().getTime(),
                type: 'ImageUploaded',
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
                id: new Date().getTime(),
                type: 'NewCountryAdded',
				country: payload.country,
				countryId: payload.countryId
			})
		},
		'NewStateAdded': (payload, vm) => {

            document.title = "OpenLitterMap (" + (vm.events.length + 1) + ")";

            vm.events.unshift({
                id: new Date().getTime(),
                type: 'NewStateAdded',
				state: payload.state,
				stateId: payload.stateId
			})
		},
		'NewCityAdded': (payload, vm) => {

            document.title = "OpenLitterMap (" + (vm.events.length + 1) + ")";

            vm.events.unshift({
                id: new Date().getTime(),
                type: 'NewCityAdded',
				city: payload.city,
				cityId: payload.cityId
			})
		},
		'UserSignedUp': (payload, vm) => {

            document.title = "OpenLitterMap (" + (vm.events.length + 1) + ")";

            vm.events.unshift({
                id: new Date().getTime(),
                type: 'UserSignedUp',
				now: payload.now
			})
		},
        'TeamCreated': (payload, vm) => {

            document.title = "OpenLitterMap (" + (vm.events.length + 1) + ")";

            vm.events.unshift({
                id: new Date().getTime(),
                type: 'TeamCreated',
                teamName: payload.teamName
            });
        },
        '.App\\Events\\Littercoin\\LittercoinMined': (payload, vm) => {

            document.title = "OpenLitterMap (" + (vm.events.length + 1) + ")";

            vm.events.unshift({
                id: new Date().getTime(),
                type: 'LittercoinMined',
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

        removeEvent (index) {
            this.events.splice(index, 1);
            document.title = this.events.length
                ? 'OpenLitterMap (' + this.events.length + ')'
                : 'OpenLitterMap';
        },
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

</style>
