<template>
    <div class="update-content">
        <h2>Update #16 - Mobile App v2 & New Video Now Online</h2>

        <p>I thought this might be an approriate time to share some good news!</p>

        <ul>
            <li>
                Version 2 of our apps are now avaiable for iOS and Android. These include:
                <ul style="margin-top: 0.5rem">
                    <li>Significantly improved photo gallery</li>
                    <li>Significantly improved login & signup</li>
                    <li>75% reduction in file size</li>
                    <li>Improved battery life</li>
                    <li>Less crashes</li>
                </ul>
                And there is lot more yet to come. We are just getting started.
            </li>
            <li>Earlier this week, I gave a talk about OpenLitterMap which you can see here. Starts at 4:00.</li>
            <li>I also did some text interviews. 1. Here is one from the US And 2. Here is another one from Sweden.</li>
            <li>
                We have passed 150,000 manually verified free and open citizen science data on litter and plastic
                pollution. We are 35% of the way to our next target (250,000) and 15% of the way to 1-million!
            </li>
            <li>
                The Open Knowledge Foundation awarded a grant to one of our members to run a litter and waste mapping
                event in Malawi which brought them from below 30th to 7th at the LitterWorldCup. - You can read about
                the event here. - Check out Map 1 - and Map 2 - here is all of the data for Malawi - We even made it
                into a newspaper!
            </li>
            <li>The UK made it into 1st place at the LitterWorldCup AND Global OpenLitterMap top-10 leaderboard.</li>
            <li>
                We are about to launch a new crowdfunding campaign which includes our first official video. I hope you
                will enjoy it and please share it!
            </li>
            <li>Our Slack channel now has more than 100 users. Come and join us using this invite link.</li>
            <li>Next, we will fix some remaining mobile bugs and introduce new and refactor existing items.</li>
        </ul>

        <div class="milestone">
            <strong>150,000 Data Points & App v2 Released!</strong> Malawi jumps to 7th place, UK takes 1st, and we're
            launching our first official video with a new crowdfunding campaign.
        </div>

        <p class="signature">
            Thank you for taking an interest in open citizen science data on pollution. Let's carry the current momentum
            forward and share data to help fix our most urgent global problems.<br /><br />Seán @ OpenLitterMap
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
