<template>
    <div class="update-content">
        <h2>Say Hello to Littercoin (LTRX)</h2>

        <p>Dear Subscriber,</p>

        <p>As promised, the updates for OpenLitterMap continue. This week we have introduced:</p>

        <ul>
            <li>
                A New Cryptocurrency "Littercoin" - a digital tradeable asset on the Ethereum blockchain. See below.
            </li>
            <li>
                A New Category "Pathways" - you can now map Gutters, Drains, Kerb-holes and other pathways where Litter
                typically begins its journey to the ocean.
            </li>
            <li>
                Data is now organised by State or County. Eg what was USA - San Francisco is now USA - California - SF.
                You can download data at state/county level now too!
            </li>
            <li>Other 25 new items have been introduced including plastic bags, straws, crack pipes and more!!</li>
            <li>
                On openlittermap.com/profile where you tag litter to images, the categorisation tool now appears first
                and your statistics are at the bottom. This makes working on mobile significantly easier.
            </li>
        </ul>

        <p>
            All data from OpenLitterMap.com is free to view by anyone and download by any logged-in user. Want to
            download all the data for a country, state or city? 1-click and it's yours- Ready for import by anyone into
            R, GIS, Excel or your favorite problem-solving development environment.
        </p>

        <div class="milestone">
            <strong>Second milestone!</strong> We now have over 430 verified images in our database and over 1,500
            verified litter items. All of this data is free to view or download by anyone.
        </div>

        <h3>What is Littercoin?</h3>

        <p>
            Littercoin (LTRX) is a new digital asset on the Ethereum blockchain (a "Cryptocurrency" like Bitcoin or
            Ether) that you can now be paid in for mapping-litter. If you configure your Ethereum wallet on
            openlittermap.com/settings/littercoin and you upload at least 1 image 7-days in a row, you will get paid
            1.0000 Littercoin for your effort which you can use to pay anyone anywhere that will accept Littercoin as a
            payment or mode of discount. Would you like to accept Littercoin? If so please get in touch and learn how
            you can support this ecosystem.
        </p>

        <h3>What can Littercoin do?</h3>

        <p>
            Right now, not a whole lot. However in the near future, Littercoin will give you voting rights to determine
            the future of OpenLitterMap via the OpenLitterMapDAO, our own decentralized autonomous organisation which
            you can look forward to, so go ahead and start generating LTRX!
        </p>

        <h3>Support OpenLitterMap</h3>

        <p>
            Do you want to contribute to the development of OpenLitterMap? By making a €9, €20 or €30 monthly
            subscription I can afford to significanly increase your upload allowance and you can finance the development
            of this working, fun, open and accessible Citizen Science platform to mitigate against the increasing and
            multi-faceted threats of plastic-pollution.
        </p>

        <p>
            If you cannot afford a ~30-cent, 66-cent or 1-euro a day subscription to finance the development of
            OpenLitterMap and help cover the costs of running a website and processing large volumes of image data on
            the cloud, you may upload at least 1 image every day for free. This allowance will increase to 2-images a
            day when you reach Level-5 and 3-images a day when you reach Level-10. If you would like access to 3-images
            a day soon, you could sign up for 1-month to significantly increase your upload allowance, generate the
            necessary XP and cancel the subscription in 1-click @ openlittermap.com/settings/payments once you are
            logged-in.
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
