<template name="sign-up">
    <section class="section hero fullheight is-primary is-bold">
        <div class="container">
            <h1 class="title">
                {{ $t('auth.signup.ready-to-join') }}
            </h1>
            <h2 class="subtitle">
                {{ $t('auth.signup.join-subtitle') }}
            </h2>

            <div class="columns">
                <div class="column wow bounceInRight">
                    <div id="first" class="box">
                        <p class="card-header-title" style="color: #3273dc; margin-bottom: -20px;">
                            {{ $t('auth.signup.free-plan') }}
                        </p>
                        <div class="content">
                            <ul>
                                <li>{{ $t('auth.signup.free-plan-feature1') }}</li>
                                <li>{{ $t('auth.signup.free-plan-feature2') }}</li>
                                <li>{{ $t('auth.signup.free-plan-feature3') }}</li>
                                <li>{{ $t('auth.signup.free-plan-feature4') }}</li>
                            </ul>
                        </div>
                        <div style="text-align: center;">
                            <router-link :to="'/join?plan=free'">
                                <button class="button is-primary is-center" style="margin-top: -10px;">
                                    {{ $t('auth.signup.free-plan-join') }}
                                </button>
                            </router-link>
                        </div>
                    </div>
                </div>

                <div class="column wow bounceInDown">
                    <div class="box">
                        <p class="card-header-title" style="color: orange;">
                            {{ $t('auth.signup.startup-plan') }} <strong style="padding-left: 30px;">{{ $t('auth.signup.startup-plan-donation') }}</strong>
                        </p>
                        <div class="content">
                            <ul>
                                <li>{{ $t('auth.signup.startup-plan-feature1') }}</li>
                                <li>{{ $t('auth.signup.startup-plan-feature2') }}</li>
                                <li>{{ $t('auth.signup.startup-plan-feature3') }}</li>
                            </ul>
                        </div>
                        <div style="text-align: center;">
                            <router-link :to="'/join?plan=startup'">
                                <button class="button is-warning">
                                    {{ $t('auth.signup.startup-plan-join') }}
                                </button>
                            </router-link>
                        </div>
                    </div>
                </div>

                <div class="column wow bounceInLeft">
                    <div class="box">
                        <p class="card-header-title" style="color: green;">
                            {{ $t('auth.signup.basic-plan') }}<strong style="padding-left: 30px;">{{ $t('auth.signup.basic-plan-donation') }}</strong>
                        </p>
                        <div class="content">
                            <ul>
                                <li>{{ $t('auth.signup.basic-plan-feature1') }}</li>
                                <li>{{ $t('auth.signup.basic-plan-feature2') }}</li>
                                <li>{{ $t('auth.signup.basic-plan-feature3') }}</li>
                            </ul>
                        </div>
                        <div style="text-align: center;">
                            <router-link :to="'/join?plan=basic'">
                                <button class="button is-success">
                                    {{ $t('auth.signup.basic-plan-join') }}
                                </button>
                            </router-link>
                        </div>
                    </div>
                </div>
            </div>

            <div class="columns">
                <div class="column is-4 is-offset-2 wow bounceInLeft">
                    <div class="box">
                        <p class="card-header-title">
                            {{ $t('auth.signup.advanced-plan') }} <strong style="padding-left: 12px;">{{ $t('auth.signup.advanced-plan-donation') }}  </strong>
                        </p>
                        <div class="content">
                            <ul>
                                <li>{{ $t('auth.signup.advanced-plan-feature1') }}</li>
                                <li>{{ $t('auth.signup.advanced-plan-feature2') }}</li>
                                <li>{{ $t('auth.signup.advanced-plan-feature3') }}</li>
                            </ul>
                        </div>
                        <div style="text-align: center;">
                            <router-link :to="'/join?plan=advanced'">
                                <button class="button is-dark">
                                    {{ $t('auth.signup.advanced-plan-join') }}
                                </button>
                            </router-link>
                        </div>
                    </div>
                </div>

                <div class="column is-4 wow bounceInRight">
                    <div class="box">
                        <p class="card-header-title" style="color: red;">
                            {{ $t('auth.signup.pro-plan') }} <strong style="padding-left: 45px;">{{ $t('auth.signup.pro-plan-donation') }}</strong>
                        </p>
                        <div class="content">
                            <ul>
                                <li>{{ $t('auth.signup.pro-plan-feature1') }}</li>
                                <li>{{ $t('auth.signup.pro-plan-feature2') }}</li>
                                <li>{{ $t('auth.signup.pro-plan-feature3') }}</li>
                            </ul>
                        </div>
                        <div style="text-align: center;">
                            <router-link :to="'/join?plan=pro'">
                                <button class="button is-danger">
                                    {{ $t('auth.signup.pro-plan-join') }}
                                </button>
                            </router-link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>

    .box {
        min-height: 100%;
    }

</style>
