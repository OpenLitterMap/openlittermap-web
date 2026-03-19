<template>
    <div class="update-content">
        <h2>Thank you for subscribing to OpenLitterMap!</h2>

        <p>Dear Subscriber,</p>

        <p>
            Thank you for being one of the first people to sign up to OpenLitterMap. You are obviously in the know about
            our big problem and I would like to remind you about this working service that I am creating independently.
            It might not be perfect, but you can expect - and you are invited to finance - improvements. Here are some
            of the upgrades since the launch last month:
        </p>

        <ul>
            <li>A new Coastal category for marine-litter;</li>
            <li>
                On the maps, users now have the option to be credited for each image they upload by their full name
                and/or the username/organisation they are legally entitled to represent (A great way to advertise your
                public portfolio/organisation);
            </li>
            <li>
                Several new countries have been automatically added to the database including India, Australia, Denmark,
                UK, US, Spain and more;
            </li>
            <li>You can now view data from areas that have only 1 verified litter point.</li>
            <li>This email thread has begun.</li>
        </ul>

        <p>
            All data from OpenLitterMap.com is free to view by anyone and download by any logged-in user. Want to
            download all the data for a country? 1-click and it's yours- ready for import by anyone into R, GIS, Excel
            or your favorite problem-solving development environment.
        </p>

        <div class="milestone">
            <strong>First milestone!</strong> We now have over 250 verified images in our database and over 1,000
            verified litter items.
        </div>

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
                If you do not process them successfully, the image will be sent back to you, so in your own
                best-interests please try and get it right the first time.
            </li>
            <li>You can add as many images as into 1 photo as you want.</li>
            <li>
                You could collect 1000 images in your own time and then when you are ready, pay €30 for a Professional
                Account, upload all of them, and process them in your own time.
            </li>
            <li>
                The maps are a free educational tool to use. You can find them at
                openlittermap.com/maps/Country/Location/map
            </li>
            <li>You can toggle between different categories of related waste eg Smoking, Coffee..</li>
            <li>All data is free to download per Country or sub-country Location. You may begin problem-solving!</li>
            <li>Remember to enable your geotagging or OpenLitterMap will not accept your images.</li>
        </ul>

        <p class="signature">Happy Mapping-<br />Seán Lynch @ OpenLitterMap.com</p>
    </div>
</template>

<script setup>
// Update metadata is defined in the parent Changelog.vue
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
