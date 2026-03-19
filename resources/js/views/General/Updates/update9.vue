<template>
    <div class="update-content">
        <h2>We made some progress this week:</h2>

        <ul>
            <li>
                Complete overhaul of the <a href="/global">Global Map</a>. Reduced the load time from over 20 to under 3
                seconds.
            </li>
            <li>
                Added a Progress bar
                <a href="/maps">to show how close we are to reaching our next open data target.</a> We are approaching
                50,000 open data on litter! Please tag or share with someone who can help.
            </li>
            <li>
                Instead of "<a href="https://www.openlittermap.com/maps/United States of America/"
                    >openlittermap.com/maps/United States of America/</a
                >" you can use a 2-letter shortcode eg
                <a href="https://openlittermap.com/maps/us">openlittermap.com/maps/us</a>
            </li>
            <li>Refactored a lot of backend code and removed a lot of redudant code. Things should work smoother.</li>
            <li>Significantly improved the welcome email (and the update email)</li>
            <li>Added 20 new Irish Brands on request. Lots of more items are identified that I need to add in.</li>
            <li>
                Our Provisional OpenLitterMap Ambassador to the UK Andrew Russell released
                <a href="https://twitter.com/AndyRussell128/status/1019251019392389120">this amazing music video!!</a>
            </li>
            <li>
                Following my peer reviewed paper in Open Geospatial Data, Software and Standards, I published a blog
                post on Springer Open. Within 2 weeks it was their most read article!
                <a
                    href="http://blogs.springeropen.com/springeropen/2018/07/04/litter-mapping-is-a-new-field-of-citizen-science-and-blockchain-rewards-offer-a-whole-new-paradigm/"
                >
                    http://blogs.springeropen.com/springeropen/2018/07/04/litter-mapping-is-a-new-field-of-citizen-science-and-blockchain-rewards-offer-a-whole-new-paradigm/</a
                >
            </li>
            <li>
                Temporarily removed the Pathways category (gutters, storm drains, sewers in the street where litter
                begins its journey to the ocean) due to too much confusion. Might add it in again later with improved
                documentation if people need it. The local council should have this data but just in case they don't
                want to give it away we can crowdsource it ourselves and put it on OpenStreetMap.
            </li>
            <li>
                Improved the Admin verification tool. More admins needed soon when data increases! We also need more
                data!!
            </li>
            <li>Native mobile apps are on the way!!</li>
            <li>
                I have been rejected by several grant holders. Unfortunately, not many people think open data on plastic
                pollution is important. Do you think this is worth supporting with the price of 1-2 cups of coffee per
                month?
            </li>
        </ul>

        <div class="milestone">
            <strong>Approaching 50,000 data points!</strong> Global map now loads in under 3 seconds (down from 20+),
            and our Springer blog post became their most read article in just 2 weeks!
        </div>

        <p>
            All Data @ <a href="https://openlittermap.com">openlittermap.com</a> is Verified, Free and Open for anyone
            to download. We want anyone to be able to access our data to solve problems.
        </p>

        <h3>What can you do?</h3>

        <p>Upload and process a few images a day.</p>

        <p>Organize a Litter Mapping Marathon, any time, any-where! Maybe this Saturday, 2-4pm?</p>

        <p>Like, Share and Retweet <a href="https://twitter.com/openlittermap">@OpenLitterMap</a>!</p>

        <p>
            Please support the development of OpenLitterMap with €5+ a month, sit back and watch these developments
            unfold.
        </p>

        <h3>Finally, here are some tips for using OpenLitterMap</h3>

        <ul>
            <li>
                You can map an entire beach or street clean in 1 photo if you want, but it depends what kind of story
                you want to tell.
            </li>
            <li>
                If you can't count what's in the photo, you can use Large/Random Dump in the Other category and rank it
                from 1-100.
            </li>
            <li>
                You are not just mapping litter, you are creating open data. Feel the Knowledge!! You can download your
                data too. Anyone can!
            </li>
            <li>
                Please don't include children in the photos, but feel welcome to take a picture of your dog and join our
                community of Trash Dog owners! Feature your cute pup on the
                <a href="https://openlittermap.com/global">global map</a> under the trash dog category!
            </li>
            <li>Sit back and expect more updates!</li>
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
