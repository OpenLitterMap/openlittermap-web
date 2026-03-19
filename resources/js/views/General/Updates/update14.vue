<template>
    <div class="update-content">
        <h2>Update #14 - Mobile App v1.0 Now Available on iOS</h2>

        <p>
            It's been 9 months since our last update. I have been working hard during this time and finally - it's time
            for an update.
        </p>

        <ul>
            <li>OpenLitterMap is now available on iOS. Android will follow shortly.</li>
            <li>
                This app will make data collection easier. There are some small bugs and many improvements to be made,
                which are already being worked on.
            </li>
            <li>
                With the web-app, our community crowdsourced over 85,000 pieces of litter. All of which have been
                manually verified and are completely free to download and re-use, for any purpose, without permission.
                If you can show me better quality open data, I will stop what I'm doing and promote that platform
                instead.
            </li>
            <li>
                For the first time, OpenLitterMap will be speaking at the annual OpenStreetMap conference, The State of
                the Map #sotm2019 in Heidelberg, Germany later this month.
            </li>
            <li>
                Last month OpenLitterMap met with the first local authority in Ireland to discuss how they can take best
                use of this platform and I have more meetings planned with various local authorities in the UK soon.
            </li>
            <li>
                We at the cusp of a global scientific rennaisance as never before was it possible to involve potentially
                millions of people in data collection. However, OpenLitterMap still hasn't even reached 1% of our
                funding target. We don't have a video to communicate open data, and I can't even justify working on this
                1-day per month despite being probably the only person in the world to study litter-mapping for over a
                decade.
            </li>
            <li>
                OpenLitterMap is available in German, French, Spanish, Turkish, Malay and English - thanks to some
                amazing volunteers! We plan to translate OpenLitterMap into as many languages as possible. We have
                released our language files as open source which anyone is welcome to fork, or download and email back
                to us. (PS - please communicate with us so that efforts are not being duplicated.)
            </li>
            <li>
                OpenLitterMap is looking for developers and others. Unfortunately, more than 40 grant holders have
                rejected to support the development of OpenLitterMap and our kickstarter couldn't even reach one percent
                so as much as we would love to get this moving - we can't offer anyone a living wage for their work yet.
            </li>
            <li>For more regular updates, please keep in touch and help us grow on social media.</li>
        </ul>

        <div class="milestone">
            <strong>iOS App Launched!</strong> After a decade of work, OpenLitterMap is now available on mobile with
            85,000+ verified data points and speaking at State of the Map 2019 in Germany.
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
