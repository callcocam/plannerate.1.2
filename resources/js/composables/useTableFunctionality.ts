import { ref, computed } from 'vue';
import type { StockAnalysis } from '@plannerate/composables/useTargetStock';
import { useTargetStockResultStore } from '@plannerate/store/editor/targetStockResult';

export function useTableFunctionality() {
    const targetStockResultStore = useTargetStockResultStore();

    // Estado de ordenação
    const sortConfig = ref({
        key: 'ean' as keyof StockAnalysis,
        direction: 'asc' as 'asc' | 'desc'
    });

    // Estado dos filtros
    const searchText = ref('');
    const activeClassificationFilters = ref<Set<string>>(new Set(['A', 'B', 'C']));

    // Ordenação
    const sortedResults = computed(() => {
        if (!targetStockResultStore.result) return [];
        return [...targetStockResultStore.result].sort((a, b) => {
            const aValue = a[sortConfig.value.key];
            const bValue = b[sortConfig.value.key];
            if (typeof aValue === 'string' && typeof bValue === 'string') {
                return sortConfig.value.direction === 'asc'
                    ? aValue.localeCompare(bValue)
                    : bValue.localeCompare(aValue);
            }
            return sortConfig.value.direction === 'asc'
                ? (aValue as number) - (bValue as number)
                : (bValue as number) - (aValue as number);
        });
    });

    // Filtro
    const filteredResults = computed(() => {
        return sortedResults.value.filter(item => {
            // Filtro por classificação
            if (activeClassificationFilters.value.size > 0 && !activeClassificationFilters.value.has(item.classification)) {
                return false;
            }
            // Filtro por texto
            if (searchText.value) {
                const searchLower = searchText.value.toLowerCase();
                return (
                    item.ean.toLowerCase().includes(searchLower) ||
                    item.name.toLowerCase().includes(searchLower)
                );
            }
            return true;
        });
    });

    function toggleSort(key: keyof StockAnalysis) {
        if (sortConfig.value.key === key) {
            sortConfig.value.direction = sortConfig.value.direction === 'asc' ? 'desc' : 'asc';
        } else {
            sortConfig.value.key = key;
            sortConfig.value.direction = 'asc';
        }
    }

    return {
        sortConfig,
        searchText,
        activeClassificationFilters,
        filteredResults,
        toggleSort
    };
}
