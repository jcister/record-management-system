import { ref } from 'vue';

const isCreateRecordModalOpen = ref(false);

export const useRecordModalGuard = () => {
    const markOpen = (): void => {
        isCreateRecordModalOpen.value = true;
    };

    const markClosed = (): void => {
        isCreateRecordModalOpen.value = false;
    };

    return {
        isCreateRecordModalOpen,
        markOpen,
        markClosed,
    };
};
