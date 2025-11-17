<template>
  <div class="flex-1 overflow-auto border rounded-lg">
    <table class="text-sm border-collapse w-full">
      <thead class="sticky top-0 bg-white z-10">
        <tr class="bg-gray-100">
          <th v-for="(label, key) in tableHeaders" :key="key"
            class="px-3 py-2 border cursor-pointer hover:bg-gray-200 text-left min-w-[120px]"
            @click="$emit('toggle-sort', key as keyof BCGResult)">
            <div class="flex items-center justify-between">
              {{ label }}
              <span class="ml-1">
                <ArrowUpDown v-if="sortConfig.key !== key" class="h-4 w-4" />
                <ArrowUp v-else-if="sortConfig.direction === 'asc'" class="h-4 w-4" />
                <ArrowDown v-else class="h-4 w-4" />
              </span>
            </div>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in results" :key="getItemKey(item)" @click="handleRowClick(item)" :class="{
          'bg-blue-100 dark:bg-blue-900/50': selectedItemId === getItemKey(item),
          'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50': true
        }">
          <td class="px-3 py-2 border">{{ item.ean }}</td>
          <td class="px-3 py-2 border">{{ item.description || item.displayGroup }}</td>
          <td class="px-3 py-2 border">{{ item.classifyGroup }}</td>
          <td class="px-3 py-2 border" v-if="displayBy !== 'produto'">
            {{ item.groupSize || 1 }}
          </td>
          <td class="px-3 py-2 border text-right">{{ formatAxisValue(item.yValue) }}</td>
          <td class="px-3 py-2 border text-right">{{ formatAxisValue(item.xValue) }}</td>
          <td class="px-3 py-2 border">
            <span class="px-2 py-1 rounded-full text-xs font-medium inline-flex items-center gap-1"
              :class="getClassificationClass(item.classification)">
              <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: item.color }"></div>
              {{ getClassificationLabel(item.classification) }}
            </span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div v-if="results.length === 0" class="text-gray-500 mt-4 text-center py-8">
    <div class="text-lg mb-2">Nenhum resultado encontrado</div>
    <div class="text-sm">Tente ajustar os filtros ou a configuração da análise</div>
  </div>

  <!-- Legenda -->
  <div class="mt-4 flex flex-wrap gap-4 flex-shrink-0 p-3 bg-gray-50 rounded-lg">
    <div v-for="(label, classification) in classificationLabels" :key="classification"
      class="flex items-center gap-2">
      <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: getClassificationColor(classification) }">
      </div>
      <span class="text-sm">{{ label }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-vue-next';
import type { BCGClassification, BCGResult } from '@plannerate/composables/useBCGMatrixImproved';

const props = defineProps<{
  results: BCGResult[];
  sortConfig: { key: keyof BCGResult; direction: 'asc' | 'desc' };
  selectedItemId: string | null;
  displayBy: string;
  displayLabel: string;
  classifyLabel: string;
  xAxisLabel: string;
  yAxisLabel: string;
}>();

const emit = defineEmits<{
  (e: 'toggle-sort', key: keyof BCGResult): void;
  (e: 'update:selectedItemId', value: string | null): void;
}>();

const classificationLabels: Record<BCGClassification, string> = {
  'Alto valor - manutenção': 'Alto valor - manutenção',
  'Incentivo - volume': 'Incentivo - volume',
  'Incentivo - lucro': 'Incentivo - lucro',
  'Incentivo - valor': 'Incentivo - valor',
  'Baixo valor - avaliar': 'Baixo valor - avaliar'
};

const tableHeaders = computed(() => {
  const headers: Record<string, string> = {
    ean: 'EAN',
    description: props.displayLabel,
    classifyGroup: props.classifyLabel,
  };

  if (props.displayBy !== 'produto') {
    headers.groupSize = 'Qtd. Itens';
  }

  headers.yValue = `EIXO Y (${props.yAxisLabel})`;
  headers.xValue = `EIXO X (${props.xAxisLabel})`;
  headers.classification = 'Classificação BCG';

  return headers;
});

const getItemKey = (item: BCGResult) => {
  return `${item.product_id}_${item.ean}`;
};

const handleRowClick = (item: BCGResult) => {
  const itemKey = getItemKey(item);
  emit('update:selectedItemId', props.selectedItemId === itemKey ? null : itemKey);
};

const formatAxisValue = (value: number) => {
  return new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2
  }).format(value);
};

const getClassificationClass = (classification: BCGClassification) => {
  const classes: Record<BCGClassification, string> = {
    'Alto valor - manutenção': 'bg-green-100 text-green-800',
    'Incentivo - volume': 'bg-blue-100 text-blue-800',
    'Incentivo - lucro': 'bg-purple-100 text-purple-800',
    'Incentivo - valor': 'bg-orange-100 text-orange-800',
    'Baixo valor - avaliar': 'bg-red-100 text-red-800'
  };
  return classes[classification] || '';
};

const getClassificationLabel = (classification: BCGClassification) => {
  return classificationLabels[classification] || classification;
};

const getClassificationColor = (classification: string) => {
  const colors: Record<string, string> = {
    'Alto valor - manutenção': '#00B050',
    'Incentivo - volume': '#00B0F0',
    'Incentivo - lucro': '#BF90FF',
    'Incentivo - valor': '#FF8C00',
    'Baixo valor - avaliar': '#FF6347'
  };
  return colors[classification] || '#666';
};
</script>

<style scoped>
table {
  border-collapse: collapse;
}

th,
td {
  white-space: nowrap;
}
</style>
