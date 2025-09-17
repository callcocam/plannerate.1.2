<template>
  <TooltipProvider>
    <Dialog :open="open" @update:open="handleClose">
      <DialogContent class="md:max-w-[90%] xl:max-w-[70%] w-full max-h-[90%] overflow-hidden flex flex-col">
        <DialogHeader>
          <div class="flex justify-between items-center">
            <div>
              <DialogTitle>Resultado do Estoque Alvo</DialogTitle>
              <DialogDescription>
                Análise de estoque alvo baseada em demanda média e parâmetros de reposição
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

            <!-- Card Classificação A -->
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

            <!-- Card Classificação B -->
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

            <!-- Card Classificação C -->
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

          <!-- Filtros -->
          <div class="mb-4 flex flex-col sm:flex-row gap-4 flex-shrink-0">
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
            <div class="flex gap-2">
              <Button v-for="classification in ['A', 'B', 'C']" :key="classification"
                :variant="activeClassificationFilters.has(classification) ? 'default' : 'outline'" :class="{
                  'bg-green-600 hover:bg-green-700': classification === 'A' && activeClassificationFilters.has(classification),
                  'bg-yellow-600 hover:bg-yellow-700': classification === 'B' && activeClassificationFilters.has(classification),
                  'bg-red-600 hover:bg-red-700': classification === 'C' && activeClassificationFilters.has(classification)
                }" @click="toggleClassificationFilter(classification)">
                {{ classification }}
              </Button>
              <Button variant="outline" @click="clearFilters">
                Limpar Filtros
              </Button>
            </div>
          </div>

          <!-- Tabela -->
          <div class="flex-1 overflow-auto border rounded-lg min-h-96">
            <table class="text-sm border-collapse w-full">
              <thead class="sticky top-0 bg-white z-10">
                <tr class="bg-gray-100">
                  <th v-for="(label, key) in headers" :key="key" class="px-2 py-1 border cursor-pointer hover:bg-gray-200 text-left"
                    @click="toggleSort(key as keyof StockAnalysis)">
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
              <tbody >
                <tr v-for="item in filteredResults" :key="item.ean" @click="selectedItemId = selectedItemId === item.ean ? null : item.ean"
                  :class="{'bg-blue-100 dark:bg-blue-900/50': selectedItemId === item.ean, 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50': true}">
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
                  <td class="px-2 py-1 border">{{ item.currentStock }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="filteredResults.length === 0" class="text-gray-500 mt-4 text-center">Nenhum resultado encontrado.</div>
        </div>

        <DialogFooter class="mt-4 flex-shrink-0">
          <div class="flex flex-nowrap gap-2 justify-end">
            <Button variant="default" size="sm" @click="targetStockResultStore.requestRecalculation()"
              class="flex items-center gap-2" :disabled="targetStockResultStore.loading">
              <span v-if="targetStockResultStore.loading" class="flex items-center gap-1">
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

            <Button variant="outline" @click="handleClose" size="sm">
              Fechar
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </TooltipProvider>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { ArrowUpDown, ArrowUp, ArrowDown, Search, X, Download, RefreshCw, Package, Star, Circle, Triangle } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog';
import * as XLSX from 'xlsx';
import type { StockAnalysis, Replenishment } from '@plannerate/composables/useTargetStock';
import { useTargetStockResultStore } from '@plannerate/store/editor/targetStockResult';
import { useEditorStore } from '@plannerate/store/editor';
import { useAnalysisService } from '@plannerate/services/analysisService';
import { useTargetStock, type ServiceLevel } from '@plannerate/composables/useTargetStock';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip';

const headers = {
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
  allowsFacing: 'Estoque permite numero de frentes',
  currentStock: 'Estoque Atual',
};

const props = defineProps<{
  open: boolean;
}>();
const emit = defineEmits(['close', 'update:open']);

const targetStockResultStore = useTargetStockResultStore();
const editorStore = useEditorStore();
const analysisService = useAnalysisService();

// Estado de ordenação
const sortConfig = ref({
  key: 'ean' as keyof StockAnalysis,
  direction: 'asc' as 'asc' | 'desc'
});

// Estado dos filtros
const searchText = ref('');
const activeClassificationFilters = ref<Set<string>>(new Set(['A', 'B', 'C']));

// Parâmetros para recálculo
const targetStockParams = ref({
  serviceLevels: [
    { classification: 'A', level: 0.95 },
    { classification: 'B', level: 0.90 },
    { classification: 'C', level: 0.85 }
  ] as ServiceLevel[],
  replenishmentParams: [
    { classification: 'A', coverageDays: 7 },
    { classification: 'B', coverageDays: 14 },
    { classification: 'C', coverageDays: 21 }
  ] as Replenishment[]
});

// Estado para a linha selecionada
const selectedItemId = ref<string | null>(null);
 
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

// Função para alternar filtro de classificação
function toggleClassificationFilter(classification: string) {
  if (activeClassificationFilters.value.has(classification)) {
    activeClassificationFilters.value.delete(classification);
  } else {
    activeClassificationFilters.value.add(classification);
  }
}

// Função para limpar todos os filtros
function clearFilters() {
  searchText.value = '';
  activeClassificationFilters.value = new Set(['A', 'B', 'C']);
}

// Ordenação
const sortedResults = computed(() => {
  if (!targetStockResultStore.result) return [];
  return [...targetStockResultStore.result].sort((a, b) => {
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
    // Filtro por classificação
    if (activeClassificationFilters.value.size > 0 && !activeClassificationFilters.value.has(item.classification)) {
      return false;
    }
    // Filtro por texto
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

function handleClose() {
  emit('close');
  emit('update:open', false);
}

function getCoverageDays(classification: string) {
  console.log(classification)
  const param = targetStockResultStore.replenishmentParams.find(p => p.classification === classification);
  return param?.coverageDays || 0;
}

const formatNumber = new Intl.NumberFormat('pt-BR', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2
});

// Cálculos do resumo
const summary = computed(() => {
  if (!targetStockResultStore.result) return null;

  const totalItems = targetStockResultStore.result.length;
  const classificationCounts = {
    A: targetStockResultStore.result.filter(item => item.classification === 'A').length,
    B: targetStockResultStore.result.filter(item => item.classification === 'B').length,
    C: targetStockResultStore.result.filter(item => item.classification === 'C').length
  };

  const percentageA = totalItems > 0 ? ((classificationCounts.A / totalItems) * 100).toFixed(1) : '0.0';
  const percentageB = totalItems > 0 ? ((classificationCounts.B / totalItems) * 100).toFixed(1) : '0.0';
  const percentageC = totalItems > 0 ? ((classificationCounts.C / totalItems) * 100).toFixed(1) : '0.0';

  return {
    totalItems,
    classificationCounts,
    percentageA,
    percentageB,
    percentageC,
  };
});

// Função para executar análise de estoque alvo com parâmetros específicos
async function executeTargetStockAnalysisWithParams(serviceLevels: ServiceLevel[], replenishmentParams: Replenishment[]) {
    targetStockResultStore.loading = true;
    const products: any[] = [];
    
    editorStore.getCurrentGondola?.sections.forEach(section => {
        section.shelves.forEach(shelf => {
            shelf.segments.forEach(segment => {
                // 🔒 VERIFICAÇÃO DE SEGURANÇA: Verificar se layer e product existem
                const product = segment.layer?.product as any;
                if (product && segment.layer) {
                    products.push({
                        id: product.id,
                        ean: product.ean,
                        name: product.name,
                        classification: product.classification || 'A',
                    });
                }
            });
        });
    });

    try {
        if (products.length > 0) {
            const sales = await analysisService.getTargetStockData(
                products.map(p => p.id),
                {
                    planogram: editorStore.currentState?.id
                }
            ) as any;
            
            // Transformar os dados de vendas no formato esperado
            const productsWithSales = products.map(product => {
                const productSales = sales.find((sale: any) => sale.product_id === product.id);
                return {
                    ...product,
                    standard_deviation: productSales?.standard_deviation,
                    average_sales: productSales?.average_sales,
                    currentStock: productSales?.currentStock,
                    variability: productSales?.variability,
                    sales: productSales ? Object.values(productSales.sales_by_day) : []
                };
            });
            
            const analyzed = useTargetStock(
                productsWithSales,
                serviceLevels,
                replenishmentParams
            );
            
            // Atualizar o store com os resultados
            targetStockResultStore.setResult(analyzed, replenishmentParams);
        } else {
            console.log('Nenhum produto encontrado na gôndola para análise de estoque alvo.');
        }
    } catch (error) {
        console.error('Erro ao executar Análise de Estoque Alvo:', error);
    } finally {
        targetStockResultStore.loading = false;
    }
}

// Listener para executar análise quando solicitado pelo TargetStockParamsPopover
window.addEventListener('execute-target-stock-analysis', (event: any) => {
    const { serviceLevels, replenishmentParams } = event.detail;
    targetStockParams.value.serviceLevels = serviceLevels;
    targetStockParams.value.replenishmentParams = replenishmentParams;
    executeTargetStockAnalysisWithParams(serviceLevels, replenishmentParams);
});

// Listener para recálculo
targetStockResultStore.$onAction(({ name }) => {
    if (name === 'requestRecalculation') {
        executeTargetStockAnalysisWithParams(
            targetStockParams.value.serviceLevels,
            targetStockParams.value.replenishmentParams
        );
    }
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