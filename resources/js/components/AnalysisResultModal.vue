<script setup lang="ts">
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { ArrowUpDown, ArrowUp, ArrowDown, Search, X, Download } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import * as XLSX from 'xlsx';
import { useAnalysisResultStore } from '@plannerate/store/editor/analysisResult';

interface AssortmentResult {
  id: number;
  name: string;
  status: 'Ativo' | 'Inativo';
  quantity: number;
  value: number;
  margin: number;
  currentStock: number;
}

const props = defineProps<{ open: boolean }>();
const emit = defineEmits(['close']);

const analysisResultStore = useAnalysisResultStore();
const result = computed<AssortmentResult[]>(() => analysisResultStore.result as AssortmentResult[]);
console.log(analysisResultStore.result);

// Estado de ordenação
const sortConfig = ref({
  key: 'id' as keyof AssortmentResult,
  direction: 'asc' as 'asc' | 'desc'
});

// Estado dos filtros
const searchText = ref('');
const activeStatusFilters = ref<Set<'Ativo' | 'Inativo' >>(new Set(['Ativo', 'Inativo' ]));

// Função para exportar para Excel
function exportToExcel() {
  // Preparar dados para exportação
  const exportData = filteredResults.value.map(item => ({
    'ID': item.id,
    'Nome': item.name,
    'Status': item.status,
    'Quantidade': item.quantity,
    'Valor': item.value,
    'Margem (%)': item.margin,
    'Estoque': item.currentStock
  }));

  // Criar worksheet
  const ws = XLSX.utils.json_to_sheet(exportData);

  // Ajustar largura das colunas
  const wscols = [
    { wch: 10 }, // ID
    { wch: 40 }, // Nome
    { wch: 15 }, // Status
    { wch: 15 }, // Quantidade
    { wch: 15 }, // Valor
    { wch: 15 }, // Margem
    { wch: 15 }  // Estoque
  ];
  ws['!cols'] = wscols;

  // Criar workboAtivo
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Análise de Assortimento');

  // Gerar arquivo
  const fileName = `analise_assortimento_${new Date().toISOString().split('T')[0]}.xlsx`;
  XLSX.writeFile(wb, fileName);
}

// Função para alternar filtro de status
function toggleStatusFilter(status: 'Ativo' | 'Inativo'  ) {
  if (activeStatusFilters.value.has(status)) {
    activeStatusFilters.value.delete(status);
  } else {
    activeStatusFilters.value.add(status);
  }
}

// Função para limpar todos os filtros
function clearFilters() {
  searchText.value = '';
  activeStatusFilters.value = new Set(['Ativo', 'Inativo']);
}

// Função para ordenar os resultados
const sortedResults = computed(() => {
  if (!analysisResultStore.result) return [];
  console.log(analysisResultStore.result);
  return [...analysisResultStore.result].sort((a, b) => {
    const aValue = a[sortConfig.value.key];
    const bValue = b[sortConfig.value.key];
    
    if (sortConfig.value.key === 'status') {
      const statusOrder = { 'Ativo': 0, 'Inativo': 1 };
      const comparison = statusOrder[aValue as 'Ativo' | 'Inativo' ] - statusOrder[bValue as 'Ativo' | 'Inativo' ];
      return sortConfig.value.direction === 'asc' ? comparison : -comparison;
    }
    
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

// Resultados filtrados
const filteredResults = computed(() => {
  return sortedResults.value.filter(item => {
    // Filtro por status: se nenhum status estiver selecionado, mostrar todos
    if (activeStatusFilters.value.size > 0 && !activeStatusFilters.value.has(item.status)) {
      return false;
    }
    // Filtro por texto
    if (searchText.value) {
      const searchLower = searchText.value.toLowerCase();
      return (
        item.id.toString().includes(searchLower) ||
        item.name.toLowerCase().includes(searchLower)
      );
    }
    return true;
  });
});

// Função para alternar ordenação
function toggleSort(key: keyof AssortmentResult) {
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

// Formatadores
const formatNumber = new Intl.NumberFormat('pt-BR', {
  minimumFractionDigits: 0,
  maximumFractionDigits: 0
});

const formatCurrency = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL'
});

const formatPercent = new Intl.NumberFormat('pt-BR', {
  style: 'percent',
  minimumFractionDigits: 1,
  maximumFractionDigits: 1
});

// Cálculos do resumo
const summary = computed(() => {
  if (!analysisResultStore.result?.length) return null;
  const statusCounts = {
    Ativo: 0,
    Inativo: 0,
    CRITICAL: 0
  };
  const totals = analysisResultStore.result.reduce((acc: any, item: AssortmentResult) => {
    statusCounts[item.status]++;
    return {
      quantity: acc.quantity + Number(item.quantity),
      value: acc.value + Number(item.value),
      margin: acc.margin + (Number(item.margin) * Number(item.value) / 100),
      currentStock: acc.currentStock + Number(item.currentStock)
    };
  }, { quantity: 0, value: 0, margin: 0, currentStock: 0 });
  return {
    totalItems: analysisResultStore.result.length,
    statusCounts,
    totals
  };
});
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/25">
    <div class="bg-white rounded-lg shadow-lg max-w-7xl w-full p-6 relative">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-bold">Resultado da Análise de Assortimento</h2>
        <Button
          variant="outline"
          size="sm"
          @click="exportToExcel"
          class="flex items-center gap-2"
        >
          <Download class="h-4 w-4" />
          Exportar Excel
        </Button>
      </div>
      
      <!-- Resumo -->
      <div v-if="summary" class="mb-6 p-4 bg-gray-50 rounded-lg">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div>
            <h3 class="text-sm font-medium text-gray-500">Total de Itens</h3>
            <p class="text-lg font-semibold">{{ formatNumber.format(summary.totalItems) }}</p>
          </div>
          <div>
            <h3 class="text-sm font-medium text-gray-500">Status</h3>
            <div class="space-y-1">
              <p class="text-sm">
                <span class="text-green-600">Ativo:</span> {{ formatNumber.format(summary.statusCounts.Ativo) }}
              </p>
              <p class="text-sm">
                <span class="text-yellow-600">Inativo:</span> {{ formatNumber.format(summary.statusCounts.Inativo) }}
              </p> 
            </div>
          </div>
          <div>
            <h3 class="text-sm font-medium text-gray-500">Valores Totais</h3>
            <p class="text-sm">Quantidade: {{ formatNumber.format(summary.totals.quantity) }}</p>
            <p class="text-sm">Valor: {{ formatCurrency.format(summary.totals.value) }}</p>
            <p class="text-sm">Margem: {{ formatCurrency.format(summary.totals.margin) }}</p>
          </div>
          <div>
            <h3 class="text-sm font-medium text-gray-500">Estoque Total</h3>
            <p class="text-lg font-semibold">{{ formatNumber.format(summary.totals.currentStock) }}</p>
          </div>
        </div>
      </div>

      <!-- Filtros -->
      <div class="mb-4 flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
          <div class="relative">
            <Search class="absolute left-2 top-2.5 h-4 w-4 text-gray-500" />
            <Input
              v-model="searchText"
              placeholder="Buscar por ID ou nome..."
              class="pl-8"
            />
            <button
              v-if="searchText"
              @click="searchText = ''"
              class="absolute right-2 top-2.5 text-gray-500 hover:text-gray-700"
            >
              <X class="h-4 w-4" />
            </button>
          </div>
        </div>
        <div class="flex gap-2">
          <Button
            v-for="status in ['Ativo', 'Inativo']"
            :key="status"
            :variant="activeStatusFilters.has(status as 'Ativo' | 'Inativo'  ) ? 'default' : 'outline'"
            :class="{
              'bg-green-600 hover:bg-green-700': status === 'Ativo' && activeStatusFilters.has(status as 'Ativo' | 'Inativo' ),
              'bg-yellow-600 hover:bg-yellow-700': status === 'Inativo' && activeStatusFilters.has(status as 'Ativo' | 'Inativo' ), 
            }"
            @click="toggleStatusFilter(status as 'Ativo' | 'Inativo' )"
          >
            {{ status }}
          </Button>
          <Button
            variant="outline"
            @click="clearFilters"
          >
            Limpar Filtros
          </Button>
        </div>
      </div>

      <!-- Tabela com scroll -->
      <div class="overflow-x-auto max-h-[60vh]">
        <table class="min-w-full text-sm border">
          <thead class="sticky top-0 bg-white z-10">
            <tr class="bg-gray-100">
              <th 
                v-for="(label, key) in {
                  id: 'ID',
                  category: 'Categoria', 
                  weightedAverage: 'Média Ponderada',
                  individualPercent: '% Individual',
                  accumulatedPercent: '% Acumulada',
                  abcClass: 'Classe ABC',
                  ranking: 'Ranking',
                  removeFromMix: 'Retirar?',
                  status: 'Status',
                  detailStatus: 'Detalhe do Status'
                }" 
                :key="key"
                class="px-2 py-1 border cursor-pointer hover:bg-gray-200"
                @click="toggleSort(key as keyof AssortmentResult)"
              >
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
            <tr v-for="item in filteredResults" :key="item.id">
              <td class="px-2 py-1 border">{{ item.id }}</td>
              <td class="px-2 py-1 border flex flex-col">
                <span class="text-sm font-medium">{{ item.category }}</span>
                <span class="text-xs text-gray-500">
                  {{ item.name }}
                </span>
              </td> 
              <td class="px-2 py-1 border">{{ item.weightedAverage }}</td>
              <td class="px-2 py-1 border">{{ (item.individualPercent * 100).toFixed(2) }}%</td>
              <td class="px-2 py-1 border">{{ (item.accumulatedPercent * 100).toFixed(2) }}%</td>
              <td class="px-2 py-1 border"
                :class="{
                  'text-green-600': item.abcClass === 'A',
                  'text-yellow-600': item.abcClass === 'B',
                  'text-red-600': item.abcClass === 'C',
                }"
              >{{ item.abcClass }}</td>
              <td class="px-2 py-1 border">{{ item.ranking }}</td>
              <td class="border px-2">{{ item.removeFromMix ? 'Sim' : 'Não' }}</td>
              <td class="px-2 py-1 border">{{ item.status }}</td>
              <td class="px-2 py-1 border">{{ item.statusDetail }}</td>
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