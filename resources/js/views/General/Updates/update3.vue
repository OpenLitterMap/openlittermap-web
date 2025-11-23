<template>
    <div class="update-content">
        <h2>The updates continue!</h2>

        <p>Dear Subscriber,</p>

        <p>
            I hope you are off to a good start with #PlasticFreeJuly. All around the world people are choosing to refuse
            plastic products because of the impact plastic is having on our only habitable planet. We urgently need to
            reduce our plastic footprint and you can help educate people by opening up data on litter, which
            openlittermap facilitates by design. Here are some updates since the last email...
        </p>

        <ul>
            <li>
                The Top-10 litter mappers (who have either their name or username as public) now appear right at the top
                of openlittermap.com/maps! Can you compete for a spot? This is a great way to advertise a business or
                your portfolio. You could even put it on your CV! By default when you upload to OpenLitterMap you are
                made anonymous but you can change this by logging in and visiting openlittermap.com/settings/privacy.
            </li>
            <li>
                Over 20 new items have been added in including 7 in soft-drinks including Paper cups, Juice Cartons,
                Juice Bottles, Juice Packets, Ice Tea Bottles, Ice Tea Cans and Energy Cans.
            </li>
            <li>
                You can now map 7 new items in Food-related waste including Styfoam plates, Napkins, Sauce packets,
                Glass Jars, Glass jar lids and large and small crisp (or as they say in the US 'chip') packets...
            </li>
            <li>
                and 3 new items in Smoking-related waste including filters, filter boxes and plastic cigarette box
                packaging (Cellophane) and 3 new items in the 'Other' category including Ear-plugs for music, Hair ties
                and washing up bottles!
            </li>
            <li>That brings the total number of items you can map, view and download up to 120!</li>
        </ul>

        <p>
            All data from OpenLitterMap.com is free to view by anyone and download by any logged-in user. Want to
            download all the data for a country, state or city? 1-click and it's yours- Ready for import by anyone into
            R, GIS, Excel or your favorite problem-solving development environment. Perhaps the greatest strength of
            openlittermap is just how easily accessible it is to download lots of data but it is up to you to create the
            data for researchers and authorities to use.
        </p>

        <div class="milestone">
            <strong>Another milestone!</strong> We have reached 1,000 verified images in our database and over 3,500
            verified litter items! All of this data is free to view or download by anyone and is 100% verified and
            legitimate. OpenLitterMap is the only litter-mapping provider that verifies each and every image!
        </div>

        <h3>I need your help.</h3>

        <p>
            OpenLitterMap is the world's most comprehensive litter-mapping platform. No other litter-mapping providers
            map and analyse litter geospatially, quantitatively and categorically and make all the data Free, Open and
            Very Easy to download by Country, State and City. This is a huge strength of OpenLitterMap. Researchers
            don't need to go trawling through 3rd party API's to try and get their hands on the data, it just works. As
            a geospatial analyst by training I understand that this is the data we need stop plastic going into the
            ocean.
        </p>

        <h3>What can you do?</h3>

        <p>
            Quite a lot. You can collect and submit data. On a free account you can submit one image a day from levels
            0-4, two images a day from levels 5-9 and 3 images a day from level 10. But if you want to significantly
            increase your upload allowance, I need help covering the costs of storing and processing your data and
            everyone elses data on the web. If you like where this website is going, for as little as 30 cents a day or
            for a maximum of a euro / a dollar (and 10 cents) a day, you can finance the development of OpenLitterMap.
            Governments are not interested in exposing the pollution caused by the products of private businesses. It
            wouldn't even occur to them. Therefore, litter mapping and crowdfunding is entirely up to us. I have already
            put 2 Masters into this, which has cost me an arm and a leg. I hope you like where this project is going and
            want to see it improve and continue. By subscribing for €9, €20 or €30 a month, you can help me build this
            platform and we can find new ways to stop plastic going into the ocean.
        </p>

        <h3>Finally, here are some tips for using OpenLitterMap</h3>

        <ul>
            <li>Make sure each litter item is clearly identifiable. You are the one who has to process them later!</li>
            <li>
                If you do not process your image(s) successfully, they will be sent back to you, so in your own
                best-interests please try and get it right the first time.
            </li>
            <li>You can add as many images as into 1 photo as you want.</li>
            <li>
                The maps are a free educational tool to use. You can use them for free at
                openlittermap.com/maps/Country/State/Location/map
            </li>
            <li>
                You can toggle between different categories of related waste eg Smoking, Coffee, Softdrinks (and now
                Coastal and Pathway too!)..
            </li>
            <li>
                All data is free to download per Country, State or sub-state (eg. City). You may begin problem-solving
                and raising awareness!
            </li>
            <li>Remember to enable your geotagging or OpenLitterMap will not accept your images.</li>
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
