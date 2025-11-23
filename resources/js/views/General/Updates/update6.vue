<template>
    <div class="update-content">
        <h2>Hello again! Back with more good news!</h2>

        <ul>
            <li>
                Added a new <strong>Brands</strong> category. Now you can map and produce open data on over 20 of our
                favourite brands.
                <a href="https://twitter.com/OpenLitterMap/status/964140547668676609">Link to twitter announcement.</a>
            </li>
            <li>
                Anyone who was the first to upload from a Country, State or City can now claim compensation in
                Littercoin for each location you created. There are no limits as to how many locations you can create.
                If you have an outstanding balance you can see it when you log in at /profile.
            </li>
            <li>
                New options to mine Littercoin! Before the only option was upload 7 days in a row. Now you can earn 1
                Littercoin for every 100 images you submit correctly, in a row. Simply add your Ethereum wallet at
                openlittermap.com/settings/littercoin and wait for payment!
            </li>
            <li>
                I have launched the
                <a href="https://medium.com/@littercoin/open-litter-mapping-marathons-bf2cb222ab2b"
                    >Global OpenLitterMapping MARATHON</a
                >. Starting this Saturday and repeating every Saturday, from 2-4pm, anywhere in the world. Would you
                like to organize an event in your location? How much open data on litter can we collect in 2 hours?
            </li>
            <li>A prominent Blockchain advisor has joined the team! More on this later!</li>
            <li>
                The Presence button now works. You can determine if litter is still there, or has been taken away. By
                default OpenLitterMap assumes that litter is still there but if you pick up as you go you can change
                this default value in
                <a href="https://openlittermap.com/settings/presence">openlittermap.com/settings/presence.</a>
            </li>
            <li>
                Styling improvements- Changed the size of the popup bar to be mobile friendly, fixed the navbar on maps,
                improved text on signup page, moved the progress bar to the bottom of /profile and some other bits and
                pieces.
            </li>
            <li>
                Several new countries including Myanmar, Pakistan and New Zealand have been dynamically added to the
                database by new and old members. Check them out at
                <a href="https://openlittermap.com/maps">openlittermap.com/maps</a>. To "create" a new location, simply
                be the first to upload from there and OpenLitterMap will configure itself dynamically. You can earn 100
                Littercoin for each Country, 50 Littercoin for each "State" and 25 Littercoin for each "City".
            </li>
            <li>
                All images are now hosted on AWS S3. All images pre Jan 2018 were deleted to reduce costs. If you like
                this platform and want to see more and improved updates, please support it with €5 a month! There is a
                lot of work to be done!
            </li>
            <li>
                In case you missed the last update, after some credit from Amazons Activate Startup Program, all
                accounts can currently upload 1,000 images a day for free. Amazing what we can achieve with some simple
                credits! Imagine what we could achieve if a few people thought this was worth €5 a month?
            </li>
        </ul>

        <div class="milestone">
            <strong>Looking forward to 2018!</strong> Expect mobile apps and machine learning coming soon :-)
        </div>

        <p>
            All Data @ <a href="https://openlittermap.com">openlittermap.com</a> is Verified, Free and Open for anyone
            to download. We do not sell data, we want anyone to be able to access our data to solve these problems!
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
