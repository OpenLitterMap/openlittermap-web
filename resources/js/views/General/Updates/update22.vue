<template>
    <div class="update-content">
        <h2>Update #22 - New tool to advance the OpenLitterAI</h2>

        <p>More good news!</p>

        <p>Today, we released a new tool to add boxes to identify litter in images and we need your help.</p>

        <ul>
            <li>
                At the moment, the tool not yet open to the public but if you would like to become a beta tester please
                get in touch! You can see a video about how it works here.
            </li>
            <li>
                This work is redeemable in Littercoin! If you can help us add 100 boxes, you will earn 1 Littercoin. Any
                boxes will have to be verified by an Admin before your reward is generated.
            </li>
            <li>
                We need to add boxes to images to teach the OpenLitterAi, a computer vision algorithm, where the litter
                is in the image and what each type of litter looks like. This will hopefully automate tagging and it
                might even remove the need to upload photos.
            </li>
            <li>
                There is over 100,000 images to tag, and we have yet to pull images from the internet to increase our
                database. So your help, input and advice here is very welcome and badly needed!
            </li>
            <li>
                Once we pass beta testing this tool will become publicly available for everyone to help with and
                contribute to. You can see the code for it here.
            </li>
            <li>
                Our mobile app is now available in Arabic, Dutch, French, German, Spanish and Portuguese! Can you help
                us translate into more languages? You can find the mobile app files here and the website files here.
                OpenLitterMap.com the website is currently only available in English and Dutch. We would love help
                getting this into more languages!
            </li>
            <li>
                We have passed 120,000 uploads and we are now generating about 1,000 uploads per week! A new superuser
                representing TeamLitterUSA is trying to get into 1st place but will the Dutch allow this to happen?
            </li>
            <li>
                On community request, we released the APK for the mobile app to allow Android users to install the app
                outside of the Google ecosystem.
            </li>
            <li>
                I made a presentation to an online GIS discord group which was well received but unfortunately the
                recording didn't work out!
            </li>
            <li>
                Thanks for our first corporate sponsor last quarter, we hired our first developer, a girl from Serbia,
                to work on the mobile app. The developer helped us introduce Swipe between images while tagging, persist
                non-uploaded or captured images after closing the app, helped us introduce other alphabets like Arabic
                and special characters to allow for Spanish and Portugese and more!
            </li>
            <li>
                Back on web, we introduced a new user Dashboard that shows you how much litter you uploaded and gives
                you cool stats about your contribution to OpenLitterMap. You can now download all your own data too, as
                well as everyone elses &lt;3
            </li>
            <li>
                Thanks to open source community work, several bugs were fixed like missing tags added and allow guests
                to download data for any country without having to sign up for an account.
            </li>
            <li>
                And probably more?? There is definitely a lot more to come. We are barely scratching the surface here
                but hopefully in the next update we might have 1.0 of the OpenLitterAi.
            </li>
            <li>
                In the mean time, we need as much images uploaded as possible. We also need help tagging boxes to images
                so if this is something you would like to help with please get in touch and I will give you access. All
                you need is a laptop and some spare time.
            </li>
            <li>
                As we transition into a post-covid world, hopefully we can start to organise more social-distance
                friendly outdoor activities. Currently, not even a university campus has had their litter mapped yet. If
                we organised people to work together in self-isolation, it would probably take about 15 minutes. Would
                you like to organise an event? Chat with others interested in the future of OLM? If so, please join our
                slack channel!
            </li>
            <li>Click here to join the OpenLitterMap Slack - We want to hear from you!</li>
            <li>
                Stay tuned for lots more exiting updates as we empower society with the critical environmental tools to
                share data on pollution.
            </li>
            <li>P.S. Our GoFundMe has reached 2%. We would really appreciate it if you could share it?</li>
        </ul>

        <div class="milestone">
            <strong>OpenLitterAI Training Tool Released!</strong> New bounding box tool for computer vision training,
            120,000+ uploads, first hired developer from Serbia, and mobile app now in 6 languages!
        </div>

        <p class="signature">
            Looking forward to sharing more updates with you again soon.<br /><br />Your personal open source litter
            mapping developer,<br />Seán @ OpenLitterMap
        </p>
    </div>
</template>

<script setup>
// Update metadata is defined in Changelog.vue
</script>

<style scoped>
.update-content h2 {
    color: #2d3748;
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0 0 1.5rem 0;
}

.update-content h3 {
    color: #4a5568;
    font-size: 1.35rem;
    font-weight: 600;
    margin: 2rem 0 1rem 0;
}

.update-content p {
    margin-bottom: 1.25rem;
    font-size: 1.05rem;
    color: #2d3748;
    line-height: 1.8;
}

.update-content ul {
    margin: 1.5rem 0;
    padding-left: 0;
    list-style: none;
}

.update-content li {
    padding: 0.75rem 0 0.75rem 2rem;
    position: relative;
    margin-bottom: 0.5rem;
}

.update-content li::before {
    content: '→';
    position: absolute;
    left: 0;
    color: #4299e1;
    font-weight: bold;
    font-size: 1.2rem;
}

.update-content strong {
    color: #2d3748;
    font-weight: 600;
}

.milestone {
    background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
    border-left: 4px solid #667eea;
    padding: 1.5rem 2rem;
    margin: 2rem 0;
    border-radius: 0 8px 8px 0;
}

.milestone strong {
    color: #5a67d8;
    font-size: 1.1rem;
}

.signature {
    margin-top: 3rem;
    padding-top: 2rem;
    border-top: 2px solid #e2e8f0;
    font-style: italic;
    color: #718096;
    font-size: 1.05rem;
}
</style>
