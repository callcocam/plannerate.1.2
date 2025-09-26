import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { StockAnalysis, Replenishment } from '@plannerate/composables/useTargetStock';

export const useTargetStockResultStore = defineStore('targetStockResult', () => {
    const result = ref<StockAnalysis[]>([]);
    const replenishmentParams = ref<Replenishment[]>([]);
    const loading = ref(false);

    function setResult(newResult: StockAnalysis[], params: Replenishment[]) {
        result.value = newResult;
        replenishmentParams.value = params;
    }

    function requestRecalculation() {
        loading.value = true;        
    }

    function setLoading(value: boolean) {
        loading.value = value;
    }

    return {
        result,
        replenishmentParams,
        loading,
        setResult,
        requestRecalculation,
        setLoading
    };
}); 