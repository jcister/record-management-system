import { ref, onMounted } from 'vue';
import { apiClient } from '@/lib/axios';

export interface Quote {
    content: string;
    author: string;
}

export const useQuote = () => {
    const quote = ref<Quote | null>(null);
    const loading = ref(true);
    const error = ref<string | null>(null);

    const fetchQuote = async () => {
        try {
            loading.value = true;
            error.value = null;

            const response = await apiClient.get('/api/quote');
            const data = response.data;

            if (!data.content || !data.author) {
                throw new Error('Invalid quote data received');
            }

            quote.value = {
                content: data.content,
                author: data.author,
            };
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Failed to load quote';
            console.error('Error fetching quote:', err);
            quote.value = null;
        } finally {
            loading.value = false;
        }
    };

    onMounted(() => {
        fetchQuote();
    });

    return {
        quote,
        loading,
        error,
        refetch: fetchQuote,
    };
};

