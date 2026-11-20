export const escapeHtml = (text: string): string => {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};
export const highlight = (text: string | undefined, term: string): string => {
    if (!text) return '';
    if (!term) return escapeHtml(text);

    const safeTerm = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(`(${safeTerm})`, 'gi');
    return escapeHtml(text).replace(regex, '<mark>$1</mark>');
};
