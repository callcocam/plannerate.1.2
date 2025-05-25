<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Matriz BCG - Exemplo</h1>

    <!-- Formulário de entrada de dados -->
    <div class="mb-8 p-4 bg-white rounded-lg shadow">
      <h2 class="text-lg font-semibold mb-4">Configurações</h2>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Nome do Eixo Y</label>
          <input v-model="yAxisName" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Nome do Eixo X</label>
          <input v-model="xAxisName" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
      </div>
    </div>

    <!-- Tabela de resultados -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">EAN</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoria</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ yAxisName }}</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ xAxisName }}</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Classificação BCG</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="result in bcgResults" :key="result.ean" :style="{ backgroundColor: result.color + '20' }">
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ result.ean }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ result.description }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ result.category }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ result.yValue.toFixed(2) }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ result.xValue.toFixed(2) }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm" :style="{ color: result.color }">
              {{ result.classification }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Legenda -->
    <div class="mt-6 p-4 bg-white rounded-lg shadow">
      <h3 class="text-lg font-semibold mb-3">Legenda</h3>
      <div class="grid grid-cols-2 gap-4">
        <div v-for="(color, classification) in classificationColors" :key="classification" 
             class="flex items-center space-x-2">
          <div class="w-4 h-4 rounded" :style="{ backgroundColor: color }"></div>
          <span class="text-sm">{{ classification }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useBCGMatrix, type BCGData } from '@/composables/useBCGMatrix';

// Configurações
const yAxisName = ref('Crescimento');
const xAxisName = ref('Participação');

// Dados de exemplo
const sampleData: BCGData[] = [
  {
    ean: '7891234567890',
    description: 'Produto A',
    category: 'Categoria 1',
    yValue: 15,
    xValue: 25
  },
  {
    ean: '7891234567891',
    description: 'Produto B',
    category: 'Categoria 1',
    yValue: 8,
    xValue: 30
  },
  {
    ean: '7891234567892',
    description: 'Produto C',
    category: 'Categoria 2',
    yValue: 20,
    xValue: 15
  },
  {
    ean: '7891234567893',
    description: 'Produto D',
    category: 'Categoria 2',
    yValue: 5,
    xValue: 10
  }
];

// Usar o composable
const { results: bcgResults, processData, classificationColors } = useBCGMatrix();

// Processar dados ao montar o componente
onMounted(() => {
  processData(sampleData);
});
</script>

<style scoped>
/* Estilos adicionais se necessário */
</style> 