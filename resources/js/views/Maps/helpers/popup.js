import moment from 'moment';
import L from 'leaflet';

/**
 * HTML Sanitization utility to prevent XSS attacks
 */
const htmlSanitizer = {
    /**
     * Escape HTML special characters to prevent XSS
     */
    escapeHtml(str) {
        if (str === null || str === undefined) return '';

        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    },

    /**
     * Sanitize URL for safe href usage
     */
    sanitizeUrl(url) {
        if (!url) return '';

        try {
            const parsed = new URL(url);
            // Only allow http(s) protocols for external links
            if (!['http:', 'https:'].includes(parsed.protocol)) {
                return '';
            }
            return url;
        } catch {
            // If URL parsing fails, check for relative URLs
            if (url.startsWith('/')) {
                return url;
            }
            return '';
        }
    },
};

export const popupHelper = {
    popupOptions: {
        minWidth: window.innerWidth >= 768 ? 350 : 200,
        maxWidth: 600,
        maxHeight: window.innerWidth >= 768 ? 800 : 500,
        closeButton: true,
        className: 'custom-popup',
    },

    /**
     * Returns the HTML that displays the Photo popups with XSS protection
     */
    getContent: (properties, url = null, t) => {
        // Provide fallback for translation function
        const translate = t || ((key) => key);

        // Sanitize all user-provided content
        const safeProps = {
            filename: htmlSanitizer.sanitizeUrl(properties.filename) || '/assets/images/waiting.png',
            name: htmlSanitizer.escapeHtml(properties.name),
            username: htmlSanitizer.escapeHtml(properties.username),
            team: htmlSanitizer.escapeHtml(properties.team),
            datetime: htmlSanitizer.escapeHtml(properties.datetime),
            picked_up: properties.picked_up,
            summary: properties.summary,
            social: {
                personal: htmlSanitizer.sanitizeUrl(properties.social?.personal),
                twitter: htmlSanitizer.sanitizeUrl(properties.social?.twitter),
                facebook: htmlSanitizer.sanitizeUrl(properties.social?.facebook),
                instagram: htmlSanitizer.sanitizeUrl(properties.social?.instagram),
                linkedin: htmlSanitizer.sanitizeUrl(properties.social?.linkedin),
                reddit: htmlSanitizer.sanitizeUrl(properties.social?.reddit),
            },
            admin: properties.admin
                ? {
                      name: htmlSanitizer.escapeHtml(properties.admin.name),
                      username: htmlSanitizer.escapeHtml(properties.admin.username),
                      created_at: properties.admin.created_at,
                      removedTags: properties.admin.removedTags,
                  }
                : null,
        };

        const isTrustedUser = safeProps.filename !== '/assets/images/waiting.png';
        const tags = popupHelper.parseSummaryTags(safeProps.summary, isTrustedUser, translate);
        const takenDateString = popupHelper.formatPhotoTakenTime(safeProps.datetime, translate);
        const userFormatted = popupHelper.formatUser(safeProps.name, safeProps.username, safeProps.team, translate);
        const isLitterArt = popupHelper.checkIfLitterArt(safeProps.summary);
        const hasTags = tags && tags !== translate('Awaiting verification');
        const pickedUpStatus = popupHelper.formatPickedUpStatus(safeProps.picked_up, hasTags, translate);
        const hasSocialLinks = Object.values(safeProps.social).some((link) => link);
        const adminInfo = safeProps.admin ? popupHelper.getAdminInfo(safeProps.admin, translate) : null;
        const removedTags = safeProps.admin?.removedTags
            ? popupHelper.getRemovedTags(safeProps.admin.removedTags, translate)
            : '';

        // Build social links HTML
        const socialLinksHtml = hasSocialLinks ? popupHelper.buildSocialLinks(safeProps.social) : '';

        return `
            <img
                src="${safeProps.filename}"
                class="leaflet-litter-img"
                onclick="document.querySelector('.leaflet-popup-close-button').click();"
                alt="${translate('Photo')}"
                loading="lazy"
                onerror="this.src='/assets/images/error.png'"
                ${isTrustedUser ? '' : 'style="padding: 16px;"'}
            />
            <div class="leaflet-litter-img-container">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    ${tags ? `<div class="popup-tags" style="margin-right: auto;">${tags}</div>` : '<div style="margin-right: auto;"></div>'}
                    ${pickedUpStatus && !isLitterArt ? `<div class="popup-pickup-status">${pickedUpStatus}</div>` : ''}
                </div>
                <div class="popup-date">${takenDateString}</div>
                ${userFormatted ? `<div class="popup-user">${userFormatted}</div>` : ''}
                ${socialLinksHtml}
                ${url ? `<a class="link popup-share" target="_blank" rel="noopener" href="${htmlSanitizer.sanitizeUrl(url)}"><i class="fa fa-share-alt" aria-label="${translate('Share')}"></i></a>` : ''}
                ${adminInfo ? `<p class="updated-by-admin">${adminInfo}</p>` : ''}
                ${removedTags ? `<p class="updated-by-admin">${removedTags}</p>` : ''}
            </div>`;
    },

    /**
     * Build social links HTML with proper sanitization
     */
    buildSocialLinks(social) {
        const links = [];
        const iconMap = {
            personal: 'fa-link',
            twitter: 'fa-twitter',
            facebook: 'fa-facebook',
            instagram: 'fa-instagram',
            linkedin: 'fa-linkedin',
            reddit: 'fa-reddit',
        };

        Object.entries(social).forEach(([platform, url]) => {
            if (url) {
                const ariaLabel = `Visit ${platform} profile`;
                links.push(
                    `<a target="_blank" rel="noopener" href="${url}" aria-label="${ariaLabel}">` +
                        `<i class="fa ${iconMap[platform]}" aria-hidden="true"></i>` +
                        `</a>`
                );
            }
        });

        return links.length > 0 ? `<div class="social-container">\n${links.join('\n')}\n</div>` : '';
    },

    /**
     * Parse tags from the summary data structure with XSS protection
     */
    parseSummaryTags: (summary, isTrustedUser, translate) => {
        if (!summary?.tags) {
            return isTrustedUser ? translate('Not tagged yet') : translate('Awaiting verification');
        }

        const tagLines = [];
        const keys = summary.keys || {};

        // v5.1 flat array format: tags is an array of tag objects with numeric IDs
        if (Array.isArray(summary.tags)) {
            summary.tags.forEach((tag) => {
                const categoryName = keys.categories?.[tag.category_id] || 'unknown';
                const objectName = keys.objects?.[tag.object_id] || 'item';
                const safeCategory = htmlSanitizer.escapeHtml(categoryName);
                const safeObject = htmlSanitizer.escapeHtml(objectName);
                const quantity = parseInt(tag.quantity) || 0;

                tagLines.push(`${safeCategory} - ${safeObject}: ${quantity}`);

                // Materials (array of IDs)
                if (Array.isArray(tag.materials)) {
                    tag.materials.forEach((matId) => {
                        const matName = keys.materials?.[matId] || `material #${matId}`;
                        tagLines.push(`&nbsp;&nbsp;${htmlSanitizer.escapeHtml(matName)}`);
                    });
                }

                // Brands (object: id → quantity)
                if (tag.brands && typeof tag.brands === 'object' && !Array.isArray(tag.brands)) {
                    Object.entries(tag.brands).forEach(([brandId, qty]) => {
                        const brandName = keys.brands?.[brandId] || `brand #${brandId}`;
                        const safeCount = parseInt(qty) || 0;
                        tagLines.push(`&nbsp;&nbsp;${htmlSanitizer.escapeHtml(brandName)}: ${safeCount}`);
                    });
                }

                // Custom tags (array of IDs)
                if (Array.isArray(tag.custom_tags)) {
                    tag.custom_tags.forEach((ctId) => {
                        const ctName = keys.custom_tags?.[ctId] || `tag #${ctId}`;
                        tagLines.push(`&nbsp;&nbsp;${htmlSanitizer.escapeHtml(ctName)}`);
                    });
                }
            });
        } else {
            // Legacy nested dict format: { category: { object: { quantity, materials, brands } } }
            Object.entries(summary.tags).forEach(([category, objects]) => {
                if (!objects || typeof objects !== 'object') return;
                const safeCategory = htmlSanitizer.escapeHtml(category);

                Object.entries(objects).forEach(([objectKey, data]) => {
                    if (!data || typeof data !== 'object') return;
                    const safeObjectKey = htmlSanitizer.escapeHtml(objectKey);
                    const quantity = parseInt(data.quantity) || 0;

                    tagLines.push(`${safeCategory} - ${safeObjectKey}: ${quantity}`);

                    if (data.materials && typeof data.materials === 'object') {
                        Object.entries(data.materials).forEach(([material, count]) => {
                            tagLines.push(`&nbsp;&nbsp;${htmlSanitizer.escapeHtml(material)}: ${parseInt(count) || 0}`);
                        });
                    }

                    if (data.brands && typeof data.brands === 'object') {
                        Object.entries(data.brands).forEach(([brand, count]) => {
                            tagLines.push(`&nbsp;&nbsp;${htmlSanitizer.escapeHtml(brand)}: ${parseInt(count) || 0}`);
                        });
                    }

                    if (Array.isArray(data.custom_tags)) {
                        data.custom_tags.forEach((customTag) => {
                            tagLines.push(`&nbsp;&nbsp;${htmlSanitizer.escapeHtml(customTag)}`);
                        });
                    }
                });
            });
        }

        return tagLines.length > 0
            ? tagLines.join('<br>')
            : isTrustedUser
              ? translate('Not tagged yet')
              : translate('Awaiting verification');
    },

    /**
     * Check if this is litter art based on summary data
     */
    checkIfLitterArt: (summary) => {
        if (!summary?.tags) return false;

        // v5.1 flat array format
        if (Array.isArray(summary.tags)) {
            const keys = summary.keys || {};
            return summary.tags.some((tag) => {
                const catName = keys.categories?.[tag.category_id] || '';
                const objName = keys.objects?.[tag.object_id] || '';
                return catName === 'art' || objName.toLowerCase().includes('art');
            });
        }

        // Legacy nested dict format
        return Object.keys(summary.tags).some(
            (category) =>
                category === 'art' ||
                (typeof summary.tags[category] === 'object' &&
                    Object.keys(summary.tags[category]).some((item) => String(item).toLowerCase().includes('art')))
        );
    },

    /**
     * Format user with team information
     */
    formatUser: (name, username, team, translate) => {
        if (!name && !username && !team) return '';

        // Team-only attribution (school safeguarding — no student identity)
        if (!name && !username && team) {
            return `${translate('Contributed by')} ${team}`;
        }

        const parts = [translate('By')];

        if (name) parts.push(name);
        if (username) parts.push(`@${username}`);
        if (team) parts.push(`@ ${team}`);

        return parts.join(' ');
    },

    /**
     * Format picked up status - show only if not null and has tags
     */
    formatPickedUpStatus: (pickedUp, hasTags, translate) => {
        // Hide if null or if not tagged
        if (pickedUp === null || pickedUp === undefined || !hasTags) {
            return '';
        }

        return `${translate('Picked up')}: ${pickedUp ? translate('True') : translate('False')}`;
    },

    /**
     * Format photo taken time
     */
    formatPhotoTakenTime: (takenOn, translate) => {
        if (!takenOn) return '';

        try {
            const date = moment(takenOn);
            if (!date.isValid()) return '';

            return `${translate('Taken on')}: ${date.format('LLL')}`;
        } catch {
            return '';
        }
    },

    /**
     * Get admin info with XSS protection
     */
    getAdminInfo: (admin, translate) => {
        const parts = [translate('These tags were updated by')];

        if (admin.name || admin.username) {
            if (admin.name) parts.push(admin.name);
            if (admin.username) parts.push(`@${admin.username}`);
        } else {
            parts.push(translate('an admin'));
        }

        if (admin.created_at) {
            try {
                const date = moment(admin.created_at);
                if (date.isValid()) {
                    parts.push(`<br>${translate('at')} ${date.format('LLL')}`);
                }
            } catch {
                // Ignore invalid dates
            }
        }

        return parts.join(' ');
    },

    /**
     * Get removed tags info with XSS protection
     */
    getRemovedTags: (removedTags, translate) => {
        const lines = [translate('Removed Tags') + ':'];

        if (Array.isArray(removedTags.customTags)) {
            const safeTags = removedTags.customTags.map((tag) => htmlSanitizer.escapeHtml(tag)).join(' ');
            if (safeTags) lines.push(safeTags);
        }

        if (removedTags.tags && typeof removedTags.tags === 'object') {
            Object.entries(removedTags.tags).forEach(([category, items]) => {
                const safeCategory = htmlSanitizer.escapeHtml(category);

                if (typeof items === 'object') {
                    const itemStrings = Object.entries(items).map(([key, value]) => {
                        const safeKey = htmlSanitizer.escapeHtml(key);
                        const safeValue = parseInt(value) || 0;
                        return `${safeCategory} - ${safeKey}(${safeValue})`;
                    });

                    if (itemStrings.length > 0) {
                        lines.push(itemStrings.join(', '));
                    }
                }
            });
        }

        return lines.length > 1 ? lines.join('<br>') : '';
    },

    /**
     * Scroll popup to bottom for tall images
     */
    scrollPopupToBottom: (event) => {
        const popup = event.popup?.getElement()?.querySelector('.leaflet-popup-content');
        if (popup) {
            requestAnimationFrame(() => {
                popup.scrollTop = popup.scrollHeight;
            });
        }
    },

    /**
     * Render a Leaflet popup for a specific feature
     */
    renderLeafletPopup: (feature, latlng, t, mapInstance) => {
        // Provide translation fallback
        const translate = t || ((key) => key);

        const content = popupHelper.getContent(feature.properties, null, translate);

        const popup = L.popup(popupHelper.popupOptions).setLatLng(latlng).setContent(content).openOn(mapInstance);

        // Scroll popup to bottom after opening (for tall images)
        popup.on('popupopen', popupHelper.scrollPopupToBottom);

        return popup;
    },

    /**
     * Get cleanup popup content with XSS protection
     */
    getCleanupContent: (properties, userId = null, translate = (key) => key) => {
        const safeName = htmlSanitizer.escapeHtml(properties.name);
        const safeDescription = htmlSanitizer.escapeHtml(properties.description);
        const safeStartsAt = htmlSanitizer.escapeHtml(properties.startsAt);
        const safeTimeDiff = htmlSanitizer.escapeHtml(properties.timeDiff);
        const userCount = parseInt(properties.users?.length) || 0;
        const inviteLink = htmlSanitizer.escapeHtml(properties.invite_link);

        let userCleanupInfo = '';

        if (userId === null) {
            userCleanupInfo = translate('Log in to join the cleanup');
        } else {
            const userInCleanup = properties.users?.some((user) => user.user_id === userId);

            if (userInCleanup) {
                userCleanupInfo = `<p>${translate('You have joined the cleanup')}</p>`;

                if (userId === properties.user_id) {
                    userCleanupInfo += `<p>${translate('You cannot leave the cleanup you created')}</p>`;
                } else {
                    userCleanupInfo += `<a
                        onclick="window.olm_map.$store.dispatch('LEAVE_CLEANUP', {
                            link: '${inviteLink}'
                        })"
                        class="cleanup-action-link"
                    >${translate('Click here to leave')}</a>`;
                }
            } else {
                userCleanupInfo = `<a
                    onclick="window.olm_map.$store.dispatch('JOIN_CLEANUP', {
                        link: '${inviteLink}'
                    })"
                    class="cleanup-action-link"
                >${translate('Click here to join')}</a>`;
            }
        }

        const personText = userCount === 1 ? translate('person') : translate('people');

        return `
            <div class="leaflet-cleanup-container">
                <p class="cleanup-name">${safeName}</p>
                <p class="cleanup-attendance">${translate('Attending')}: ${userCount} ${personText}</p>
                <p class="cleanup-description">${safeDescription}</p>
                <p class="cleanup-time">${translate('When')}: ${safeStartsAt}</p>
                <p class="cleanup-time-diff">${safeTimeDiff}</p>
                ${userCleanupInfo}
            </div>
        `;
    },

    /**
     * Get merchant popup content with XSS protection
     */
    getMerchantContent: (properties, translate = (key) => key) => {
        const safeName = htmlSanitizer.escapeHtml(properties.name);
        const safeAbout = htmlSanitizer.escapeHtml(properties.about);
        const safeWebsite = htmlSanitizer.sanitizeUrl(properties.website);

        let photos = '';
        if (Array.isArray(properties.photos)) {
            photos = properties.photos
                .map((photo) => {
                    const safePath = htmlSanitizer.sanitizeUrl(photo.filepath);
                    if (safePath) {
                        return `<div class="swiper-slide">
                        <img style="height: 404px;" src="${safePath}" alt="${translate('Merchant photo')}" loading="lazy">
                    </div>`;
                    }
                    return '';
                })
                .join('');
        }

        const websiteLink = safeWebsite
            ? `<a href="${safeWebsite}" target="_blank" rel="noopener">${safeWebsite}</a>`
            : '';

        return `
            <div class="leaflet-cleanup-container">
                ${
                    photos
                        ? `
                    <div class="swiper-container">
                        <div class="swiper-wrapper">${photos}</div>
                        <div class="swiper-button-prev" id="prevButton"></div>
                        <div class="swiper-button-next" id="nextButton"></div>
                    </div>
                `
                        : ''
                }
                <p>${translate('Name')}: ${safeName}</p>
                ${safeAbout ? `<p>${translate('About this merchant')}: ${safeAbout}</p>` : ''}
                ${websiteLink ? `<p>${translate('Website')}: ${websiteLink}</p>` : ''}
            </div>
        `;
    },
};

export default popupHelper;
