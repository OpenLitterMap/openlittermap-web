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

    /**
     * Escape a string for safe embedding inside a JS single-quoted string literal.
     */
    escapeJsString(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\n/g, '\\n');
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
     * Format a DB key (snake_case or camelCase) into readable title case
     */
    formatKey(key) {
        if (!key) return '';

        return key
            .replace(/_/g, ' ')
            .replace(/([a-z])([A-Z])/g, '$1 $2')
            .replace(/\b\w/g, (c) => c.toUpperCase());
    },

    /**
     * Convert a 2-letter country code to its emoji flag
     */
    countryCodeToFlag(code) {
        if (!code || code.length !== 2) return '';
        const upper = code.toUpperCase();
        const codePoints = [...upper].map((c) => 0x1f1e6 + c.charCodeAt(0) - 65);
        return String.fromCodePoint(...codePoints);
    },

    /**
     * Build a shareable URL for a photo
     */
    buildShareUrl(photoId) {
        if (!photoId) return '';

        try {
            const url = new URL(window.location.href);
            url.searchParams.set('photo', photoId);
            url.searchParams.set('load', 'true');
            return url.toString();
        } catch {
            return '';
        }
    },

    /**
     * Returns the HTML that displays the Photo popups with XSS protection
     */
    getContent: (properties, url = null, t) => {
        // Provide fallback for translation function
        const translate = t || ((key) => key);

        // Sanitize all user-provided content
        const safeProps = {
            id: properties.id,
            verified: properties.verified,
            filename: htmlSanitizer.sanitizeUrl(properties.filename) || '/assets/images/waiting.png',
            name: htmlSanitizer.escapeHtml(properties.name),
            username: htmlSanitizer.escapeHtml(properties.username),
            team: htmlSanitizer.escapeHtml(properties.team),
            datetime: htmlSanitizer.escapeHtml(properties.datetime),
            flag: htmlSanitizer.escapeHtml(properties.flag),
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

        // A photo is "trusted" if it has been admin-approved (verified >= 2)
        // The signed URL will be fetched async, so we derive trust from verification status
        const verifiedValue = typeof safeProps.verified === 'object'
            ? safeProps.verified?.value ?? 0
            : parseInt(safeProps.verified) || 0;
        const isTrustedUser = verifiedValue >= 2;
        const tagsHtml = popupHelper.parseSummaryTags(safeProps.summary, isTrustedUser, translate);
        const dateFormatted = popupHelper.formatPhotoTakenTime(safeProps.datetime);
        const userHtml = popupHelper.formatUser(safeProps.name, safeProps.username, safeProps.team, safeProps.flag, translate);
        const isLitterArt = popupHelper.checkIfLitterArt(safeProps.summary);
        const hasTags = tagsHtml && !tagsHtml.includes('popup-empty-state');
        const pickedUpHtml =
            !isLitterArt ? popupHelper.formatPickedUpStatus(safeProps.picked_up, hasTags, translate) : '';
        const hasSocialLinks = Object.values(safeProps.social).some((link) => link);
        const adminInfo = safeProps.admin ? popupHelper.getAdminInfo(safeProps.admin, translate) : null;
        const removedTags = safeProps.admin?.removedTags
            ? popupHelper.getRemovedTags(safeProps.admin.removedTags, translate)
            : '';

        // Build share URL from photo ID
        const shareUrl = url || popupHelper.buildShareUrl(safeProps.id);
        const safeShareUrl = htmlSanitizer.escapeHtml(shareUrl);

        const hasMetaContent = pickedUpHtml || dateFormatted;
        const hasFooterContent = hasSocialLinks || shareUrl;
        const isEmptyState = tagsHtml && tagsHtml.includes('popup-empty-state');

        // Image click handler is set by fetchAndSetSignedUrl after the signed URL loads
        const imageClickAttr = '';

        // When there are no tags, combine the empty-state message and date into a single meta line
        const metaHtml = isEmptyState
            ? `<div class="popup-meta">${tagsHtml.replace('popup-empty-state', 'popup-empty-state popup-empty-inline')}${dateFormatted ? `<span class="popup-date">${dateFormatted}</span>` : ''}</div>`
            : `${tagsHtml}${hasMetaContent ? `<div class="popup-meta">${pickedUpHtml}${dateFormatted ? `<span class="popup-date">${dateFormatted}</span>` : ''}</div>` : ''}`;

        return `
            <div class="popup-image-wrap">
                <img
                    src="${safeProps.filename}"
                    class="leaflet-litter-img leaflet-litter-img--waiting"
                    ${imageClickAttr}
                    alt="${translate('Photo')}"
                    loading="lazy"
                    onerror="this.src='/assets/images/error.png'"
                />
            </div>
            <div class="popup-body">
                ${metaHtml}
                ${userHtml ? `<div class="popup-attribution">${userHtml}</div>` : ''}
                ${adminInfo ? `<div class="popup-admin">${adminInfo}</div>` : ''}
                ${removedTags ? `<div class="popup-admin">${removedTags}</div>` : ''}
                ${hasFooterContent ? popupHelper.buildFooter(safeProps.social, safeShareUrl, translate) : ''}
            </div>`;
    },

    /**
     * Build footer bar with social links + copy link button
     */
    buildFooter(social, shareUrl, translate) {
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

        const copyButton = shareUrl
            ? `<button class="popup-copy-btn" onclick="
                    navigator.clipboard.writeText(decodeURIComponent('${encodeURIComponent(shareUrl)}')).then(function() {
                        var btn = event.currentTarget;
                        btn.classList.add('popup-copy-btn--copied');
                        btn.querySelector('.popup-copy-label').textContent = '${htmlSanitizer.escapeJsString(translate('Copied!'))}';
                        setTimeout(function() {
                            btn.classList.remove('popup-copy-btn--copied');
                            btn.querySelector('.popup-copy-label').textContent = '${htmlSanitizer.escapeJsString(translate('Copy link'))}';
                        }, 2000);
                    });
                " title="${translate('Copy link')}">
                    <i class="fa fa-link" aria-hidden="true"></i>
                    <span class="popup-copy-label">${translate('Copy link')}</span>
                </button>`
            : '';

        return `<div class="popup-footer">${links.length > 0 ? `<div class="popup-social">${links.join('')}</div>` : '<div></div>'}${copyButton}</div>`;
    },

    /**
     * Build social links HTML with proper sanitization (kept for backward compat)
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

        return links.length > 0 ? `<div class="popup-social">\n${links.join('\n')}\n</div>` : '';
    },

    /**
     * Parse tags from the summary data structure, grouped by category
     */
    parseSummaryTags: (summary, isTrustedUser, translate) => {
        if (!summary?.tags) {
            const msg = isTrustedUser ? translate('Not tagged yet') : translate('Awaiting verification');
            return `<div class="popup-empty-state">${msg}</div>`;
        }

        const keys = summary.keys || {};

        // Build grouped structure: { categoryName: [tagItems] }
        const grouped = new Map();
        let totalItems = 0;

        if (Array.isArray(summary.tags)) {
            summary.tags.forEach((tag) => {
                const categoryName = popupHelper.formatKey(keys.categories?.[tag.category_id] || 'other');
                const objectName = popupHelper.formatKey(keys.objects?.[tag.object_id] || 'item');
                const quantity = parseInt(tag.quantity) || 0;
                totalItems += quantity;

                const extras = [];

                if (Array.isArray(tag.materials)) {
                    tag.materials.forEach((matId) => {
                        const matName = popupHelper.formatKey(keys.materials?.[matId] || `material #${matId}`);
                        extras.push(
                            `<span class="popup-chip popup-chip-mat">${htmlSanitizer.escapeHtml(matName)}</span>`
                        );
                    });
                }

                if (tag.brands && typeof tag.brands === 'object' && !Array.isArray(tag.brands)) {
                    Object.entries(tag.brands).forEach(([brandId, qty]) => {
                        const brandName = keys.brands?.[brandId] || `brand #${brandId}`;
                        const count = parseInt(qty) || 0;
                        const label = count > 1 ? `${brandName} \u00d7${count}` : brandName;
                        extras.push(
                            `<span class="popup-chip popup-chip-brand">${htmlSanitizer.escapeHtml(label)}</span>`
                        );
                    });
                }

                if (Array.isArray(tag.custom_tags)) {
                    tag.custom_tags.forEach((ctId) => {
                        const ctName = keys.custom_tags?.[ctId] || `tag #${ctId}`;
                        extras.push(
                            `<span class="popup-chip popup-chip-custom">${htmlSanitizer.escapeHtml(ctName)}</span>`
                        );
                    });
                }

                if (!grouped.has(categoryName)) grouped.set(categoryName, []);
                grouped.get(categoryName).push({
                    name: htmlSanitizer.escapeHtml(objectName),
                    quantity,
                    extras,
                });
            });
        } else {
            // Legacy nested dict format
            Object.entries(summary.tags).forEach(([category, objects]) => {
                if (!objects || typeof objects !== 'object') return;
                const categoryName = htmlSanitizer.escapeHtml(popupHelper.formatKey(category));

                Object.entries(objects).forEach(([objectKey, data]) => {
                    if (!data || typeof data !== 'object') return;
                    const objectName = htmlSanitizer.escapeHtml(popupHelper.formatKey(objectKey));
                    const quantity = parseInt(data.quantity) || 0;
                    totalItems += quantity;

                    const extras = [];

                    if (data.materials && typeof data.materials === 'object') {
                        Object.entries(data.materials).forEach(([material, count]) => {
                            const label = popupHelper.formatKey(material);
                            const c = parseInt(count) || 0;
                            extras.push(
                                `<span class="popup-chip popup-chip-mat">${htmlSanitizer.escapeHtml(label)}${c > 1 ? ` \u00d7${c}` : ''}</span>`
                            );
                        });
                    }

                    if (data.brands && typeof data.brands === 'object') {
                        Object.entries(data.brands).forEach(([brand, count]) => {
                            const c = parseInt(count) || 0;
                            const label = c > 1 ? `${brand} \u00d7${c}` : brand;
                            extras.push(
                                `<span class="popup-chip popup-chip-brand">${htmlSanitizer.escapeHtml(label)}</span>`
                            );
                        });
                    }

                    if (Array.isArray(data.custom_tags)) {
                        data.custom_tags.forEach((customTag) => {
                            extras.push(
                                `<span class="popup-chip popup-chip-custom">${htmlSanitizer.escapeHtml(customTag)}</span>`
                            );
                        });
                    }

                    if (!grouped.has(categoryName)) grouped.set(categoryName, []);
                    grouped.get(categoryName).push({ name: objectName, quantity, extras });
                });
            });
        }

        if (grouped.size === 0) {
            const msg = isTrustedUser ? translate('Not tagged yet') : translate('Awaiting verification');
            return `<div class="popup-empty-state">${msg}</div>`;
        }

        // Summary pill
        const summaryPill =
            totalItems > 0
                ? `<span class="popup-tag-total">${totalItems} ${totalItems === 1 ? translate('item') : translate('items')}</span>`
                : '';

        // Render grouped tags
        const groupsHtml = [];
        grouped.forEach((items, categoryName) => {
            const itemsHtml = items
                .map(
                    (item) => `
                <div class="popup-tag-row">
                    <span class="popup-tag-name">${item.name}</span>
                    <span class="popup-tag-qty">\u00d7${item.quantity}</span>
                </div>
                ${item.extras.length > 0 ? `<div class="popup-tag-extras">${item.extras.join('')}</div>` : ''}`
                )
                .join('');

            groupsHtml.push(`
                <div class="popup-tag-group">
                    <div class="popup-tag-category">${categoryName}</div>
                    ${itemsHtml}
                </div>`);
        });

        return `<div class="popup-tags-section">
            <div class="popup-tags-header">${summaryPill}</div>
            ${groupsHtml.join('')}
        </div>`;
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
     * Format user attribution as structured HTML
     */
    formatUser: (name, username, team, flag, translate) => {
        if (!name && !username && !team) return '';

        // Team-only attribution (school safeguarding — no student identity)
        if (!name && !username && team) {
            return `<span class="popup-team-only"><i class="fa fa-users popup-team-icon" aria-hidden="true"></i>${translate('Contributed by')} ${team}</span>`;
        }

        const flagEmoji = flag ? popupHelper.countryCodeToFlag(flag) : '';
        let html = '';
        if (flagEmoji) html += `<span class="popup-user-flag">${flagEmoji}</span>`;
        if (name) html += `<span class="popup-user-name">${name}</span>`;
        if (username) html += `<span class="popup-user-handle">@${username}</span>`;
        if (team) html += `<span class="popup-user-team"><i class="fa fa-users popup-team-icon" aria-hidden="true"></i>${team}</span>`;

        return html;
    },

    /**
     * Format picked up status as a styled pill
     */
    formatPickedUpStatus: (pickedUp, hasTags, translate) => {
        if (pickedUp === null || pickedUp === undefined || !hasTags) {
            return '';
        }

        if (pickedUp) {
            return `<span class="popup-pill popup-pill-picked">\u2713 ${translate('Picked up')}</span>`;
        }

        return `<span class="popup-pill popup-pill-remaining">\u25CF ${translate('Not picked up')}</span>`;
    },

    /**
     * Format photo taken time — relative for recent, full date for older
     */
    formatPhotoTakenTime: (takenOn) => {
        if (!takenOn) return '';

        try {
            const date = moment(takenOn);
            if (!date.isValid()) return '';

            const now = moment();
            const diffHours = now.diff(date, 'hours');

            // Recent: relative time (under 48h)
            if (diffHours < 48) {
                return date.fromNow();
            }

            // This year: omit the year
            if (date.year() === now.year()) {
                return date.format('MMM D \u00b7 h:mm A');
            }

            return date.format('MMM D, YYYY \u00b7 h:mm A');
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
                    parts.push(`<br>${translate('at')} ${date.format('MMM D, YYYY \u00b7 h:mm A')}`);
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
                const safeCategory = htmlSanitizer.escapeHtml(popupHelper.formatKey(category));

                if (typeof items === 'object') {
                    const itemStrings = Object.entries(items).map(([key, value]) => {
                        const safeKey = htmlSanitizer.escapeHtml(popupHelper.formatKey(key));
                        const safeValue = parseInt(value) || 0;
                        return `${safeCategory} \u2014 ${safeKey} (${safeValue})`;
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
     * Fetch a signed S3 URL for a photo and update the popup image
     */
    fetchAndSetSignedUrl: async (photoId, popupElement) => {
        if (!photoId) return;

        const img = popupElement?.querySelector('.leaflet-litter-img');
        if (!img) return;

        try {
            const response = await fetch(`/api/photos/${photoId}/signed-url`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) return;

            const data = await response.json();
            if (!data.url || data.url === '/assets/images/waiting.png') return;

            // Guard: popup may have been closed or replaced while we were fetching
            if (!img.isConnected) return;

            img.src = data.url;
            img.classList.remove('leaflet-litter-img--waiting');

            // Add click-to-open behavior for the loaded image
            img.style.cursor = 'pointer';
            img.title = 'View full image';
            img.onclick = () => window.open(data.url, '_blank');

            // Add the gradient overlay if not already present
            const wrap = img.closest('.popup-image-wrap');
            if (wrap && !wrap.querySelector('.popup-image-gradient')) {
                const gradient = document.createElement('div');
                gradient.className = 'popup-image-gradient';
                wrap.appendChild(gradient);
            }
        } catch (error) {
            // Silently fail — the waiting image remains
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

        // Fetch signed URL and update the image
        const popupElement = popup.getElement();
        popupHelper.fetchAndSetSignedUrl(feature.properties.id, popupElement);

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
