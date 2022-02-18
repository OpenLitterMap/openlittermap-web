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

import SocialSection from './SocialSection';
import HeroSection from './HeroSection';
import StatsSection from './StatsSection';
import ZoomSection from './ZoomSection';
import SlackSection from './SlackSection';
import FundraiserSection from './FundraiserSection';

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

