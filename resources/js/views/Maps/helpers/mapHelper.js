import moment from 'moment';
import { CLUSTER_ZOOM_THRESHOLD } from './constants.js';
import { updateLocationInURL } from './urlHelpers.js';
import { renderLeafletPopup } from './layerHelpers.js';
import { addGlifyPoints, removeGlifyPoints } from './glifyHelpers.js';

export const mapHelper = {
    /**
     * These options control how the popup renders
     * @see https://leafletjs.com/reference-1.7.1.html#popup-l-popup
     */
    popupOptions: {
        minWidth: window.innerWidth >= 768 ? 350 : 200, // allow smaller widths on mobile
        maxWidth: 600,
        maxHeight: window.innerWidth >= 768 ? 800 : 500, // prevent tall popups on mobile
        closeButton: true,
    },

    /**
     * Handle map updates when user drags or zooms
     * @param {Object} params - Parameters object
     * @param {L.Map} params.mapInstance - Leaflet map instance
     * @param {Object} params.globalMapStore - Global map store
     * @param {Object} params.clusters - Clusters layer
     * @param {Object} params.points - Points layer
     * @param {Number} params.prevZoom - Previous zoom level
     * @param {Function} params.t - Translation function
     * @param {Number} params.page - Page number for pagination
     * @returns {Object} - Updated state
     */
    async handleMapUpdate({ mapInstance, globalMapStore, clusters, points, prevZoom, t, page = 1 }) {
        if (!mapInstance) return { points, prevZoom };

        updateLocationInURL(mapInstance);

        const bounds = mapInstance.getBounds();
        const bbox = {
            left: bounds.getWest(),
            bottom: bounds.getSouth(),
            right: bounds.getEast(),
            top: bounds.getNorth(),
        };
        const zoom = Math.round(mapInstance.getZoom());

        // We don't want to make a request at zoom level 2-5 if the user is just panning the map.
        // At these levels, we just load all global data for now
        if ([2, 3, 4, 5].includes(zoom) && zoom === prevZoom) {
            return { points, prevZoom };
        }

        // Remove points when zooming out or changing view
        if (points) {
            removeGlifyPoints(points, mapInstance);
            points = null;
        }

        // Clear clusters layer when switching between cluster and point view
        if (zoom < CLUSTER_ZOOM_THRESHOLD && prevZoom >= CLUSTER_ZOOM_THRESHOLD) {
            // Moving from points to clusters - ensure clusters layer is clean
            clusters.clearLayers();
        } else if (zoom >= CLUSTER_ZOOM_THRESHOLD && prevZoom < CLUSTER_ZOOM_THRESHOLD) {
            // Moving from clusters to points - ensure clusters are removed
            clusters.clearLayers();
        }

        // Get filters from url
        const searchParams = new URLSearchParams(window.location.search);
        const year = parseInt(searchParams.get('year')) || null;
        const fromDate = searchParams.get('fromDate') || null;
        const toDate = searchParams.get('toDate') || null;
        const username = searchParams.get('username') || null;

        // Get Clusters or Points
        if (zoom < CLUSTER_ZOOM_THRESHOLD) {
            points = await this.handleClusterView({
                globalMapStore,
                clusters,
                zoom,
                bbox,
                year,
                points,
                mapInstance,
            });
        } else {
            points = await this.handlePointsView({
                mapInstance,
                globalMapStore,
                clusters,
                prevZoom,
                zoom,
                bbox,
                year,
                fromDate,
                toDate,
                username,
                t,
                page,
            });
        }

        return { points, prevZoom: zoom };
    },

    /**
     * Handle cluster view (zoom < CLUSTER_ZOOM_THRESHOLD)
     */
    async handleClusterView({ globalMapStore, clusters, zoom, bbox, year, points, mapInstance }) {
        // Remove any remaining glify points
        if (points) {
            removeGlifyPoints(points, mapInstance);
        }

        // Remove photo id and filters from the url when zooming out
        const url = new URL(window.location.href);
        url.searchParams.delete('fromDate');
        url.searchParams.delete('toDate');
        url.searchParams.delete('username');
        url.searchParams.delete('photo');
        url.searchParams.delete('page');
        window.history.pushState(null, '', url);

        try {
            await globalMapStore.GET_CLUSTERS({ zoom, bbox, year });
            clusters.clearLayers();
            clusters.addData(globalMapStore.clustersGeojson);
        } catch (error) {
            console.error('get clusters error', error);
        }

        return null; // No points in cluster view
    },

    /**
     * Handle points view (zoom >= CLUSTER_ZOOM_THRESHOLD)
     */
    async handlePointsView({
        mapInstance,
        globalMapStore,
        clusters,
        prevZoom,
        zoom,
        bbox,
        year,
        fromDate,
        toDate,
        username,
        t,
        page = 1,
    }) {
        // Clear cluster layer if we were in cluster mode
        if (prevZoom < CLUSTER_ZOOM_THRESHOLD) {
            clusters.clearLayers();
        }

        // const layers = getActiveLayers();
        const layers = [];

        try {
            await globalMapStore.GET_POINTS({
                zoom,
                bbox,
                layers,
                year,
                fromDate,
                toDate,
                username,
                page,
                per_page: 300,
            });

            // Add the new points
            const points = addGlifyPoints(globalMapStore.pointsGeojson, mapInstance, t);

            // If there is a photo id in the url, open it
            const urlParams = new URLSearchParams(window.location.search);
            const photoId = parseInt(urlParams.get('photo'));

            if (photoId && globalMapStore.pointsGeojson.features.length) {
                const feature = globalMapStore.pointsGeojson.features.find((f) => f.properties.id === photoId);

                if (feature) {
                    renderLeafletPopup(
                        feature,
                        [feature.geometry.coordinates[1], feature.geometry.coordinates[0]], // [lat, lng] for Leaflet
                        t,
                        mapInstance
                    );
                }
            }

            return points;
        } catch (error) {
            console.log('get points error', error);
            return null;
        }
    },

    /**
     * name, username = null || string
     *
     * @param admin
     */
    getAdminName: (admin) => {
        let str = 'These tags were updated by ';

        if (admin.name || admin.username) {
            if (admin.name) str += admin.name;
            if (admin.username) str += ' @' + admin.username;
        } else {
            str += 'an admin';
        }

        // at date
        str += '<br> at ' + moment(admin.created_at).format('LLL');

        return str;
    },

    /**
     * Get the removed custom + pre-defined Tags for the litter popup
     */
    getRemovedTags: (removedTags, t) => {
        let str = 'Removed Tags: ';

        if (removedTags.customTags) {
            removedTags.customTags.forEach((customTag) => {
                str += customTag + ' ';
            });

            str += '<br>';
        }

        if (removedTags.tags) {
            Object.keys(removedTags.tags).forEach((category) => {
                Object.entries(removedTags.tags[category]).forEach((entry) => {
                    str += t(`litter.${category}.${entry[0]}`) + `(${entry[1]})`;
                });

                str += '<br>';
            });
        }

        return str;
    },

    /**
     * Scrolls the popup to its bottom if the image is very tall
     * Needed to reduce the flicker when the map renders the popups
     * @param event The event emitted by the leaflet map
     */
    scrollPopupToBottom: (event) => {
        let popup = event.popup?.getElement()?.querySelector('.leaflet-popup-content');

        if (popup) popup.scrollTop = popup.scrollHeight;
    },

    /**
     * Returns th HTML that displays tags on Photo popups
     *
     * @param tagsString
     * @param customTags
     * @param isTrustedUser
     * @param t
     * @returns {string}
     */
    parseTags: (tagsString, customTags, isTrustedUser, t) => {
        if (!tagsString && !customTags) {
            return isTrustedUser ? t('litter.not-tagged-yet') : t('litter.not-verified');
        }

        let tags = '';
        let a = tagsString ? tagsString.split(',') : [];

        a.pop();

        a.forEach((i) => {
            let b = i.split(' ');

            if (b[0] === 'art.item') {
                tags += t('litter.' + b[0]) + '<br>';
            } else {
                tags += t('litter.' + b[0]) + ': ' + b[1] + '<br>';
            }
        });

        return tags;
    },

    /**
     * Formats the user name for usage in Photo popups
     *
     * @param name
     * @param username
     * @returns {string}
     */
    formatUserName: (name, username, t) => {
        return name || username
            ? `${t('locations.cityVueMap.by')} ${name ? name : ''} ${username ? '@' + username : ''}`
            : '';
    },

    /**
     * Formats the picked up text for usage in Photo popups
     *
     * @returns {string}
     * @param pickedUp
     */
    formatPickedUp: (pickedUp, t) => {
        return pickedUp ? `${t('litter.presence.picked-up')}` : `${t('litter.presence.still-there')}`;
    },

    /**
     * Formats the team name for usage in Photo popups
     *
     * @param teamName
     * @returns {string}
     */
    formatTeam: (teamName, t) => {
        return teamName ? `${t('common.team')} ${teamName}` : '';
    },

    /**
     * Formats the photo taken time for usage in Photo popups
     *
     * @param takenOn
     * @returns {string}
     */
    formatPhotoTakenTime: (takenOn, t) => {
        return t('locations.cityVueMap.taken-on') + ' ' + moment(takenOn).format('LLL');
    },

    /**
     * Returns the HTML that displays the Photo popups
     *
     * @param properties
     * @param url
     * @param t
     * @returns {string}
     */
    getMapImagePopupContent: (properties, url = null, t) => {
        const user = mapHelper.formatUserName(properties.name, properties.username, t);
        const isTrustedUser = properties.filename !== '/assets/images/waiting.png';
        const customTags = properties.custom_tags?.join('<br>');
        const tags = mapHelper.parseTags(properties.result_string, customTags, isTrustedUser, t);
        const takenDateString = mapHelper.formatPhotoTakenTime(properties.datetime, t);
        const teamFormatted = mapHelper.formatTeam(properties.team, t);
        const pickedUpFormatted = mapHelper.formatPickedUp(properties.picked_up, t);
        const isLitterArt = properties.result_string && properties.result_string.includes('art.item');
        const hasSocialLinks = properties.social && Object.keys(properties.social).length;
        const admin = properties.admin ? mapHelper.getAdminName(properties.admin) : null;
        const removedTags = properties.admin?.removedTags
            ? mapHelper.getRemovedTags(properties.admin.removedTags, t)
            : '';

        return `
            <img
                src="${properties.filename}"
                class="leaflet-litter-img"
                onclick="document.querySelector('.leaflet-popup-close-button').click();"
                alt="Litter photo"
                ${isTrustedUser ? '' : 'style="padding: 16px;"'}
            />
            <div class="leaflet-litter-img-container">
                ${tags ? '<div>' + tags + '</div>' : ''}
                ${customTags ? '<div>' + customTags + '</div>' : ''}
                ${!isLitterArt ? '<div>' + pickedUpFormatted + '</div>' : ''}
                <div>${takenDateString}</div>
                ${user ? '<div>' + user + '</div>' : ''}
                ${teamFormatted ? '<div class="team">' + teamFormatted + '</div>' : ''}
                ${hasSocialLinks ? '<div class="social-container">' : ''}
                    ${properties.social?.personal ? '<a target="_blank" href="' + properties.social.personal + '"><i class="fa fa-link"></i></a>' : ''}
                    ${properties.social?.twitter ? '<a target="_blank" href="' + properties.social.twitter + '"><i class="fa fa-twitter"></i></a>' : ''}
                    ${properties.social?.facebook ? '<a target="_blank" href="' + properties.social.facebook + '"><i class="fa fa-facebook"></i></a>' : ''}
                    ${properties.social?.instagram ? '<a target="_blank" href="' + properties.social.instagram + '"><i class="fa fa-instagram"></i></a>' : ''}
                    ${properties.social?.linkedin ? '<a target="_blank" href="' + properties.social.linkedin + '"><i class="fa fa-linkedin"></i></a>' : ''}
                    ${properties.social?.reddit ? '<a target="_blank" href="' + properties.social.reddit + '"><i class="fa fa-reddit"></i></a>' : ''}
                ${hasSocialLinks ? '</div>' : ''}
                ${url ? '<a class="link" target="_blank" href="' + url + '"><i class="fa fa-share-alt"></i></a>' : ''}
                ${admin ? '<p class="updated-by-admin">' + admin + '</p>' : ''}
                ${removedTags ? '<p class="updated-by-admin">' + removedTags + '</p>' : ''}
            </div>`;
    },

    /**
     * Returns the HTML that displays on each Cleanup popup
     *
     * @param properties
     * @param userId
     * @returns {string}
     */
    getCleanupContent: (properties, userId = null) => {
        let userCleanupInfo = ``;

        if (userId === null) {
            userCleanupInfo = `Log in to join the cleanup`;
        } else {
            if (properties.users.find((user) => user.user_id === userId)) {
                userCleanupInfo = '<p>You have joined the cleanup</p>';

                if (userId === properties.user_id) {
                    userCleanupInfo += '<p>You cannot leave the cleanup you created</p>';
                } else {
                    userCleanupInfo += `<a
                            onclick="window.olm_map.$store.dispatch('LEAVE_CLEANUP', {
                                link: '${properties.invite_link}'
                            })"
                        >Click here to leave</a>`;
                }
            } else {
                userCleanupInfo = `<a
                    onclick="window.olm_map.$store.dispatch('JOIN_CLEANUP', {
                        link: '${properties.invite_link}'
                    })"
                >Click here to join</a>`;
            }
        }

        return `
            <div class="leaflet-cleanup-container">
                <p>${properties.name}</p>
                <p>Attending: ${properties.users.length} ${properties.users.length === 1 ? 'person' : 'people'}</p>
                <p>${properties.description}</p>
                <p>When? ${properties.startsAt}</p>
                <p>${properties.timeDiff}</p>
                ${userCleanupInfo}
            </div>
        `;
    },

    /**
     * Build the HTML to include in the popup content
     * @returns string (with html)
     * @param properties
     */
    getMerchantContent: (properties) => {
        let photos = '';

        if (properties.photos.length > 0) {
            properties.photos.forEach((photo) => {
                photos += `<div class="swiper-slide"><img style="height: 404px;" src="${photo.filepath}" alt="photo"></div>`;
            });
        }

        let websiteLink = properties.website
            ? `<a href="${properties.website}" target="_blank">${properties.website}</a>`
            : '';

        return `
            <div class="leaflet-cleanup-container">
                <div class="swiper-container">
                    <div class="swiper-wrapper">
                        ${photos}
                    </div>
                    <div class="swiper-button-prev" id="prevButton"></div>
                    <div class="swiper-button-next" id="nextButton"></div>
                </div>
                <p>Name: ${properties.name}</p>
                <p>About this merchant: ${properties.about ? properties.about : ''}</p>
                <p>Website: ${websiteLink}</p>
            </div>
        `;
    },
};
