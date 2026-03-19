<template>
    <div class="update-content">
        <h2>Update #17 - Weekly Community Zoom Calls</h2>

        <p>It's been a while since our last update and I wanted to keep you in the loop.</p>

        <ul>
            <li>
                Every Thursday, from 6pm Irish / UK time (GMT+1), we will be running weekly zoom calls for less than an
                hour. We hope to follow this up with a global Litter Mapping Marathon every Saturday from 1-2pm your
                local time. If you are interested to know more about OpenLitterMap, how you can be a part of it, or
                maybe you are concerned about litter & plastic pollution, we would love for you to join us! About an
                hour before the call, we post the link into our Slack channel and Facebook group. If you are there,
                download the Slack app and say hello using this invite link, or search for "Open Litter Map" on
                facebook.
            </li>
            <li>
                Several new items & categories have been added to the web & mobile apps including Gloves, Facemasks
                (Sanitary), a new Dumping category (small, medium & large), a new Industrial category (oil, chemical
                spills) and more!
            </li>
            <li>
                After many delays, we are finally getting close to launching our first video. We will use this to launch
                a GoFundMe soon to try and bring OpenLitterMap to the next stage.
            </li>
            <li>
                For the last 12+ months I have been studying machine learning like crazy. I did several paid online
                courses, watched 100s of youtube videos and read a massive book. This week I finally started developing
                my first neural network - the OpenLitterAI.
            </li>
            <li>
                Although OpenLitterMap is not yet open source, we will be releasing the code whenever the development of
                the platform is supported or we can achieve MVP.
            </li>
            <li>
                Currently interviewing 2 junior and 1 senior developer to join the team & a senior marketing manager.
            </li>
            <li>
                Huge performace boost to The Litter World Cup which now loads all metdata for 80+ countries in <1 second
                down from ~5seconds.
            </li>
            <li>Several more grants were rejected. We are now 60+ rejected grants in a row!</li>
            <li>
                #TeamLitterUK have made it to 1st & 2nd place on the Global OpenLitterMap Leaderboard as well as 1st
                place at the #LitterWorldCup.
            </li>
            <li>
                OpenLitterMap has been almost entirely researched and developed by 1 person over a 10+ year period with
                very small amounts of crowdfunding. If you would like to learn more, come and join us this (or any)
                Thursday @ 6pm GMT +1 and help us advance high-qualtiy open citizen science on pollution.
            </li>
        </ul>

        <div class="milestone">
            <strong>Community Calls Launched & OpenLitterAI Begins!</strong> Weekly Thursday Zoom calls start, plus
            after 12+ months of studying machine learning, development on the first neural network has begun.
        </div>

        <p class="signature">Cheers,<br />Seán @ OpenLitterMap</p>
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
