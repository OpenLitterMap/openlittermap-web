<template>
    <footer class="relative min-h-[42em] p-5 md:p-20 bg-[radial-gradient(circle_at_1%_1%,_#328bf2,_#1644ad)]">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col items-center text-center px-6 md:px-20 mb-12">
                <p class="text-white text-3xl font-bold mb-5">
                    {{ t('Want us to email you occasionally with good news?') }}
                </p>

                <div v-if="hasErrors" class="bg-red-500 text-white p-4 rounded-md mb-4 w-full md:w-1/2">
                    <div v-for="errorKey in Object.keys(errors)" :key="errorKey">
                        <p>{{ getError(errorKey) }}</p>
                    </div>
                </div>

                <form @submit.prevent="subscribe" class="w-full md:w-1/2">
                    <input
                        v-model="state.email"
                        @input="clearErrors"
                        required
                        type="email"
                        :placeholder="t('Enter your email address')"
                        class="w-full h-12 px-4 mb-4 rounded-full border-none"
                    />

                    <button
                        type="submit"
                        class="w-full md:w-auto px-6 py-2 mb-4 bg-olm-green text-white font-medium rounded-md hover:bg-olm-green-700 transition"
                    >
                        {{ t('Subscribe') }}
                    </button>

                    <p v-show="justSubscribed" class="text-white text-xl font-semibold">
                        {{ t('You have been subscribed to the good news! You can unsubscribe at any time.') }}
                    </p>
                </form>
            </div>

            <!-- Bottom Section -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-8 text-left px-6 md:px-20">
                <!-- Left column -->
                <div class="md:col-span-2 space-y-4">
                    <p class="text-white text-2xl font-bold">#OpenLitterMap</p>
                    <p class="text-blue-100">
                        {{
                            t(
                                "We need your help to create the world's most advanced and accessible database on pollution."
                            )
                        }}
                    </p>

                    <!-- Socials -->
                    <div class="flex space-x-4 mt-4">
                        <img
                            v-for="(s, index) in state.socials"
                            :key="index"
                            :src="icon(s.icon)"
                            @click="open(s.url)"
                            class="cursor-pointer h-8"
                        />
                    </div>

                    <p class="text-blue-100 mt-4">
                        {{ state.version }}
                    </p>
                </div>

                <!-- Middle columns -->
                <div>
                    <p class="text-white text-xl font-bold mb-3">{{ t('READ') }}</p>
                    <p
                        class="text-blue-100 cursor-pointer hover:text-white"
                        @click="open('https://openlittermap.medium.com/')"
                    >
                        {{ t('Blog') }}
                    </p>
                    <p
                        class="text-blue-100 cursor-pointer hover:text-white"
                        @click="open('https://opengeospatialdata.springeropen.com/articles/10.1186/s40965-018-0050-y')"
                    >
                        {{ t('Research Paper') }}
                    </p>
                    <router-link to="/references" class="block text-blue-100 hover:text-white">
                        {{ t('References') }}
                    </router-link>
                    <router-link to="/changelog" class="block text-blue-100 hover:text-white">
                        {{ t('Changelog') }}
                    </router-link>
                    <router-link to="/credits" class="block text-blue-100 hover:text-white">
                        {{ t('Credits') }}
                    </router-link>
                    <router-link to="/faq" class="block text-blue-100 hover:text-white">
                        {{ t('FAQ') }}
                    </router-link>
                </div>

                <div>
                    <p class="text-white text-xl font-bold mb-3">{{ t('WATCH') }}</p>
                    <p
                        class="text-blue-100 cursor-pointer hover:text-white"
                        @click="open('https://www.youtube.com/watch?v=my7Cx-kZhT4')"
                    >
                        TEDx 2017
                    </p>
                    <p
                        class="text-blue-100 cursor-pointer hover:text-white"
                        @click="open('https://www.youtube.com/watch?v=E_qhEhHwUGM')"
                    >
                        State of the Map 2019
                    </p>
                    <p
                        class="text-blue-100 cursor-pointer hover:text-white"
                        @click="open('https://www.youtube.com/watch?v=T8rGf1ScR1I')"
                    >
                        Datapub 2020
                    </p>
                    <p
                        class="text-blue-100 cursor-pointer hover:text-white"
                        @click="open('https://www.youtube.com/watch?v=5HuaQNeHuZ8')"
                    >
                        ESA PhiWeek 2020
                    </p>
                    <p
                        class="text-blue-100 cursor-pointer hover:text-white"
                        @click="open('https://www.youtube.com/watch?v=QhLsA0WIfTA')"
                    >
                        Geneva Forum, UN 2020
                    </p>
                    <p
                        class="text-blue-100 cursor-pointer hover:text-white"
                        @click="open('https://www.youtube.com/watch?v=Pe4nHdoAlu4')"
                    >
                        Cardano4Climate Meetup 2021
                    </p>
                    <p
                        class="text-blue-100 cursor-pointer hover:text-white"
                        @click="open('https://www.youtube.com/watch?v=f2UGAxRwrQk')"
                    >
                        CardanoSummit 2022
                    </p>
                </div>

                <div>
                    <p class="text-white text-xl font-bold mb-3">{{ t('HELP') }}</p>
                    <router-link to="/contact-us" class="block text-blue-100 hover:text-white">
                        {{ t('Contact Us') }}
                    </router-link>
                    <p class="text-blue-100 hover:text-white cursor-pointer">
                        {{ t('Create Account') }}
                    </p>
                    <p
                        class="text-blue-100 hover:text-white cursor-pointer"
                        @click="open('https://angel.co/openlittermap/jobs')"
                    >
                        {{ t('Join the Team') }}
                    </p>
                    <p
                        class="text-blue-100 hover:text-white cursor-pointer"
                        @click="
                            open(
                                'https://join.slack.com/t/openlittermap/shared_invite/zt-fdctasud-mu~OBQKReRdC9Ai9KgGROw'
                            )
                        "
                    >
                        {{ t('Join Slack') }}
                    </p>
                    <p
                        class="text-blue-100 hover:text-white cursor-pointer"
                        @click="open('https://github.com/openlittermap')"
                    >
                        GitHub
                    </p>
                    <p
                        class="text-blue-100 hover:text-white cursor-pointer"
                        @click="open('https://www.facebook.com/pg/openlittermap/groups/')"
                    >
                        {{ t('Facebook Group') }}
                    </p>
                    <router-link to="/donate" class="block text-blue-100 hover:text-white">
                        {{ t('Single Donation') }}
                    </router-link>
                    <router-link to="/signup" class="block text-blue-100 hover:text-white">
                        {{ t('Crowdfunding') }}
                    </router-link>
                </div>
            </div>
        </div>

        <!-- Very bottom section -->
        <div class="md:flex justify-center py-4 text-center mt-10">
            <p class="flex-[0.5] text-blue-100 border-t border-blue-400 pt-8">
                OpenLitterMap is a real-time gamification experience. Starting with litter & plastic pollution, our goal
                is to transform education by unlocking the data collection purpose of technology.
            </p>
        </div>
    </footer>
</template>

<script setup>
import { reactive, computed } from 'vue';
import { useI18n } from 'vue-i18n';
const { t } = useI18n();
import { useSubscriberStore } from '@/stores/subscriber';
const subscriberStore = useSubscriberStore();

const state = reactive({
    email: '',
    socials: [
        { icon: 'facebook2.png', url: 'https://facebook.com/openlittermap' },
        { icon: 'ig2.png', url: 'https://instagram.com/openlittermap' },
        { icon: 'twitter2.png', url: 'https://twitter.com/openlittermap' },
        { icon: 'reddit.png', url: 'https://reddit.com/r/openlittermap' },
        { icon: 'tumblr.png', url: 'https://tumblr.com/openlittermap' },
    ],
    version: 'v5.0.0',
});

// Computed properties
const errors = computed(() => subscriberStore.errors);
const hasErrors = computed(() => Object.keys(errors.value).length > 0);
const justSubscribed = computed(() => subscriberStore.justSubscribed);

// Methods
function clearErrors() {
    subscriberStore.clearErrors();
}

function getError(key) {
    // Each error is an array, so get [0]
    return errors.value[key][0];
}

function icon(path) {
    return '/assets/icons/' + path;
}

function open(url) {
    window.open(url, '_blank');
}

async function subscribe() {
    await subscriberStore.CREATE_EMAIL_SUBSCRIPTION(state.email);
}
</script>

<style scoped></style>
