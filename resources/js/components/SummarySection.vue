<template>
  <div v-if="summary" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4 flex-shrink-0">
    <div class="bg-white p-4 rounded-lg border-l-4 border-gray-400 shadow-sm flex items-center">
      <Package class="h-8 w-8 text-gray-400 mr-4 flex-shrink-0" />
      <div>
        <h3 class="text-sm font-medium text-gray-500">Total de Itens</h3>
        <p class="text-2xl font-bold">{{ formatNumber.format(summary.totalItems) }}</p>
      </div>
    </div>
    <div class="bg-white p-4 rounded-lg border-l-4 border-green-500 shadow-sm flex items-center">
      <Star class="h-8 w-8 text-green-500 mr-4 flex-shrink-0" />
      <div>
        <h3 class="text-sm font-medium text-gray-500">Classificação A</h3>
        <p class="text-2xl font-bold">
          {{ formatNumber.format(summary.classificationCounts.A) }}
          <span class="text-sm font-normal text-gray-500">({{ summary.percentageA }}%)</span>
        </p>
      </div>
    </div>
    <div class="bg-white p-4 rounded-lg border-l-4 border-yellow-500 shadow-sm flex items-center">
      <Circle class="h-8 w-8 text-yellow-500 mr-4 flex-shrink-0" />
      <div>
        <h3 class="text-sm font-medium text-gray-500">Classificação B</h3>
        <p class="text-2xl font-bold">
          {{ formatNumber.format(summary.classificationCounts.B) }}
          <span class="text-sm font-normal text-gray-500">({{ summary.percentageB }}%)</span>
        </p>
      </div>
    </div>
    <div class="bg-white p-4 rounded-lg border-l-4 border-red-500 shadow-sm flex items-center">
      <Triangle class="h-8 w-8 text-red-500 mr-4 flex-shrink-0" />
      <div>
        <h3 class="text-sm font-medium text-gray-500">Classificação C</h3>
        <p class="text-2xl font-bold">
          {{ formatNumber.format(summary.classificationCounts.C) }}
          <span class="text-sm font-normal text-gray-500">({{ summary.percentageC }}%)</span>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Package, Star, Circle, Triangle } from 'lucide-vue-next';

defineProps<{
  summary: {
    totalItems: number;
    classificationCounts: { A: number; B: number; C: number; };
    percentageA: string;
    percentageB: string;
    percentageC: string;
  } | null;
}>();

const formatNumber = new Intl.NumberFormat('pt-BR', {
  minimumFractionDigits: 0,
  maximumFractionDigits: 0
});
</script>
