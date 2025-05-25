import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { BCGResult } from '@/composables/useBCGMatrix';

export const useBCGResultStore = defineStore('bcgResult', () => {
  const result = ref<BCGResult[] | null>(null);
  const loading = ref(false);

  function setResult(newResult: BCGResult[] | null) {
    result.value = newResult;
  }

  function requestRecalculation() {
    loading.value = true;
  }

  return {
    result,
    loading,
    setResult,
    requestRecalculation
  };
}); 