<script setup lang="ts">
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { ArrowUpDown, ArrowUp, ArrowDown, Search, X, Download, RefreshCw, Package, CheckCircle2, Archive, Trash2 } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip';
import * as XLSX from 'xlsx';
import { useAnalysisResultStore } from '@plannerate/store/editor/analysisResult';
import { useEditorStore } from '@plannerate/store/editor';
import { useAnalysisService } from '@plannerate/services/analysisService';
import { useAssortmentStatus } from '@plannerate/composables/useSortimentoStatus';

const headers = {
  id: 'EAN',
  category: 'Categoria',
  name: 'Nome',
  weightedAverage: 'Média Ponderada',
  individualPercent: '% Individual',
  accumulatedPercent: '% Acumulada',
  abcClass: 'Classe ABC',
  ranking: 'Ranking',
  removeFromMix: 'Retirar?',
  status: 'Status',
  detailStatus: 'Detalhe do Status'
};

interface AssortmentResult {
  id: string;
  ean: string;
  name: string;
  category: string;
  status: 'Ativo' | 'Inativo';
  quantity: number;
  value: number;
  margin: number;
  currentStock: number;
  removeFromMix: boolean;
}

defineProps<{ open: boolean }>();
const emit = defineEmits(['close', 'remove-from-gondola', 'update:open']);

const analysisResultStore = useAnalysisResultStore();
const editorStore = useEditorStore();

// Estado para a linha selecionada
const selectedItemId = ref<string | null>(null);

// Computed para o item selecionado
const selectedItem = computed(() => {
  if (selectedItemId.value === null || !analysisResultStore.result) return null;
  return (analysisResultStore.result as AssortmentResult[]).find(item => item.id.toString() === selectedItemId.value);
});

// Computed para controlar a exibição do botão de remover
const showRemoveButton = computed(() => {
  return selectedItem.value !== null && selectedItem.value !== undefined && selectedItem.value.removeFromMix;
});
const abcParams = ref({
  weights: {
    quantity: 0.30,
    value: 0.30,
    margin: 0.40,
  },
  thresholds: {
    a: 0.8,
    b: 0.85,
  },
});



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
    'Categoria': item.category,
    'Nome': item.name,
    'Média Ponderada': item.weightedAverage,
    '% Individual': item.individualPercent,
    '% Acumulada': item.accumulatedPercent,
    'Classe ABC': item.abcClass,
    'Ranking': item.ranking,
    'Retirar?': item.removeFromMix,
    'Status': item.status, 
    'Detalhe do Status': item.statusDetail
  }));

  // Criar worksheet
  const ws = XLSX.utils.json_to_sheet(exportData);

  // Ajustar largura das colunas
  const wscols = [
    { wch: 10 }, // EAN
    { wch: 40 }, // Categoria
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

function handleClose() {
  emit('close');
  emit('update:open', false);
}

// Formatadores
const formatNumber = new Intl.NumberFormat('pt-BR', {
  minimumFractionDigits: 0,
  maximumFractionDigits: 0
});
 

// Cálculos do resumo
const summary = computed(() => {
  if (!analysisResultStore.result?.length) return null;

  const totalItems = analysisResultStore.result.length;
  const statusCounts = { Ativo: 0, Inativo: 0 };
  let itemsToRemove = 0;

  (analysisResultStore.result as AssortmentResult[]).forEach((item: AssortmentResult) => {
    if (item.status === 'Ativo' || item.status === 'Inativo') {
      statusCounts[item.status]++;
    }
    if (item.removeFromMix) {
      itemsToRemove++;
    }
  });

  const activePercentage = totalItems > 0 ? ((statusCounts.Ativo / totalItems) * 100).toFixed(1) : '0.0';
  const inactivePercentage = totalItems > 0 ? ((statusCounts.Inativo / totalItems) * 100).toFixed(1) : '0.0';
  const removePercentage = totalItems > 0 ? ((itemsToRemove / totalItems) * 100).toFixed(1) : '0.0';

  return {
    totalItems,
    statusCounts,
    itemsToRemove,
    activePercentage,
    inactivePercentage,
    removePercentage,
  };
});

function removeFromGondola(selectedItemId: string | null) {
  if (selectedItemId) {
    emit('remove-from-gondola', selectedItemId);
    analysisResultStore.requestRecalculation();
  }
}

// Função para executar análise ABC com parâmetros específicos
async function executeABCAnalysisWithParams(weights: any, thresholds: any) {
    analysisResultStore.loading = true;
    const products: any[] = [];
    
    editorStore.getCurrentGondola?.sections.forEach(section => {
        section.shelves.forEach(shelf => {
            shelf.segments.forEach(segment => {
                const product = segment.layer.product as any;
                if (product) {
                    products.push({
                        id: product.id,
                        ean: product.ean,
                        name: product.name,
                        classification: product.classification,
                        currentStock: product.current_stock || 0
                    });
                }
            });
        });
    });

    try {
        if (products.length > 0) {
            const { getABCAnalysis } = useAnalysisService();
            const analysisData = await getABCAnalysis(
                products.map(p => p.id),
                {
                    planogram: editorStore.currentState?.id 
                }
            ) as any;
            const analyzed = useAssortmentStatus(analysisData, weights, thresholds);
            analysisResultStore.setResult(analyzed);
        } else {
            console.log('Nenhum produto encontrado na gôndola para análise.');
        }
    } catch (error) {
        console.error('Erro ao executar Análise ABC:', error);
    } finally {
        analysisResultStore.loading = false;
    }
}

// Listener para executar análise quando solicitado pelo ABCParamsPopover
window.addEventListener('execute-abc-analysis', (event: any) => {
    const { weights, thresholds } = event.detail;
    abcParams.value.weights = weights;
    abcParams.value.thresholds = thresholds;
    executeABCAnalysisWithParams(weights, thresholds);
});
analysisResultStore.$onAction(({ name }) => {
    if (name === 'requestRecalculation') {
        executeABCAnalysisWithParams(abcParams.value.weights, abcParams.value.thresholds);
    }
});
</script>

<template>
  <TooltipProvider>
    <Dialog :open="open" @update:open="handleClose">
      <DialogContent class="md:max-w-[70%] w-full max-h-[90vh] overflow-hidden flex flex-col">
        <DialogHeader>
          <div class="flex justify-between items-center">
            <div>
              <DialogTitle>Resultado da Análise de Assortimento</DialogTitle>
              <DialogDescription>
                Análise ABC baseada em quantidade, valor e margem de contribuição
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <div class="flex-1 overflow-hidden flex flex-col">
          <!-- Resumo -->
          <div v-if="summary" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4 flex-shrink-0">
            <!-- Card Total de Itens -->
            <div class="bg-white p-4 rounded-lg border-l-4 border-gray-400 shadow-sm flex items-center">
              <Package class="h-8 w-8 text-gray-400 mr-4 flex-shrink-0" />
              <div>
                <h3 class="text-sm font-medium text-gray-500">Total de Itens</h3>
                <p class="text-2xl font-bold">{{ formatNumber.format(summary.totalItems) }}</p>
              </div>
            </div>

            <!-- Card Itens Ativos -->
            <div class="bg-white p-4 rounded-lg border-l-4 border-green-500 shadow-sm flex items-center">
              <CheckCircle2 class="h-8 w-8 text-green-500 mr-4 flex-shrink-0" />
              <div>
                <h3 class="text-sm font-medium text-gray-500">Itens Ativos</h3>
                <p class="text-2xl font-bold">
                  {{ formatNumber.format(summary.statusCounts.Ativo) }}
                  <span class="text-sm font-normal text-gray-500">({{ summary.activePercentage }}%)</span>
                </p>
              </div>
            </div>

            <!-- Card Itens Inativos -->
            <div class="bg-white p-4 rounded-lg border-l-4 border-yellow-500 shadow-sm flex items-center">
              <Archive class="h-8 w-8 text-yellow-500 mr-4 flex-shrink-0" />
              <div>
                <h3 class="text-sm font-medium text-gray-500">Itens Inativos</h3>
                <p class="text-2xl font-bold">
                  {{ formatNumber.format(summary.statusCounts.Inativo) }}
                  <span class="text-sm font-normal text-gray-500">({{ summary.inactivePercentage }}%)</span>
                </p>
              </div>
            </div>

            <!-- Card A Retirar -->
            <div class="bg-white p-4 rounded-lg border-l-4 border-red-500 shadow-sm flex items-center">
              <Trash2 class="h-8 w-8 text-red-500 mr-4 flex-shrink-0" />
              <div>
                <h3 class="text-sm font-medium text-gray-500">A Retirar</h3>
                <p class="text-2xl font-bold">
                  {{ formatNumber.format(summary.itemsToRemove) }}
                  <span class="text-sm font-normal text-gray-500">({{ summary.removePercentage }}%)</span>
                </p>
              </div>
            </div>
          </div>

          <!-- Filtros -->
          <div class="mb-4 flex flex-col sm:flex-row gap-4 flex-shrink-0">
            <div class="flex-1">
              <div class="relative">
                <Search class="absolute left-2 top-2.5 h-4 w-4 text-gray-500" />
                <Input v-model="searchText" placeholder="Buscar por ID ou nome..." class="pl-8" />
                <button v-if="searchText" @click="searchText = ''"
                  class="absolute right-2 top-2.5 text-gray-500 hover:text-gray-700">
                  <X class="h-4 w-4" />
                </button>
              </div>
            </div>
            <div class="flex gap-2">
              <Button v-for="status in ['Ativo', 'Inativo']" :key="status"
                :variant="activeStatusFilters.has(status as 'Ativo' | 'Inativo') ? 'default' : 'outline'" :class="{
                  'bg-green-600 hover:bg-green-700': status === 'Ativo' && activeStatusFilters.has(status as 'Ativo' | 'Inativo'),
                  'bg-yellow-600 hover:bg-yellow-700': status === 'Inativo' && activeStatusFilters.has(status as 'Ativo' | 'Inativo'),
                }" @click="toggleStatusFilter(status as 'Ativo' | 'Inativo')">
                {{ status }}
              </Button>
              <Button variant="outline" @click="clearFilters">
                Limpar Filtros
              </Button>
            </div>
          </div>

          <!-- Tabela -->
          <div class="flex-1 overflow-auto border rounded-lg">
            <table class="text-sm border-collapse w-full">
              <thead class="sticky top-0 bg-white z-10">
                <tr class="bg-gray-100">
                  <th v-for="(label, key) in headers" :key="key"
                    class="px-2 py-1 border cursor-pointer hover:bg-gray-200 text-left"
                    @click="toggleSort(key as keyof AssortmentResult)">
                    <Tooltip :delay-duration="100">
                      <TooltipTrigger class="w-full flex items-center justify-between">
                        <span :class="{ 'truncate max-w-20': key !== 'name' }">{{ label }}</span>
                        <span class="ml-1">
                          <ArrowUpDown v-if="sortConfig.key !== key" class="h-4 w-4" />
                          <ArrowUp v-else-if="sortConfig.direction === 'asc'" class="h-4 w-4" />
                          <ArrowDown v-else class="h-4 w-4" />
                        </span>
                      </TooltipTrigger>
                      <TooltipContent>
                        <p>{{ label }}</p>
                      </TooltipContent>
                    </Tooltip>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr 
                  v-for="item in filteredResults" 
                  :key="item.id"
                  @click="selectedItemId = selectedItemId === item.id ? null : item.id"
                  :class="{'bg-blue-100 dark:bg-blue-900/50': selectedItemId === item.id, 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50': true}"
                >
                  <td class="px-2 py-1 border">{{ item.id }}</td>
                  <td class="px-2 py-1 border">{{ item.category }}</td>
                  <td class="px-2 py-1 border">{{ item.name }}</td>
                  <td class="px-2 py-1 border">{{ item.weightedAverage }}</td>
                  <td class="px-2 py-1 border">{{ (item.individualPercent * 100).toFixed(2) }}%</td>
                  <td class="px-2 py-1 border">{{ (item.accumulatedPercent * 100).toFixed(2) }}%</td>
                  <td class="px-2 py-1 border" :class="{
                    'text-green-600': item.abcClass === 'A',
                    'text-yellow-600': item.abcClass === 'B',
                    'text-red-600': item.abcClass === 'C',
                  }">{{ item.abcClass }}</td>
                  <td class="px-2 py-1 border">{{ item.ranking }}</td>
                  <td class="px-2 py-1 border">{{ item.removeFromMix ? 'Sim' : 'Não' }}</td>
                  <td class="px-2 py-1 border">{{ item.status }}</td>
                  <td class="px-2 py-1 border">{{ item.statusDetail }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="filteredResults.length === 0" class="text-gray-500 mt-4 text-center">Nenhum resultado encontrado.</div>
        </div>

        <DialogFooter class="mt-4 flex-shrink-0">
          <div class="flex flex-nowrap gap-2 justify-end">
            <Button variant="default" size="sm" @click="analysisResultStore.requestRecalculation()"
              class="flex items-center gap-2" :disabled="analysisResultStore.loading">
              <span v-if="analysisResultStore.loading" class="flex items-center gap-1">
                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                  </path>
                </svg>
                Calculando...
              </span>
              <span v-else>Recalcular</span>
              <RefreshCw class="h-4 w-4" />
            </Button>

            <Button variant="outline" size="sm" @click="exportToExcel" class="flex items-center gap-2">
              <Download class="h-4 w-4" />
              Exportar Excel
            </Button>

            <Button v-if="showRemoveButton" variant="destructive" size="sm" @click="removeFromGondola(selectedItemId)"
              class="flex items-center gap-2">
              Remover da Gôndola
            </Button>

            <Button variant="outline" @click="handleClose" size="sm">
              Fechar
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </TooltipProvider>
</template> 


<style scoped>
table {
  border-collapse: collapse;
}

th,
td {
  white-space: nowrap;
}
</style>