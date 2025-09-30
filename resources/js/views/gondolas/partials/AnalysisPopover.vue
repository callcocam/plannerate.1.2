<template>
  <div class="flex gap-2 justify-between">
    <div class="flex gap-2">
      <!-- Botão para limpar resultado se houver -->
      <Button v-if="analysisResultStore.result" @click="clearAnalysisResult" variant="destructive">
        <Paintbrush class="mr-1 h-4 w-4" />
        <span class="hidden xl:block">Limpar Resultado</span>
      </Button>
    </div>

    <div class="flex gap-2">
      <!-- Popover principal de cálculos -->
      <Popover v-model:open="showCalculos">
        <PopoverTrigger as-child>
          <Button variant="outline" size="sm" @click="showCalculos = true" title="Calculos">
            <NutIcon class="h-4 w-4" />
            <span class="hidden xl:block">Performance</span>
          </Button>
        </PopoverTrigger>
        <PopoverContent class="w-auto max-w-lg z-[1000]">
          <div class="flex flex-col gap-2">
            <Button variant="outline" @click="handleOpenABCParams">Calculos ABC</Button>
            <Button v-if="isAbcCalculating" variant="outline" @click="handleOpenTargetStockParams">Calculos Estoque Alvo
              Prateleira</Button>
            <Button variant="outline" @click="handleOpenBCGParams">Calculos Matriz BCG</Button>
            
            <!-- Divisor -->
            <div class="border-t border-gray-200 dark:border-gray-700 my-2"></div>
            
            <!-- Botão de Distribuição Hierárquica -->
            <Button variant="default" @click="handleOpenHierarchicalDistribution">
              🚀 Distribuição Hierárquica Auto
            </Button>
          </div>
        </PopoverContent>
      </Popover>

      <!-- Botão de imprimir -->
      <Button variant="outline" size="sm" title="Imprimir" @click="showPrintModal = true">
        <PrinterIcon class="h-4 w-4" />
        <span class="hidden xl:block">Imprimir</span>
      </Button>

      <!-- Popover de Gerar Relatório -->
      <Popover v-model:open="showReportOptions">
        <PopoverTrigger as-child>
          <Button variant="outline" size="sm" @click="showReportOptions = true" title="Gerar Relatório">
            <FileTextIcon class="h-4 w-4" />
            <span class="hidden xl:block">Relatórios</span>
          </Button>
        </PopoverTrigger>
        <PopoverContent class="w-auto max-w-sm z-[1000]">
          <div class="flex flex-col gap-2">
            <h4 class="font-medium text-sm mb-2">Formato do Relatório</h4>
            <Button variant="outline" @click="generateReport('excel')" :disabled="isGeneratingReport">
              <FileSpreadsheetIcon class="h-4 w-4 mr-2" />
              Relatório Reposição (.xlsx)
            </Button>
            <Button variant="outline" @click="generateReport('pdf')" :disabled="isGeneratingReport">
              <FileTextIcon class="h-4 w-4 mr-2" />
              Relatório Reposição (PDF)
            </Button>

            <!-- Divisor -->
            <div class="border-t border-gray-200 dark:border-gray-700 my-2"></div>

            <!-- Novos relatórios -->
            <Button variant="outline" @click="generateReport('compra')" :disabled="isGeneratingReport">
              <FileSpreadsheetIcon class="h-4 w-4 mr-2" />
              Relatório Compra (.xlsx)
            </Button>
            <Button variant="outline" @click="generateReport('dimensao')" :disabled="isGeneratingReport">
              <FileSpreadsheetIcon class="h-4 w-4 mr-2" />
              Relatório Sem Dimensões (.xlsx)
            </Button>
            <Button variant="outline" @click="generateReport('image')" :disabled="isGeneratingReport">
              <FileSpreadsheetIcon class="h-4 w-4 mr-2" />
              Relatório Sem Imagens (.xlsx)
            </Button>
          </div>
        </PopoverContent>
      </Popover>
    </div>
  </div>

  <!-- Dialogs para parâmetros -->
  <Dialog v-model:open="showABCParams">
    <DialogContent class="w-auto max-w-xl z-[1000]">
      <DialogTitle>Parâmetros ABC</DialogTitle>
      <DialogDescription>
        Ajuste os pesos e limites para a análise ABC conforme sua estratégia.
      </DialogDescription>
      <ABCParamsPopover v-model:weights="abcParams.weights" v-model:thresholds="abcParams.thresholds"
        @show-result-modal="showResultModal = true" @close="showABCParams = false" />
    </DialogContent>
  </Dialog>

  <Dialog v-model:open="showTargetStockParams">
    <DialogContent class="w-auto max-w-xl z-[1000]">
      <DialogTitle>Parâmetros de Estoque Alvo</DialogTitle>
      <DialogDescription>
        Configure os níveis de serviço e parâmetros de reposição para calcular o estoque ideal.
      </DialogDescription>
      <TargetStockParamsPopover :service-levels="targetStockParams.serviceLevels"
        :replenishment-params="targetStockParams.replenishmentParams" @show-result-modal="openTargetStockResultModal" />
    </DialogContent>
  </Dialog>

  <Dialog v-model:open="showBCGParams">
    <DialogContent class="w-auto max-w-xl z-[1000]">
      <DialogTitle>Parâmetros Matriz BCG</DialogTitle>
      <DialogDescription>
        Defina os parâmetros de participação de mercado e taxa de crescimento para análise BCG.
      </DialogDescription>
      <BCGConfigurationPopover v-model:x-axis="bcgConfig.xAxis" v-model:y-axis="bcgConfig.yAxis"
        @show-result-modal="handleShowBCGResultModal" @close="showBCGParams = false" />
    </DialogContent>
  </Dialog>


  <!-- Modais de resultado -->
  <AnalysisResultModal :open="showResultModal" @close="closeResultModal" @remove-from-gondola="removeFromGondola" />

  <TargetStockResultModal :open="showTargetStockResultModal" @close="showTargetStockResultModal = false" />

  <BCGResultModalImproved v-model:open="showBCGResultModal" />

  <!-- Modal de Impressão -->
  <PrintModal v-model:open="showPrintModal" @close="showPrintModal = false" />
  
  <!-- Dialog de Distribuição Hierárquica -->
  <Dialog v-model:open="showHierarchicalDistribution">
    <DialogContent class="w-auto max-w-xl z-[1000]">
      <DialogTitle>Distribuição Hierárquica por Categoria</DialogTitle>
      <DialogDescription>
        Distribui produtos automaticamente respeitando a hierarquia mercadológica com facing baseado no target stock.
      </DialogDescription>
      <div class="space-y-4">
        <!-- Parâmetros ABC -->
        <div>
          <h4 class="font-medium text-sm mb-2">Pesos ABC</h4>
          <div class="grid grid-cols-3 gap-2">
            <div>
              <label class="text-xs text-gray-600">Quantidade</label>
              <input type="number" v-model.number="hierarchicalParams.weights.quantity" 
                step="0.1" min="0" max="1" class="w-full px-2 py-1 border rounded" />
            </div>
            <div>
              <label class="text-xs text-gray-600">Valor</label>
              <input type="number" v-model.number="hierarchicalParams.weights.value" 
                step="0.1" min="0" max="1" class="w-full px-2 py-1 border rounded" />
            </div>
            <div>
              <label class="text-xs text-gray-600">Margem</label>
              <input type="number" v-model.number="hierarchicalParams.weights.margin" 
                step="0.1" min="0" max="1" class="w-full px-2 py-1 border rounded" />
            </div>
          </div>
        </div>

        <!-- Parâmetros de Target Stock -->
        <div>
          <h4 class="font-medium text-sm mb-2">Parâmetros de Estoque Alvo</h4>
          
          <!-- Níveis de Serviço -->
          <div class="mb-3">
            <label class="text-xs text-gray-600 font-medium block mb-1">Níveis de Serviço</label>
            <div class="grid grid-cols-3 gap-2">
              <div>
                <label class="text-xs text-gray-500">Classe A</label>
                <input type="number" v-model.number="hierarchicalParams.targetStock.serviceLevel.A" 
                  step="0.01" min="0" max="1" class="w-full px-2 py-1 border rounded text-sm" />
              </div>
              <div>
                <label class="text-xs text-gray-500">Classe B</label>
                <input type="number" v-model.number="hierarchicalParams.targetStock.serviceLevel.B" 
                  step="0.01" min="0" max="1" class="w-full px-2 py-1 border rounded text-sm" />
              </div>
              <div>
                <label class="text-xs text-gray-500">Classe C</label>
                <input type="number" v-model.number="hierarchicalParams.targetStock.serviceLevel.C" 
                  step="0.01" min="0" max="1" class="w-full px-2 py-1 border rounded text-sm" />
              </div>
            </div>
          </div>

          <!-- Dias de Cobertura -->
          <div>
            <label class="text-xs text-gray-600 font-medium block mb-1">Dias de Cobertura</label>
            <div class="grid grid-cols-3 gap-2">
              <div>
                <label class="text-xs text-gray-500">Classe A</label>
                <input type="number" v-model.number="hierarchicalParams.targetStock.coverageDays.A" 
                  step="1" min="1" class="w-full px-2 py-1 border rounded text-sm" />
              </div>
              <div>
                <label class="text-xs text-gray-500">Classe B</label>
                <input type="number" v-model.number="hierarchicalParams.targetStock.coverageDays.B" 
                  step="1" min="1" class="w-full px-2 py-1 border rounded text-sm" />
              </div>
              <div>
                <label class="text-xs text-gray-500">Classe C</label>
                <input type="number" v-model.number="hierarchicalParams.targetStock.coverageDays.C" 
                  step="1" min="1" class="w-full px-2 py-1 border rounded text-sm" />
              </div>
            </div>
          </div>
        </div>
        
        <!-- Botões de ação -->
        <div class="flex justify-end gap-2">
          <Button variant="outline" @click="showHierarchicalDistribution = false">
            Cancelar
          </Button>
          <Button variant="default" @click="executeHierarchicalDistribution" :disabled="isExecutingDistribution">
            {{ isExecutingDistribution ? 'Distribuindo...' : 'Executar Distribuição' }}
          </Button>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { NutIcon, PrinterIcon, Paintbrush, FileTextIcon, FileSpreadsheetIcon } from 'lucide-vue-next';

// Componentes UI
import {
  Popover,
  PopoverContent,
  PopoverTrigger
} from '@plannerate/components/ui/popover';
import { Button } from '@plannerate/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogTitle
} from '@plannerate/components/ui/dialog';

// Componente de impressão
import PrintModal from '@plannerate/components/PrintModal.vue';

// Imports dos componentes de análise
import ABCParamsPopover from '@plannerate/components/ABCParamsPopover.vue';
import AnalysisResultModal from '@plannerate/components/AnalysisResultModal.vue';
import TargetStockParamsPopover from '@plannerate/components/TargetStockParamsPopover.vue';
import TargetStockResultModal from '@plannerate/components/TargetStockResultModal.vue';
import BCGConfigurationPopover from '@plannerate/components/bcg/BCGConfigurationPopover.vue';
import BCGResultModalImproved from '@plannerate/components/bcg/BCGResultModalImproved.vue';

// Stores
import { useAnalysisResultStore } from '@plannerate/store/editor/analysisResult';
import { useEditorStore } from '@plannerate/store/editor';
import { useTargetStockResultStore } from '@plannerate/store/editor/targetStockResult';

// Stores
const analysisResultStore = useAnalysisResultStore();
const targetStockResultStore = useTargetStockResultStore();
const editorStore = useEditorStore();

console.log('AnalysisResultStore:', analysisResultStore);

// Estados dos popovers e modais
const showCalculos = ref(false);
const showABCParams = ref(false);
const showTargetStockParams = ref(false);
const showBCGParams = ref(false);
const showResultModal = ref(false);
const showBCGResultModal = ref(false);
const showTargetStockResultModal = ref(false);
const showReportOptions = ref(false);
const isGeneratingReport = ref(false);
const showPrintModal = ref(false);
const showHierarchicalDistribution = ref(false);
const isExecutingDistribution = ref(false);

const isAbcCalculating = ref(false);

// Watchers para fechar popover principal quando abrir parâmetros
watch(showABCParams, (newVal) => {
  if (!newVal) {
    showCalculos.value = false;
  }
});

watch(showTargetStockParams, (newVal) => {
  if (!newVal) {
    showCalculos.value = false;
  }
});

watch(showBCGParams, (newVal) => {
  if (!newVal) {
    showCalculos.value = false;
  }
});

// Configuração padrão
const bcgConfig = ref({
  xAxis: 'VALOR DE VENDA',
  yAxis: 'MARGEM DE CONTRIBUIÇÃO'
});
// Parâmetros dos cálculos
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

const targetStockParams = ref({
  serviceLevels: [
    { classification: 'A', level: 0.7 },
    { classification: 'B', level: 0.8 },
    { classification: 'C', level: 0.9 }
  ],
  replenishmentParams: [
    { classification: 'A', coverageDays: 2 },
    { classification: 'B', coverageDays: 5 },
    { classification: 'C', coverageDays: 7 }
  ],
});

// Parâmetros da distribuição hierárquica
const hierarchicalParams = ref({
  weights: {
    quantity: 0.30,
    value: 0.30,
    margin: 0.40,
  },
  targetStock: {
    serviceLevel: {
      A: 0.70,  // Igual ao modal de Target Stock
      B: 0.80,
      C: 0.90   // Igual ao modal de Target Stock
    },
    coverageDays: {
      A: 2,
      B: 5,
      C: 7
    }
  }
});


// Métodos
const closeResultModal = () => {
  showResultModal.value = false;
};

function openTargetStockResultModal() {
  showTargetStockResultModal.value = true;
  showTargetStockParams.value = false;
}

function removeFromGondola(selectedItemId: string | null) {
  if (selectedItemId) {
    const record = editorStore.getCurrentGondola?.sections.flatMap((section: any) =>
      section.shelves.flatMap((shelf: any) =>
        shelf.segments.flatMap((segment: any) => segment.layer.product)
      )
    ).find((product: any) => product?.ean === selectedItemId);

    if (record) {
      let sectionId = null;
      let shelfId = null;
      let segmentId = null;

      if (editorStore.getCurrentGondola) {
        editorStore.getCurrentGondola?.sections.forEach((section: any) => {
          section.shelves.forEach((shelf: any) => {
            shelf.segments.forEach((segment: any) => {
              if (segment.layer.product?.ean === selectedItemId) {
                sectionId = section.id;
                shelfId = shelf.id;
                segmentId = segment.id;
              }
            });
          });
        });

        if (sectionId && shelfId && segmentId) {
          editorStore.removeSegmentFromShelf(
            editorStore.getCurrentGondola?.id,
            sectionId,
            shelfId,
            segmentId
          );
        }
      }
    }
  }
}

const handleShowBCGResultModal = () => {
  showBCGResultModal.value = true;
  showBCGParams.value = false;
};

// Funções para abrir popovers de parâmetros
function handleOpenABCParams() {
  showCalculos.value = false;
  showABCParams.value = true;
  isAbcCalculating.value = true;
}

function handleOpenTargetStockParams() {
  showCalculos.value = false;
  showTargetStockParams.value = true;
}

function handleOpenBCGParams() {
  showCalculos.value = false;
  showBCGParams.value = true;
}

// Função para gerar relatório
async function generateReport(format: 'excel' | 'pdf' | 'compra' | 'dimensao' | 'image') {
  if (!editorStore.getCurrentGondola) {
    alert('Nenhuma gôndola selecionada para gerar relatório.');
    return;
  }

  isGeneratingReport.value = true;
  showReportOptions.value = false;

  try {
    const gondolaId = editorStore.getCurrentGondola.id;

    // Definir endpoint baseado no formato
    let endpoint: string;
    let acceptHeader: string;
    let fileExtension: string;

    switch (format) {
      case 'excel':
        endpoint = `/api/plannerate/gondola-report/${gondolaId}/excel`;
        acceptHeader = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        fileExtension = 'xlsx';
        break;
      case 'pdf':
        endpoint = `/api/plannerate/gondola-report/${gondolaId}/pdf`;
        acceptHeader = 'application/pdf';
        fileExtension = 'pdf';
        break;
      case 'compra':
        endpoint = `/api/plannerate/gondola-report/${gondolaId}/compra`;
        acceptHeader = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        fileExtension = 'xlsx';
        break;
      case 'dimensao':
        endpoint = `/api/plannerate/gondola-report/${gondolaId}/dimensao`;
        acceptHeader = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        fileExtension = 'xlsx';
        break;
      case 'image':
        endpoint = `/api/plannerate/gondola-report/${gondolaId}/image`;
        acceptHeader = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        fileExtension = 'xlsx';
        break;
      default:
        throw new Error('Formato de relatório inválido');
    }

    // Fazer download do arquivo
    const response = await fetch(endpoint, {
      method: 'GET',
      headers: {
        'Accept': acceptHeader,
        'Authorization': `Bearer ${localStorage.getItem('auth_token') || ''}`,
      },
    });

    if (!response.ok) {
      throw new Error(`Erro ao gerar relatório: ${response.statusText}`);
    }

    // Obter o nome do arquivo do header ou usar um padrão
    const contentDisposition = response.headers.get('content-disposition');
    let filename = `relatorio-gondola-${gondolaId}.${fileExtension}`;

    if (contentDisposition) {
      const filenameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
      if (filenameMatch) {
        filename = filenameMatch[1].replace(/['"]/g, '');
      }
    }

    // Criar blob e fazer download
    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

  } catch (error: any) {
    console.error('Erro ao gerar relatório:', error);
    alert(`Erro ao gerar relatório: ${error?.message || 'Erro desconhecido'}`);
  } finally {
    isGeneratingReport.value = false;
  }
}

const clearAnalysisResult = () => {
  analysisResultStore.setResult(null);
  targetStockResultStore.setResult([], []);
  isAbcCalculating.value = false;
};

// Função para abrir modal de distribuição hierárquica
function handleOpenHierarchicalDistribution() {
  showCalculos.value = false;
  showHierarchicalDistribution.value = true;
}

// Função para executar distribuição hierárquica
async function executeHierarchicalDistribution() {
  if (!editorStore.currentState) {
    alert('Nenhum planograma selecionado.');
    return;
  }

  const gondola = editorStore.getCurrentGondola;
  if (!gondola) {
    alert('Nenhuma gôndola selecionada.');
    return;
  }

  // Validar pesos
  const totalWeight = hierarchicalParams.value.weights.quantity + 
                      hierarchicalParams.value.weights.value + 
                      hierarchicalParams.value.weights.margin;
  
  if (Math.abs(totalWeight - 1.0) > 0.01) {
    alert('A soma dos pesos deve ser igual a 1.0');
    return;
  }

  isExecutingDistribution.value = true;

  try {
    // Usar apiService para garantir que o CSRF token seja enviado
    const { apiService } = await import('@plannerate/services');
    
    const result = await apiService.post('/analysis/hierarchical-distribution', {
      gondola_id: gondola.id,
      planogram: editorStore.currentState.id,
      // Enviar array vazio para o backend buscar todos os produtos automaticamente
      products: [],
      weights: hierarchicalParams.value.weights,
      targetStock: hierarchicalParams.value.targetStock,
      storeId: editorStore.currentState.store_id ? parseInt(editorStore.currentState.store_id) : undefined
    });

    if (result.success) {
      alert(`✅ Distribuição concluída!\n\n` +
            `Total: ${result.data.placed_products}/${result.data.total_products} produtos\n` +
            `Falhas: ${result.data.failed_products}\n\n` +
            `A página será recarregada para exibir as mudanças.`);
      
      // Recarregar planograma
      window.location.reload();
    } else {
      alert('❌ Erro na distribuição: ' + result.message);
    }
  } catch (error: any) {
    console.error('Erro ao executar distribuição hierárquica:', error);
    alert('❌ Erro ao executar distribuição: ' + (error?.message || 'Erro desconhecido'));
  } finally {
    isExecutingDistribution.value = false;
    showHierarchicalDistribution.value = false;
  }
}
</script>