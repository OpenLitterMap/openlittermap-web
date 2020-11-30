<template>
    <div>
        <div style="height: 1px; background-color: #00CBAB;" />
        <nav class="navbar main-nav">
            <div class="container">
                <div class="navbar-brand">

                    <router-link to="/" class="navbar-item">
                        <h1 class="nav-title">#OpenLitterMap</h1>
                    </router-link>

                    <!-- Mobile -->
                    <div :class="burger" @click="toggleOpen">
                        <span class="is-white" />
                        <span class="is-white" />
                        <span class="is-white" />
                    </div>
                </div>

                <div :class="nav">
                    <div class="navbar-end">

                        <!-- Admin -->
                        <router-link v-if="admin" to="/admin/photos" class="navbar-item">
                            {{ $t('nav.admin')}}
                        </router-link>

                        <!-- About -->
                        <router-link to="/about" class="navbar-item">
                              {{ $t('nav.about')}}
                        </router-link>

                        <!-- Global Map -->
                        <router-link to="/global" class="navbar-item">
                             {{ $t('nav.global-map')}}
                        </router-link>

                        <!-- World Cup -->
                        <router-link to="/world" class="navbar-item">
                             {{ $t('nav.world-cup')}}
                        </router-link>

                        <!-- if auth -->
                        <div v-if="auth" class="flex-not-mobile">

                            <router-link to="/upload" class="navbar-item">
                                 {{ $t('nav.upload')}}
                            </router-link>

                            <!-- Dropdown toggle -->
                            <div class="navbar-item has-dropdown is-hoverable">
                                <!-- "More" -->
                                <a id="more" class="navbar-item"> {{ $t('nav.more')}}</a>
                                <!-- Dropdown menu -->
                                <div class="navbar-dropdown" style="z-index: 2;">

                                    <!-- Tag Litter -->
                                    <router-link to="/tag" class="navbar-item drop-item">
                                         {{ $t('nav.tag-litter')}}
                                    </router-link>

                                    <!-- Todo - Profile -->
<!--                                    <router-link to="/profile" class="navbar-item drop-item">-->
<!--                                        Profile-->
<!--                                    </router-link>-->

                                    <!-- Teams -->
                                    <router-link to="/teams" class="navbar-item drop-item">
                                        {{ $t('nav.teams') }}
                                    </router-link>

                                    <!-- Settings -->
                                    <router-link to="/settings/password" class="navbar-item drop-item">
                                         {{ $t('nav.settings')}}
                                    </router-link>

                                    <!-- Logout -->
                                    <a class="navbar-item drop-item" @click="logout"> {{ $t('nav.logout')}}</a>
                                </div>
                            </div>
                        </div>

                        <!-- The user is not authenticated -->
                        <div v-else class="flex-not-mobile">
                            <!-- Login -->
                            <a class="navbar-item" @click="login"> {{ $t('nav.login')}}</a>

                            <!-- Signup -->
                            <router-link to="/signup" class="navbar-item">
                                 {{ $t('nav.signup')}}
                            </router-link>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </div>
</template>

<script>

export default {
    name: 'Nav',
    data ()
    {
        return {
            open: false
        };
    },
    computed: {

        /**
         * Return true if the user is admin
         */
        admin ()
        {
            return this.$store.state.user.admin;
        },

        /**
         * Return true if the user is logged in
         */
        auth ()
        {
            return this.$store.state.user.auth;
        },

        /**
         *
         */
        burger ()
        {
            return this.open ? 'navbar-burger burger is-active' : 'navbar-burger burger'
        },

        /**
         *
         */
        nav ()
        {
            return this.open ? 'navbar-menu is-active' : 'navbar-menu';
        }
    },

    methods: {

        /**
         * Show modal to log the user in
         */
        login ()
        {
            this.$store.commit('showModal', {
                type: 'Login',
                title: 'Login',
                action: 'LOGIN'
            });
        },

        /**
         * Log the user out
         */
        async logout ()
        {
            await this.$store.dispatch('LOGOUT');
        },

        /**
         * Mobile - toggle the nav
         */
        toggleOpen ()
        {
            this.open = ! this.open;
        }
    }
}
</script>

<style scoped>

    .burger {
        align-self: center;
    }

    .drop-item {
        color: black;
        font-weight: 500;
    }

    .flex-not-mobile {
        display: flex;
    }

    .main-nav {
        background-color: black;
        padding-top: 10px;
        padding-bottom: 10px;
    }

    .nav-title {
        color: white;
        font-size: 2.5rem;
        font-weight: 600;
        line-height: 1.125;
    }

    .is-white {
        color: white;
    }

    @media (max-width: 768px)
    {

        .flex-not-mobile {
            display: block;
        }

        .nav-title {
            font-size: 2rem;
            padding-left: 0.25em;
        }

    }

</style>
