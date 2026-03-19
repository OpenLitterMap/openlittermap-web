<template>
    <div class="update-content">
        <h2>Update #15 - Android App Online</h2>

        <p>Another huge leap forward for OpenLitterMap!</p>

        <ul>
            <li>
                OpenLitterMap is now available for Android if you follow this link. It's so new you can't even search
                for it yet!
            </li>
            <li>
                There may be some small bugs. If you find anything wrong, please contact me and I will resolve them
                ASAP.
            </li>
            <li>
                We have been given a generous 12-months worth of research credits from AWS. This allows us to upgrade
                our server from a baby to a monster, at no extra cost. This will allow more concurrent users to upload
                and download content significantly faster. The server migration is complete.
            </li>
            <li>
                Last month I gave a talk at the annual OpenStreetMap conference the State of the Map. You can watch the
                talk here.
            </li>
            <li>
                Next week I will be giving another talk at an OpenStreetMap conference (South-East Europe) which you can
                learn more about here.
            </li>
            <li>
                The week after that, I am traveling to my neighbours in Wales to meet with all Local Authorities in 1
                country for the first time. Wales is the first country in the world to take democratizing science on
                litter mapping seriously. Go Wales!!!!
            </li>
            <li>
                OpenLitterMap has been translated into Italian, but there is a bug that I need to fix before it works.
                Sorry Italy!!!
            </li>
            <li>
                OpenLitterMap is still looking for developers and others. Currently I am doing all of this by myself.
                Please help?
            </li>
            <li>
                Our community chat is starting to get more active. Please join us on Slack and help grow the community
                with this invite link.
            </li>
            <li>I promise I will update this horrible footer on one of our next updates.</li>
        </ul>

        <div class="milestone">
            <strong>Android Launched & AWS Support!</strong> Wales becomes the first country to take litter mapping
            seriously, and we've upgraded to a monster server thanks to AWS research credits.
        </div>

        <p class="signature">Happy Mapping-<br />Seán @ OpenLitterMap</p>
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
