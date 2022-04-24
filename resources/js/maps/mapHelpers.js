import i18n from '../i18n';
import moment from 'moment';

const helper = {
    /**
     * These options control how the popup renders
     * @see https://leafletjs.com/reference-1.7.1.html#popup-l-popup
     */
    popupOptions: {
        minWidth: 350,
        maxWidth: 600,
        maxHeight: 700,
        closeButton: true
    },

    /**
     * Returns th HTML that displays tags on Photo popups
     *
     * @param tagsString
     * @param isTrustedUser
     * @returns {string}
     */
    parseTags: (tagsString, isTrustedUser) => {
        if (!tagsString) {
            return isTrustedUser
                ? i18n.t('litter.not-tagged-yet')
                : i18n.t('litter.not-verified');
        }

        let tags = '';
        let a = tagsString.split(',');

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
     * Todo translate 'team'
     * @param teamName
     * @returns {string}
     */
    formatTeam: (teamName) => {
        return teamName
            ? `Team ${teamName}`
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
        const tags = helper.parseTags(properties.result_string, isTrustedUser);
        const takenDateString = helper.formatPhotoTakenTime(properties.datetime);
        const teamFormatted = helper.formatTeam(properties.team);
        const pickedUpFormatted = helper.formatPickedUp(properties.picked_up);
        const isLitterArt = properties.result_string && properties.result_string.includes('art.item');

        return `
            <img
                src="${properties.filename}"
                class="leaflet-litter-img"
                onclick="document.querySelector('.leaflet-popup-close-button').click();"
                alt="Litter photo"
                ${(isTrustedUser ? '' : ('style="padding: 16px;"'))}
            />
            <div class="leaflet-litter-img-container">
                <p>${tags}</p>
                ${!isLitterArt ? ('<p>' + pickedUpFormatted + '</p>') : ''}
                <p>${takenDateString}</p>
                ${user ? ('<p>' + user + '</p>') : ''}
                ${teamFormatted ? ('<p>' + teamFormatted + '</p>') : ''}
                <div class="social-container">
                    ${properties.social?.twitter ? '<a target="_blank" href="' + properties.social.twitter + '"><i class="fa fa-twitter"></i></a>' : ''}
                    ${properties.social?.facebook ? '<a target="_blank" href="' + properties.social.facebook + '"><i class="fa fa-facebook"></i></a>' : ''}
                    ${properties.social?.instagram ? '<a target="_blank" href="' + properties.social.instagram + '"><i class="fa fa-instagram"></i></a>' : ''}
                    ${properties.social?.linkedin ? '<a target="_blank" href="' + properties.social.linkedin + '"><i class="fa fa-linkedin"></i></a>' : ''}
                    ${properties.social?.reddit ? '<a target="_blank" href="' + properties.social.reddit + '"><i class="fa fa-reddit"></i></a>' : ''}
                </div>
                ${url ? '<a class="link" target="_blank" href="' + url + '"><i class="fa fa-link fa-rotate-90"></i></a>' : ''}
            </div>`;
    }
};

export {helper as mapHelper};
