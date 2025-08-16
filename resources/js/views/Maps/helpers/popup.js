import moment from 'moment';
import L from 'leaflet';

export const popupHelper = {
    popupOptions: {
        minWidth: window.innerWidth >= 768 ? 350 : 200, // allow smaller widths on mobile
        maxWidth: 600,
        maxHeight: window.innerWidth >= 768 ? 800 : 500, // prevent tall popups on mobile
        closeButton: true,
    },

    /**
     * Returns the HTML that displays the Photo popups
     * Refactored to use the new summary data structure
     */
    getContent: (properties, url = null, t) => {
        const user = popupHelper.formatUserName(properties.name, properties.username, t);
        const isTrustedUser = properties.filename !== '/assets/images/waiting.png';
        const tags = popupHelper.parseSummaryTags(properties.summary, isTrustedUser, t);
        const takenDateString = popupHelper.formatPhotoTakenTime(properties.datetime, t);
        const teamFormatted = popupHelper.formatTeam(properties.team, t);
        const pickedUpFormatted = popupHelper.formatPickedUp(properties.picked_up, t);
        const isLitterArt = popupHelper.checkIfLitterArt(properties.summary);
        const hasSocialLinks = properties.social && Object.keys(properties.social).length;
        const admin = properties.admin ? popupHelper.getAdminName(properties.admin) : null;
        const removedTags = properties.admin?.removedTags
            ? popupHelper.getRemovedTags(properties.admin.removedTags, t)
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
     * Parse tags from the new summary data structure
     */
    parseSummaryTags: (summary, isTrustedUser, t) => {
        if (!summary?.tags) {
            return isTrustedUser ? t('litter.not-tagged-yet') : t('litter.not-verified');
        }

        let tags = '';

        // Iterate through categories
        Object.entries(summary.tags).forEach(([category, objects]) => {
            // Iterate through objects in each category
            Object.entries(objects).forEach(([objectKey, data]) => {
                // Add main item with quantity
                tags += `${t(`litter.${category}.${objectKey}`)}: ${data.quantity}<br>`;

                // Add materials if present
                if (data.materials && Object.keys(data.materials).length > 0) {
                    Object.entries(data.materials).forEach(([material, count]) => {
                        tags += `  ${t(`materials.${material}`)}: ${count}<br>`;
                    });
                }

                // Add brands if present
                if (data.brands && Object.keys(data.brands).length > 0) {
                    Object.entries(data.brands).forEach(([brand, count]) => {
                        tags += `  ${t(`brands.${brand}`) || brand}: ${count}<br>`;
                    });
                }

                // Add custom tags if present
                if (data.custom_tags && data.custom_tags.length > 0) {
                    data.custom_tags.forEach((customTag) => {
                        tags += `  ${customTag}<br>`;
                    });
                }
            });
        });

        return tags || (isTrustedUser ? t('litter.not-tagged-yet') : t('litter.not-verified'));
    },

    /**
     * Check if this is litter art based on summary data
     */
    checkIfLitterArt: (summary) => {
        if (!summary?.tags) return false;

        // Check if any category contains art items
        return Object.keys(summary.tags).some(
            (category) => category === 'art' || Object.keys(summary.tags[category]).some((item) => item.includes('art'))
        );
    },

    /**
     * Formats the user name for usage in Photo popups
     */
    formatUserName: (name, username, t) => {
        return name || username
            ? `${t('locations.cityVueMap.by')} ${name ? name : ''} ${username ? '@' + username : ''}`
            : '';
    },

    /**
     * Formats the picked up text for usage in Photo popups
     */
    formatPickedUp: (pickedUp, t) => {
        return pickedUp ? `${t('litter.presence.picked-up')}` : `${t('litter.presence.still-there')}`;
    },

    /**
     * Formats the team name for usage in Photo popups
     */
    formatTeam: (teamName, t) => {
        return teamName ? `${t('common.team')} ${teamName}` : '';
    },

    /**
     * Formats the photo taken time for usage in Photo popups
     */
    formatPhotoTakenTime: (takenOn, t) => {
        return t('locations.cityVueMap.taken-on') + ' ' + moment(takenOn).format('LLL');
    },

    /**
     * Get admin name and update info
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
     */
    scrollPopupToBottom: (event) => {
        let popup = event.popup?.getElement()?.querySelector('.leaflet-popup-content');

        if (popup) popup.scrollTop = popup.scrollHeight;
    },

    /**
     * Returns the HTML that displays on each Cleanup popup
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
     * Build the HTML to include in the popup content for merchants
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

    /**
     * Render a Leaflet popup for a specific feature
     */
    renderLeafletPopup: (feature, latlng, t, mapInstance) => {
        const content = popupHelper.getContent(feature.properties, null, t);

        const popup = L.popup(popupHelper.popupOptions).setLatLng(latlng).setContent(content).openOn(mapInstance);

        // Scroll popup to bottom after opening (for tall images)
        popup.on('popupopen', popupHelper.scrollPopupToBottom);

        return popup;
    },

    /**
     * Legacy method to parse old tag format - kept for backwards compatibility
     * @deprecated Use parseSummaryTags instead
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
};
