<template>
    <div class="update-content">
        <h2>Breaking Barriers in 2018</h2>

        <p>Hey all, a few updates from OpenLitterMap</p>

        <ul>
            <li>
                <strong>Anyone can upload 1,000 images a day until further notice. Wow!</strong>
            </li>
            <li>I did a <a href="https://www.youtube.com/watch?v=my7Cx-kZhT4">Ted Talk</a>! and I submitted a scientific software article for peer review to Open Geospatial Data, Software and
                Standards.
            </li>
            <li>Significant pr
                <li>Significant progress on native mobile apps which will launch in 2018.</li>
                <li>We have achieved our first successful machine learning identification. This is huge.</li>
                <li>fixed the Total Litter count number formatting and a few other improvements.</li>
            </li>
            <li>
                Several new countries have been dynamically added to the database by new members. Check them out at
                <a href="https://openlittermap.com/maps">openlittermap.com/maps</a>
            </li>
            <li>
                The OpenLitterMap kickstarter failed. We managed to raise just €80 from th                The OpenLitterMap kickstarter failed. We managed to raise just €80 from the €20,000 sought. Because very
                few people are interested in financing OpenLitterMap with €5-10/$ a month, several new sources of
                funding are being investigated which is taking a lot of time and preperation.the team. Do you know a developer or graphic designer or someone
                with marketing who wants to help?
            </li>
            <li>The upload from iOS 11.1 wasn't working but in iOS 11.2 it was fixed. Thanks Apple!</li>
        </ul>

        <div class="milestone">
            <strong>Looking forward to 2018!</strong> Expect mobile apps and machine learning coming soon
        </div>

        <p>
            All Data @ <a href="https://openlittermap.com">openlittermap.com</a> is Free and Open for anyone to
            download.
        </p>

        <h3>What can you do?</h3>

        <p>Upload and process a few images a day.</p>

        <p>Like and share on social media, retweet.</p>

        <p>Please support with €5, €10, €20 or €30 a month to finance the development of OpenLitterMap.</p>

        <h3>Finally, here are some tips for using OpenLitterMap</h3>

        <ul>
            <li>
                Make sure each litter item is clearly identifiable. You are the one who has to process them later! This
                will get easier with machine learning soon ;-)
            </li>
            <li>
                You can map an entire beach or street clean in 1 photo if you want, but it depends what kind of story
                you want to tell.
            </li>
            <li>
                The maps are a free educational tool to use. You can use them for free at
                openlittermap.com/maps/Country/State/City/map or change /map to /options to filter by time and change
                the size of the hex. This is a free tool at your disposal to educate people about plastic pollution.
            </li>
            <li>
                You can toggle between different categories of related waste eg Smoking, Coffee, Softdrinks (and
                Coastal, Pathway and Art now too!)..
            </li>
            <li>All data is free to download per Country, State or sub-state (eg. City).</li>
            <li>
                Remember to enable your geotagging or OpenLitterMap will not accept your images. Instructions are sent
                out with an email post Aug-17.
            </li>
            <li>Sit back and expect more updates</li>
        </ul>

        <p class="signature">Happy Mapping-<br />Seán Lynch @ OpenLitterMap.com</p>
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
