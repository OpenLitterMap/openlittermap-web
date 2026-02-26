<template>
    <div class="update-content">
        <h2>Great News! OpenLitterMap is now available en Español.</h2>

        <ul>
            <li>
                <strong
                    >If you have used OpenLitterMap recently, you might need to delete cookies on your device if the
                    "add data" component is missing.</strong>
                <p>To delete cookies (if necessary):</p>
                <p><strong>On iPhone:</strong></p>
                <ol>
                    <li>Open Settings</li>
                    <li>Open the settings for Safari or for whatever browser you use</li>
                    <li>Scroll down to the bottom and select "Clear History and Website Data"</li>
                </ol>
                <p><strong>On Android:</strong></p>
                <ol>
                    <li>Open the browser and click the Menu button on your phone</li>
                    <li>Tap the More option</li>
                    <li>Scroll down to the Privacy settings section and tap the Clear cache option</li>
                    <li>Now tap the Clear all cookie data option</li>
                </ol>
                <p><strong>On Laptop:</strong></p>
                <ol>
                    <li>Chrome - Settings</li>
                    <li>Clear Browsing Data</li>
                    <li>Delete cookies</li>
                </ol>
            </li>
            <li>
                This means that <strong>other languages will be really easy to add in.</strong> What language would you
                like to see next?
            </li>
            <li>Several performance upgrades and legacy code refactors.</li>
            <li>
                2 New Blogs coming incl "The Smartest Cities Will Embace Open Data on Plastic Pollution" in the North
                American Public Sector Digest.
            </li>
            <li>
                OpenLitterMap was rejected by yet another grant holder (making that 5 or 6 unsuccessful grant
                applications in a row). There is not much support for open data on plastic pollution.
            </li>
            <li>
                Added a Progress Bar to the global page (<a href="openlittermap.com/maps">openlittermap.com/maps</a>) to
                determine how far away we are from meeting our next open data target.
            </li>
            <li>Removed the url to the annoying navigation section but its still there if you want @ /navigation</li>
        </ul>
        <div class="milestone">
            <strong>¡Hablamos Español!</strong> OpenLitterMap is now available in Spanish, making internationalization
            easy for future languages!
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
                community of Trash Dog owners! Feature your cute puppy on the
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

.update-content ol {
    margin: 0.5rem 0 1rem 2rem;
    padding-left: 0;
}

.update-content ol li {
    padding: 0.25rem 0;
    margin-bottom: 0.25rem;
}

.update-content ol li::before {
    content: none;
}

.update-content ul {
    margin: 1.5rem 0;
    padding-left: 0;
    list-style: none;
}

.update-content ul li {
    padding: 0.75rem 0 0.75rem 2rem;
    position: relative;
    margin-bottom: 0.5rem;
}

.update-content ul li::before {
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
