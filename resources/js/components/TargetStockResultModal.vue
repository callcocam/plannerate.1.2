<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/25">
    <div class="bg-white rounded-lg shadow-lg max-w-7xl w-full p-6 relative">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-bold">Resultado do Estoque Alvo</h2>
        <Button variant="outline" size="sm" @click="exportToExcel" class="flex items-center gap-2">
          <Download class="h-4 w-4" />
          Exportar Excel
        </Button>
      </div>
      <!-- Filtros -->
      <div class="mb-4 flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
          <div class="relative">
            <Search class="absolute left-2 top-2.5 h-4 w-4 text-gray-500" />
            <Input v-model="searchText" placeholder="Buscar por EAN ou nome..." class="pl-8" />
            <button v-if="searchText" @click="searchText = ''"
              class="absolute right-2 top-2.5 text-gray-500 hover:text-gray-700">
              <X class="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>
      <!-- Tabela -->
      <div class="overflow-x-auto max-h-[60vh]">
        <table class="min-w-full text-xs border">
          <thead class="sticky top-0 bg-white z-10">
            <tr class="bg-gray-100">
              <th v-for="(label, key) in {
                ean: 'EAN',
                name: 'Descrição Produto',
                averageSales: 'Demanda média',
                standardDeviation: 'Desvio Padrão',
                coverage: 'Cobertura de estoque em dias (Reposição)',
                serviceLevel: 'Nível de Serviço',
                zScore: 'Constante Z-ns',
                safetyStock: 'Estoque de Segurança',
                minimumStock: 'Estoque mínimo prateleira',
                targetStock: 'Estoque alvo prateleira',
                allowsFacing: 'Estoque permite numero de frentes'
              }" :key="key" class="px-2 py-1 border cursor-pointer hover:bg-gray-200"
                @click="toggleSort(key as keyof StockAnalysis)">
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
            <tr v-for="item in filteredResults" :key="item.ean">
              <td class="px-2 py-1 border">{{ item.ean }}</td>
              <td class="px-2 py-1 border">{{ item.name }}</td>
              <td class="px-2 py-1 border text-right">{{ formatNumber.format(item.averageSales) }}</td>
              <td class="px-2 py-1 border text-right">{{ formatNumber.format(item.standardDeviation) }}</td>
              <td class="px-2 py-1 border text-right">{{ getCoverageDays(item.classification) }}</td>
              <td class="px-2 py-1 border text-right">{{ item.serviceLevel }}</td>
              <td class="px-2 py-1 border text-right">{{ item.zScore }}</td>
              <td class="px-2 py-1 border text-right">{{ item.safetyStock }}</td>
              <td class="px-2 py-1 border text-right">{{ item.minimumStock }}</td>
              <td class="px-2 py-1 border text-right">{{ item.targetStock }}</td>
              <td class="px-2 py-1 border">{{ item.allowsFacing ? 'Sim' : 'Não' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="filteredResults.length === 0" class="text-gray-500 mt-4">Nenhum resultado encontrado.</div>
      <div class="flex justify-end mt-4">
        <Button @click="closeModal" variant="outline">Fechar</Button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { ArrowUpDown, ArrowUp, ArrowDown, Search, X, Download } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import * as XLSX from 'xlsx';
import type { StockAnalysis, Replenishment } from '@plannerate/composables/useTargetStock';

const props = defineProps<{
  open: boolean;
  result: StockAnalysis[];
  replenishmentParams: Replenishment[];
}>();
const emit = defineEmits(['close']);

// Estado de ordenação
const sortConfig = ref({
  key: 'ean' as keyof StockAnalysis,
  direction: 'asc' as 'asc' | 'desc'
});

// Estado do filtro de texto
const searchText = ref('');

// Função para exportar para Excel
function exportToExcel() {
  const exportData = filteredResults.value.map(item => ({
    'EAN': item.ean,
    'Descrição Produto': item.name,
    'Demanda média': item.averageSales,
    'Desvio Padrão': item.standardDeviation,
    'Cobertura de estoque em dias (Reposição)': getCoverageDays(item.classification),
    'Nível de Serviço': item.serviceLevel,
    'Constante Z-ns': item.zScore,
    'Estoque de Segurança': item.safetyStock,
    'Estoque mínimo prateleira': item.minimumStock,
    'Estoque alvo prateleira': item.targetStock,
    'Estoque permite numero de frentes': item.allowsFacing ? 'Sim' : 'Não'
  }));
  const ws = XLSX.utils.json_to_sheet(exportData);
  const wscols = [
    { wch: 15 }, { wch: 40 }, { wch: 15 }, { wch: 15 }, { wch: 20 }, { wch: 15 }, { wch: 15 }, { wch: 15 }, { wch: 20 }, { wch: 20 }, { wch: 20 }
  ];
  ws['!cols'] = wscols;
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Estoque Alvo');
  const fileName = `estoque_alvo_${new Date().toISOString().split('T')[0]}.xlsx`;
  XLSX.writeFile(wb, fileName);
}

// Ordenação
const sortedResults = computed(() => {
  if (!props.result) return [];
  return [...props.result].sort((a, b) => {
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

function closeModal() {
  emit('close');
}

function getCoverageDays(classification: string) {
  const param = props.replenishmentParams.find(rp => rp.classification === classification);
  return param ? param.coverageDays : '-';
}

const formatNumber = new Intl.NumberFormat('pt-BR', {
  minimumFractionDigits: 0,
  maximumFractionDigits: 2
});
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