<template>
    <div class="update-content">
        <h2>Onwards.</h2>

        <p>Dear Subscriber,</p>

        <p>Hey all, a few updates from OpenLitterMap :-)</p>

        <ul>
            <li>A New Art Category to map #LitterArt anywhere.</li>
            <li>
                <strong>Forgot Password issue finally fixed.</strong>
            </li>
            <li>
                A new €5 a month plan to upload 5-images a day. All litter data you create at
                <a href="https://openlittermap.com">OpenLitterMap.com</a> is available for anyone to download for free.
            </li>
            <li>
                You can now link your Instagram account to your OpenLitterMap username if you can make the Top 10 Global
                OpenLitterMap Leaderboard.
            </li>
            <li>
                If you can make the Top 10 Global OpenLitterMap Leaderboard you can now upload 10 images a day for free.
                These restrictions will continue to improve over time.
            </li>
            <li>
                OpenLitterMap has been shortlisted for a European Youth Award. Results expected on Tuesday the 10th of
                October.
            </li>
            <li>
                Pie Charts @ openlittermap.com/maps (litter) are now optimized for mobile. Time-series & list of
                locations will continue to populate themselves dynamically and will OpenLitterMap will accept historical
                (geotagged) data.
            </li>
            <li>Removed some cluttering text and improved the style and useability a bit.</li>
            <li>
                I have a TED talk and a scientific publication due this week! Looking forward to sharing these with you
                soon.
            </li>
        </ul>

        <p>
            All data from OpenLitterMap.com is free to view by anyone and download by anyone. You can upload 1 image a
            day for free, an allowance that increases as you play the game. To significantly increase your upload
            capacity and make lots of data available on the web, please help finance OpenLitterMap with €5, €9, €20 or
            €30 a month and watch the improvements unfold :-)
        </p>

        <div class="milestone">
            <strong>Another milestone!</strong> We are approaching almost 10,000 Verified litter-items from almost 2,000
            Verified litter photos. I have manually verified all of the contents in each image to bring 100% of the data
            to Stage 2 Level of Verification.
        </div>

        <h3>I need your help.</h3>

        <p>
            All Data @ <a href="https://openlittermap.com">openlittermap.com</a> is Free and Open for anyone to
            download.
        </p>

        <h3>What can you do?</h3>

        <p>
            Please support with €5, €9, €20 or €30 a month to significantly increase your upload capacity and finance
            the development of OpenLitterMap.
        </p>

        <h3>Finally, here are some tips for using OpenLitterMap</h3>

        <ul>
            <li>Make sure each litter item is clearly identifiable. You are the one who has to process them later!</li>
            <li>
                If you do not process your image(s) successfully, they will be sent back to you, so in your own
                best-interests please try and get it right the first time.
            </li>
            <li>
                You can add as many images as into 1 photo as you want. Currently 100s which will increase to 1000s and
                10,000s on request.
            </li>
            <li>
                The maps are a free educational tool to use. You can use them for free at
                openlittermap.com/maps/Country/State/City/map
            </li>
            <li>
                You can toggle between different categories of related waste eg Smoking, Coffee, Softdrinks (and
                Coastal, Pathway and Art now too!)..
            </li>
            <li>
                All data is free to download per Country, State or sub-state (eg. City). You may begin problem-solving
                and raising awareness!
            </li>
            <li>
                Remember to enable your geotagging or OpenLitterMap will not accept your images. Instructions are sent
                out with an email post Aug-17.
            </li>
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
