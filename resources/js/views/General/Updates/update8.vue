<template>
    <div class="update-content">
        <h2>My paper was published today!</h2>

        <h3>Click the title to read:</h3>

        <p>
            <a
                href="https://link.springer.com/epdf/10.1186/s40965-018-0050-y?author_access_token=3LwbLtFAeDQSrDuxw_OjFW_BpE1tBhCbnbw3BuzI2RMWdnznM8UdTedfkG16-PR6Kq_etv5A9BWyBvhd3jY21zgbY8u62l4iSeGg3lv258nDY5RTrYezUaLYYk8smdGcGPSw0GptRg7kyWqiqp4C9Q%3D%3D"
            >
                <strong
                    >Lynch, S., (2018)., OpenLitterMap.com - Open Data on Plastic Pollution with Blockchain Rewards
                    (Littercoin)., Open Geospatial Data, Software, and Standards., 3:6</strong
                >
            </a>
        </p>

        <div class="milestone">
            <strong>First Peer-Reviewed Publication!</strong> The OpenLitterMap scientific paper is now published in
            Open Geospatial Data, Software, and Standards - a major milestone for the project!
        </div>

        <p>
            All Data @ <a href="https://openlittermap.com">openlittermap.com</a> is Verified, Free and Open for anyone
            to download. We want anyone to be able to access our data to solve problems.
        </p>

        <h3>What can you do?</h3>

        <p>Upload and process a few images a day.</p>

        <p>Organize a Litter Mapping Marathon, any Saturday, 2-4pm!</p>

        <p>Like, Share and Retweet!</p>

        <p>
            Please support the development of OpenLitterMap with €5+ a month, sit back and watch these developments
            unfold.
        </p>

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
            <li>Sit back and expect more updates ;-)</li>
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
