import { green_dot, grey_dot } from './icons';
import {MEDIUM_CLUSTER_SIZE, LARGE_CLUSTER_SIZE, ZOOM_STEP, MAX_ZOOM, CLUSTER_ZOOM_THRESHOLD} from './constants.js';
import L from "leaflet";
import {mapHelper} from "./mapHelper.js";

/**
 * Create the cluster or point icon to display for each feature
 */
export function createClusterIcon (feature, latlng)
{
    if (!feature.properties.cluster)
    {
        return (feature.properties.verified === 2)
            ? L.marker(latlng, { icon: green_dot })
            : L.marker(latlng, { icon: grey_dot });
    }

    const count = feature.properties.point_count;
    const size = (count < MEDIUM_CLUSTER_SIZE)
        ? 'small'
        : count < LARGE_CLUSTER_SIZE
            ? 'medium'
            : 'large';

    const icon = L.divIcon({
        html: '<div class="mi"><span class="mx-auto my-auto">' + feature.properties.point_count_abbreviated + '</span></div>',
        className: 'marker-cluster-' + size,
        iconSize: L.point(40, 40)
    });

    return L.marker(latlng, { icon });
}

export function onEachFeature (feature, layer, map)
{
    if (feature.properties.cluster)
    {
        layer.on('click', function (e)
        {
            const zoomTo = ((map.getZoom() + ZOOM_STEP) > MAX_ZOOM)
                ? MAX_ZOOM
                : (map.getZoom() + ZOOM_STEP);

            map.flyTo(e.latlng, zoomTo, {
                animate: true,
                duration: 2
            });
        });
    }
}

/**
 * Helper method to create a Popup
 *
 * @param feature
 * @param latLng
 * @param t - translation function
 */
export function renderLeafletPopup (feature, latLng, t)
{
    const url = new URL(window.location.href);
    url.searchParams.set('lat', feature.geometry.coordinates[0]);
    url.searchParams.set('lon', feature.geometry.coordinates[1]);
    url.searchParams.set('zoom', CLUSTER_ZOOM_THRESHOLD.toString());
    url.searchParams.set('photo', feature.properties.photo_id);

    L.popup(mapHelper.popupOptions)
        .setLatLng(latLng)
        .setContent(mapHelper.getMapImagePopupContent(feature.properties, url.toString()), t)
        .openOn(map);
}
