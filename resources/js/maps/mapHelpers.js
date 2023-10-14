import i18n from '../i18n';
import moment from 'moment';

const helper = {
    /**
     * These options control how the popup renders
     * @see https://leafletjs.com/reference-1.7.1.html#popup-l-popup
     */
    popupOptions: {
        minWidth: window.innerWidth >= 768 ? 350 : 200, // allow smaller widths on mobile
        maxWidth: 600,
        maxHeight: window.innerWidth >= 768 ? 800 : 500, // prevent tall popups on mobile
        closeButton: true
    },

    /**
     * name, username = null || string
     *
     * @param admin
     */
    getAdminName: (admin) => {
        let str = "These tags were updated by ";

        if (admin.name || admin.username)
        {
            if (admin.name) str += admin.name;
            if (admin.username) str += ' @' + admin.username;
        }
        else
        {
            str += "an admin";
        }

        // at date
        str += "<br> at " + moment(admin.created_at).format('LLL');

        return str;
    },

    /**
     * Get the removed custom + pre-defined Tags for the litter popup
     */
    getRemovedTags: (removedTags) => {
        let str = "Removed Tags: ";

        if (removedTags.customTags)
        {
            removedTags.customTags.forEach(customTag => {
                str += customTag + " ";
            });

            str += "<br>";
        }

        if (removedTags.tags)
        {
            Object.keys(removedTags.tags).forEach(category => {
                Object.entries(removedTags.tags[category]).forEach((entry) => {
                    str += i18n.t(`litter.${category}.${entry[0]}`) + `(${entry[1]})`;
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
     * @returns {string}
     */
    parseTags: (tagsString, customTags, isTrustedUser) => {
        if (!tagsString && !customTags) {
            return isTrustedUser
                ? i18n.t('litter.not-tagged-yet')
                : i18n.t('litter.not-verified');
        }

        let tags = '';
        let a = tagsString ? tagsString.split(',') : [];

        a.pop();

        a.forEach(i => {
            let b = i.split(' ');

            if (b[0] === 'art.item') {
                tags += i18n.t('litter.' + b[0]) + '<br>';
            } else {
                tags += i18n.t('litter.' + b[0]) + ': ' + b[1] + '<br>';
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
    formatUserName: (name, username) => {
        return (name || username)
            ? `${i18n.t('locations.cityVueMap.by')} ${name ? name : ''} ${username ? '@' + username : ''}`
            : '';
    },

    /**
     * Formats the picked up text for usage in Photo popups
     *
     * @returns {string}
     * @param pickedUp
     */
    formatPickedUp: (pickedUp) => {
        return pickedUp
            ? `${i18n.t('litter.presence.picked-up')}`
            : `${i18n.t('litter.presence.still-there')}`;
    },

    /**
     * Formats the team name for usage in Photo popups
     *
     * @param teamName
     * @returns {string}
     */
    formatTeam: (teamName) => {
        return teamName
            ? `${i18n.t('common.team')} ${teamName}`
            : '';
    },

    /**
     * Formats the photo taken time for usage in Photo popups
     *
     * @param takenOn
     * @returns {string}
     */
    formatPhotoTakenTime: (takenOn) => {
        return i18n.t('locations.cityVueMap.taken-on') + ' ' + moment(takenOn).format('LLL');
    },

    /**
     * Returns the HTML that displays the Photo popups
     *
     * @param properties
     * @param url
     * @returns {string}
     */
    getMapImagePopupContent: (properties, url = null) => {
        const user = helper.formatUserName(properties.name, properties.username)
        const isTrustedUser = properties.filename !== '/assets/images/waiting.png';
        const customTags = properties.custom_tags?.join('<br>');
        const tags = helper.parseTags(properties.result_string, customTags, isTrustedUser);
        const takenDateString = helper.formatPhotoTakenTime(properties.datetime);
        const teamFormatted = helper.formatTeam(properties.team);
        const pickedUpFormatted = helper.formatPickedUp(properties.picked_up);
        const isLitterArt = properties.result_string && properties.result_string.includes('art.item');
        const hasSocialLinks = properties.social && Object.keys(properties.social).length;
        const admin = properties.admin ? helper.getAdminName(properties.admin) : null;
        const removedTags = properties.admin?.removedTags ? helper.getRemovedTags(properties.admin.removedTags) : '';

        return `
            <img
                src="${properties.filename}"
                class="leaflet-litter-img"
                onclick="document.querySelector('.leaflet-popup-close-button').click();"
                alt="Litter photo"
                ${(isTrustedUser ? '' : ('style="padding: 16px;"'))}
            />
            <div class="leaflet-litter-img-container">
                ${tags ? ('<div>' + tags + '</div>') : ''}
                ${customTags ? ('<div>' + customTags + '</div>') : ''}
                ${!isLitterArt ? ('<div>' + pickedUpFormatted + '</div>') : ''}
                <div>${takenDateString}</div>
                ${user ? ('<div>' + user + '</div>') : ''}
                ${teamFormatted ? ('<div class="team">' + teamFormatted + '</div>') : ''}
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
        }
        else {
            if (properties.users.find(user => user.user_id === userId)) {
                userCleanupInfo = '<p>You have joined the cleanup</p>'

                    if (userId === properties.user_id) {
                        userCleanupInfo += '<p>You cannot leave the cleanup you created</p>'
                    }
                    else {
                        userCleanupInfo += `<a
                            onclick="window.olm_map.$store.dispatch('LEAVE_CLEANUP', {
                                link: '${properties.invite_link}'
                            })"
                        >Click here to leave</a>`
                    }
            }
            else {
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
     * @param properties: Merchant object
     * @returns string (with html)
     */
    getMerchantContent: (properties) => {
        let photos = '';

        if (properties.photos.length > 0)
        {
            properties.photos.forEach(photo => {
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
    }
};

export {helper as mapHelper};
