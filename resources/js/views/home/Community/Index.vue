<template>
    <div>
        <HeroSection/>
        <StatsSection/>
        <SocialSection/>
        <ZoomSection/>
        <SlackSection/>
        <FundraiserSection/>
        <FAQSection/>
    </div>
</template>

<script>

import SocialSection from './SocialSection';
import HeroSection from './HeroSection';
import StatsSection from './StatsSection';
import ZoomSection from './ZoomSection';
import SlackSection from './SlackSection';
import FundraiserSection from './FundraiserSection';
import FAQSection from './FAQSection';

export default {
    name: 'Index',
    components: {
        HeroSection,
        StatsSection,
        SocialSection,
        ZoomSection,
        SlackSection,
        FundraiserSection,
        FAQSection,
    },
};
</script>

