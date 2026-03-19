<template>
    <div class="update-content">
        <h2>Update #19 - GoFundMe & OpenLitterMap.com v2.0 now online</h2>

        <p>Today, we are launching a GoFundMe to try and bring OpenLitterMap to the next stage.</p>

        <ul>
            <li>Here is a link to the GoFundMe Campaign.</li>
            <li>This includes our first promotional video! Please share it with as many people as you can.</li>
            <li>Included in the campaign is a new video showing how to use our App.</li>
            <li>
                We also just released OpenLitterMap.com v2.0 This is a huge improvement on the code I wrote 3-4 years
                ago. There is a new landing page All data on the global map now loads instantly. Wow! A few things are
                currently not working, like leaderboards, downloads and live updates, but hopefully I will get to fix
                them over the weekend (If I am not too busy sending out 1000s of emails). Adding new features is going
                to be a LOT easier. We are much closer to going open source! We have many more special annoucements and
                GOOD NEWS coming soon!
            </li>
            <li>
                Reminder that every Thursday, 6pm Irish time, we run a community zoom call for an hour. We need your
                help, and we would love to hear your opinion about the future of this platform (Your platform).
            </li>
            <li>If you would like to help, we would love to have you on our Slack channel!</li>
        </ul>

        <div class="milestone">
            <strong>v2.0 Launched & GoFundMe Live!</strong> Complete platform rewrite with instant global map loading,
            new landing page, and first promotional video - much closer to going open source!
        </div>

        <p class="signature">Thanks again,<br />Seán @ OpenLitterMap</p>
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
