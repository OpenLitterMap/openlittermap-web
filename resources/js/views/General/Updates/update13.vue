<template>
    <div class="update-content">
        <h2>Update #13 - The Struggle Continues</h2>

        <p>It's been over 3 months since our last update!</p>

        <ul>
            <li>
                Plastic entering the marine environment is expected to continue to increase exponentially, from 8-12
                million tonnes in 2010 to ~70 million tonnes by 2025 (Jambeck, et al 2015).
            </li>
            <li>
                Plastic pollution is already believed to be etched permanently into the biosphere, including water, air,
                sea-salt, humans, soil and wildlife.
            </li>
            <li>
                My 20th grant application was rejected last week. This lack of support has had a tremendously negative
                impact on my mental health- more so than plastic pollution itself.
            </li>
            <li>
                In 2019, I plan to release Mobile Apps, Smart Contracts, A.I. and re-vamp the website. I will probably
                have to do this by myself, unless anyone out there would like to help me?
            </li>
            <li>
                Due to the complete lack of support, I have had no choice but to scrap the original idea for the
                non-profit OpenLitterMap Foundation. Instead, I have had to iterate over a business plan which will be
                executed once the technology is more developed. This is necessary to keep this project alive and
                growing. After all, not even the Pineapple Fund thought this project was worth supporting!
            </li>
            <li>
                A new OLM token will be released, rewarded to anyone crowdfunding (€1 = 1 OLM). Anyone crowdfunding
                prior to its release will recieve double the tokens. This new OLM token will give you voting rights on
                the future of the platform, as well as an opportunity be financially re-imbused by the OpenLitterMap
                Ecosystem. This will be explained in the Littercoin Whitepaper. I was really hoping to release that by
                now, but unfortunately there is no support for the development of this technology despite about 1,000
                tonnes of plastic believed to be going into the ocean every hour, children falling on needles, and the
                waste of public expenditure.
            </li>
            <li>
                We are going to build the most amazing data collection experience and source of open data on plastic
                pollution the world has ever seen. This will happen much faster if more people sign up to support or a
                grant holder figures out that open data on plastic pollution is actually really important.
            </li>
            <li>
                I'm looking for developers and others who think open data on plastic pollution is important to join my
                team!
            </li>
            <li>For a list of updates, keep in touch on social media.</li>
        </ul>

        <div class="milestone">
            <strong>20 grant rejections and counting.</strong> Despite the challenges, 2019 will bring Mobile Apps,
            Smart Contracts, and A.I. to help fight plastic pollution - with or without institutional support.
        </div>

        <p class="signature">Happy Mapping-<br />Seán @ OpenLitterMap.com</p>
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
