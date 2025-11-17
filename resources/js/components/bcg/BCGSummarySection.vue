<template>
  <div v-if="summary" class="mb-4 py-3 px-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg flex-shrink-0 border border-blue-200">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <!-- Totais -->
      <div>
        <h3 class="text-sm font-semibold text-blue-700 mb-2">Resumo Geral</h3>
        <div class="space-y-1">
          <p class="text-xs">
            <span class="font-medium">Total de {{ displayLabel }}:</span>
            <span class="ml-1 font-bold text-blue-800">{{ formatNumber.format(summary.totalItems) }}</span>
          </p>
          <p class="text-xs">
            <span class="font-medium">Agrupado por:</span>
            <span class="ml-1 text-blue-700">{{ classifyLabel }}</span>
          </p>
          <p class="text-xs" v-if="summary.totalClassifyGroups">
            <span class="font-medium">{{ classifyLabel }} únicos:</span>
            <span class="ml-1 font-bold text-blue-800">{{ summary.totalClassifyGroups }}</span>
          </p>
        </div>
      </div>

      <!-- Classificações BCG -->
      <div class="col-span-2">
        <h3 class="text-sm font-semibold text-blue-700 mb-2">Distribuição por Classificação BCG</h3>
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-2">
          <div v-for="(count, classification) in summary.classificationCounts" :key="classification"
            class="text-center p-2 rounded-md bg-white border border-gray-200">
            <div class="text-xs font-medium text-gray-600 mb-1">
              {{ getClassificationShortLabel(String(classification)) }}
            </div>
            <div class="text-lg font-bold" :class="getClassificationColorClass(String(classification))">
              {{ formatNumber.format(count) }}
            </div>
            <div class="text-xs text-gray-500">
              {{ getPercentage(count, summary.totalItems) }}%
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Summary {
  totalItems: number;
  classificationCounts: Record<string, number>;
  totalClassifyGroups?: number;
}

defineProps<{
  summary: Summary | null;
  displayLabel: string;
  classifyLabel: string;
}>();

const formatNumber = new Intl.NumberFormat('pt-BR', {
  minimumFractionDigits: 0,
  maximumFractionDigits: 0
});

const getClassificationShortLabel = (classification: string) => {
  const shortLabels: Record<string, string> = {
    'Alto valor - manutenção': 'Alto Valor',
    'Incentivo - volume': 'Inc. Volume',
    'Incentivo - lucro': 'Inc. Lucro',
    'Incentivo - valor': 'Inc. Valor',
    'Baixo valor - avaliar': 'Baixo Valor'
  };
  return shortLabels[classification] || classification;
};

const getClassificationColorClass = (classification: string) => {
  const classes: Record<string, string> = {
    'Alto valor - manutenção': 'text-green-600',
    'Incentivo - volume': 'text-blue-600',
    'Incentivo - lucro': 'text-purple-600',
    'Incentivo - valor': 'text-orange-600',
    'Baixo valor - avaliar': 'text-red-600'
  };
  return classes[classification] || 'text-gray-600';
};

const getPercentage = (value: number, total: number) => {
  return total > 0 ? Math.round((value / total) * 100) : 0;
};
</script>

<style scoped>
.bg-gradient-to-r {
  background: linear-gradient(to right, var(--tw-gradient-stops));
}
</style>
