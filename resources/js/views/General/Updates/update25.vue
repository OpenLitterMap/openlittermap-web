<template>
    <div class="update-content">
        <h2>Update #25 - Big Improvements since $50,000 funding from cryptocurrency Cardano</h2>

        <p>There are plenty of updates to share since our last email on the 4th of August 2021.</p>

        <p>You can find most of them on our weekly blog but we will share the best bits here.</p>

        <ul>
            <li>
                <strong>Global leaderboard for all users.</strong> Yes, finally! We now have a global leaderboard of all
                6,100+ and growing members. Check it out and find your position at openlittermap.com/leaderboard. By
                default, new users are anonymous, so if you haven't already done so- visit settings and check your
                privacy options to see if your name or username are set to appear on the leaderboards. In the web-app
                settings, there are more options. You can choose to show a country flag and you can now include links to
                your social media accounts to promote them.
            </li>
            <li>
                <strong>Promote links to your social media accounts on OpenLitterMap.</strong> The more data you upload,
                the more points you score, and the more visibility your accounts get. Social media accounts will now
                appear in every popup on the global map and for every user in the global leaderboard. See this twitter
                post for examples.
            </li>
            <li>
                <strong>Weekly community calls.</strong> Every Thursday, 6pm GMT, we host a weekly live zoom call where
                we code live, launch updates, and test new features with the community while planning for the future and
                having fun. Want to help shape the future direction of OpenLitterMap? Join us.
            </li>
            <li>
                <strong>Huge mobile app improvements.</strong> It took a long time but the app is finally starting to
                work incredibly well! The photo gallery is now just totally smooth. You can now create a team and join a
                team on the app. You can see the leaderboard for all teams listed so far. There are a lot more new
                features and improvements left to add in, but the most recent update (v3.2.3) is really stable. Now that
                the app has reached stability, we will begin to work on new gamification features soon! Read more about
                recent app improvements here.
            </li>
            <li>
                <strong>Huge global map improvements.</strong> Seriously, just look at how all of this global data just
                loads instantly. Open the Global Map. All of the clusters are re-created every night at midnight. You
                can also change the data by year, or all time. Keep the global map open to see live notifications. Click
                a photo uploaded notification to zoom to that location and open the data! It's now possible to filter
                the global map point data by user, minDate and maxDate in the URL. Clusters of just 1 photos now appear
                on the global map. You can see how far North and South the openlittermap community has reached-
                incredible! Also added new options to sort locations click here to open tweet.
            </li>
            <li>
                <strong>Growing global community.</strong> It took a long time, but openlittermap is after getting
                really stable lately, and now we are attracting a much more engaged community. Every day, new people are
                joining our Slack channel and weekly community zoom calls. All data is going up. We are approaching
                500,000 tags and recently passed 250,000 images. I have given dozens of presentations across
                universities + institutions, met with people from UNEP and even gave a presentation to my local Cork
                City Council.
            </li>
            <li>
                <strong>Sent a 12 page report about my work to various Irish government oireachtas committees.</strong>
                You can read the report here. Worryingly, both the committees on Education, Research, Science, and
                Innovation and "climate action and environment" had nothing to say. The committee on EU affairs did not
                even reply about using citizen science as a means of building Peace Through Science in zones of conflict
                (like Northern Ireland). In the last 15 years of this research there has been a total of €0 in funding
                to support the development of OpenLitterMap in Ireland.
            </li>
            <li>
                <strong>Expect more updates soon!</strong> OpenLitterMap is staring to work well and its growing, but
                there is a lot more that needs to be done. We hope you will join us by mapping and sharing data on
                litter and plastic pollution in your community.
            </li>
        </ul>

        <div class="milestone">
            <strong>Platform Maturity Achieved!</strong> Global leaderboard, social media integration, smooth mobile app
            (v3.2.3), 250,000+ images, 500,000 tags approaching, and presentations to UNEP and Cork City Council.
            Community growing daily!
        </div>

        <p class="signature">
            Looking forward to sharing more updates with you again soon.<br /><br />Thanks for taking an interest in
            OpenLitterMap.<br /><br />Seán.
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
