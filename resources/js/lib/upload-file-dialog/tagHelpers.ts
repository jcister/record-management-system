import type { Tag } from './types';

export const mapTagsByIds = (tagIds: number[], tags: Tag[]): Tag[] => {
    return tagIds
        .map((id) => tags.find((tag) => tag.id === id))
        .filter((tag): tag is Tag => tag !== undefined);
};
