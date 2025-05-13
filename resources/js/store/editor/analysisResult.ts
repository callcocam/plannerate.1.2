import { defineStore } from 'pinia';

export const useAnalysisResultStore = defineStore('analysisResult', {
  state: () => ({
    result: null as any // Pode ser array ou objeto, conforme a análise
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
    }
  },
}); 