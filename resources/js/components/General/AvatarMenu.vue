<template>
    <div>
        <img
            v-show="avatar"
            :src="avatar"
            alt="user-avatar"
            width="32"
            height="32"
            class="avatar-content"
            @click="toggleAvatar"
        >
        <!-- Avatar menu -->
        <div v-show="isAvatarMenuOpen" v-click-outside="close" class="avatar-menu">
            <!-- Todo - Profile -->
            <!-- <router-link to="/profile" class="navbar-item drop-item">-->
            <!-- Profile-->
            <!-- </router-link>-->
            <!-- Settings -->
            <router-link to="/settings/details" class="drop-item">
                {{ $t('nav.settings') }}
            </router-link>

            <!-- Logout -->
            <a class="drop-item" @click="logout"> {{ $t('nav.logout') }}</a>
        </div>
    </div>
</template>

<script>
import Vue from 'vue';

Vue.directive('click-outside', {
    bind: function (el, binding, vnode) {
        window.event = function (event) {
            if (!(el == event.target || el.contains(event.target))) {
                vnode.context[binding.expression](event);
            }
        };
        document.body.addEventListener('click', window.event);
    },
    unbind: function (el) {
        document.body.removeEventListener('click', window.event);
    }
});

export default {
    name: 'AvatarMenu',
    data ()
    {
        return { isAvatarMenuOpen: false};
    },
    computed: {
        avatar ()
        {
            return this.$store.state.user.user.avatar;
        },
    },
    methods: {
        toggleAvatar (event)
        {
            this.isAvatarMenuOpen = !this.isAvatarMenuOpen;
            event.stopPropagation();
        },
        async logout ()
        {
            await this.$store.dispatch('LOGOUT');
        },
        close () {
            this.isAvatarMenuOpen = false;
        }
    }
};
</script>

<style lang="scss" scoped>
@import "../../styles/variables.scss";
    .avatar-menu {
        z-index: 2;
        padding: 10px 0;
        position: absolute;
        top: 60px;
        right: -35px;
        background: $white;
        border-radius: 10px;
        box-shadow: 1px 1px 4px rgba(0, 0, 0, 0.2);

         .drop-item {
            display: block;
            padding: 10px 30px;

            &:hover {
                background-color: whitesmoke;
            }
        }
    }

    .avatar-content {
        cursor: pointer;
        background: $white;
        border-radius: 50%;
        max-height: unset
    }
</style>
