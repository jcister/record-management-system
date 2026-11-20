export const MAX_FILE_BYTES = 100 * 1024 * 1024;

// Keep chunk size conservative to avoid local proxy/multipart overhead issues.
export const CHUNK_SIZE_BYTES = 512 * 1024;

// Chunking threshold should be below PHP's upload_max_filesize (typically 2M)
// Using 1.5MB to ensure files larger than common PHP limits use chunking
export const CHUNK_THRESHOLD_BYTES = 1.5 * 1024 * 1024;
