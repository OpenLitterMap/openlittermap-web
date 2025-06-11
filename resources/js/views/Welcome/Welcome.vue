<template>
    <div class="px-4 md:px-0">
        <div class="max-w-[1344px] pt-10 md:pt-20 mx-auto">
            <Intro />

            <WhyHow />

            <Partners />
        </div>

        <Footer />
    </div>
</template>

<script setup>
import Intro from './components/Intro.vue';
import WhyHow from './components/WhyHow.vue';
import Partners from './components/Partners.vue';
import Footer from './components/Footer.vue';
</script>

<style scoped lang="scss">
//    .c-1 {
//        margin-bottom: 3em;
//    }
//
//    .home-container {
//        padding-top: 5em;
//    }
//
//
//    /* Smaller screens */
//    @media (max-width: 1024px) {
//
//        .home-container {
//            padding-left: 2em;
//            padding-right: 2em;
//        }
//
//    }
//
//    /* Mobile view */
//    @media (max-width: 768px) {
//
//        .home-container {
//            padding-top: 3em !important;
//        }
//
//        .welcome-mb {
//            margin-bottom: 1em;
//        }
//
//        .top-image {
//            height: 400px;
//        }
//
//        .partners-container {
//            padding-left: 0;
//            padding-right: 0;
//        }
//    }
//
//    /* Extra small */
//    @media (max-width: 576px) {
//
//        .variable-height {
//            min-height: 100px;
//        }
//
//        .top-image {
//            height: 260px;
//        }
//
//    }
//
//    /* Extra extra small */
//    @media (max-width: 430px) {
//        .variable-height {
//            min-height: 175px;
//        }
//    }
//
//
</style>
