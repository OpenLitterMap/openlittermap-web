<template>
    <div>
        <HeroSection/>
        <StatsSection/>
        <SocialSection/>
        <ZoomSection/>
        <SlackSection/>
        <FundraiserSection/>
    </div>
</template>

<script>

import SocialSection from './SocialSection.vue';
import HeroSection from './HeroSection.vue';
import StatsSection from './StatsSection.vue';
import ZoomSection from './ZoomSection.vue';
import SlackSection from './SlackSection.vue';
import FundraiserSection from './FundraiserSection.vue';

export default {
    name: 'Index',
    components: {
        HeroSection,
        StatsSection,
        SocialSection,
        ZoomSection,
        SlackSection,
        FundraiserSection
    },
};
</script>

