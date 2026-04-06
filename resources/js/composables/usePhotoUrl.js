/**
 * Resolve a photo filename to a displayable URL.
 *
 * In production, filename is a full S3 URL (https://olm-s3.s3...).
 * In local dev with MinIO, filename is a full MinIO URL (http://localhost:9000/...).
 * If filename is a relative path, prepend the current origin.
 */
export function resolvePhotoUrl(filename) {
    if (!filename) return '/assets/images/waiting.png';
    if (filename.startsWith('http') || filename.startsWith('//')) return filename;
    return `${window.location.origin}${filename}`;
}
