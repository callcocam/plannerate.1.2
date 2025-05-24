import { defineStore } from 'pinia';

export const useAnalysisResultStore = defineStore('analysisResult', {
  state: () => ({
    result: null as any, // Pode ser array ou objeto, conforme a análise
    loading: false, // Add loading state
  }),
  getters: {
    getById: (state) => (id: string | number) => {
      if (Array.isArray(state.result)) {
        return state.result.find((item: any) => item.id === id);
      }
      return null;
    }
  },
  actions: {
    setResult(data: any) {
      this.result = data;
    },
    clearResult() {
      this.result = null;
    },
    requestRecalculation() {
      // This action will be used to signal that a recalculation is needed
      this.loading = true; // Set loading to true when recalculation is requested
    }
  },
}); 