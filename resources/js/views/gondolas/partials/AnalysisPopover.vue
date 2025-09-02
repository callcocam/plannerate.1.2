<template>
  <div class="flex gap-2 justify-between">
    <div class="flex gap-2">
      <!-- Botão para limpar resultado se houver -->
      <Button v-if="analysisResultStore.result" @click="analysisResultStore.setResult(null);" variant="destructive">
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
            <Button variant="outline" @click="handleOpenTargetStockParams">Calculos Estoque Alvo Prateleira</Button>
            <Button variant="outline" @click="handleOpenBCGParams">Calculos Matriz BCG</Button>
          </div>
        </PopoverContent>
      </Popover>
      
      <!-- Botão de imprimir -->
      <Button variant="outline" size="sm" title="Imprimir">
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
      <ABCParamsPopover
        v-model:weights="abcParams.weights"
        v-model:thresholds="abcParams.thresholds"
        @show-result-modal="showResultModal = true"
        @close="showABCParams = false"
      />
    </DialogContent>
  </Dialog>

  <Dialog v-model:open="showTargetStockParams">
    <DialogContent class="w-auto max-w-xl z-[1000]">
      <DialogTitle>Parâmetros de Estoque Alvo</DialogTitle>
      <DialogDescription>
        Configure os níveis de serviço e parâmetros de reposição para calcular o estoque ideal.
      </DialogDescription>
      <TargetStockParamsPopover 
        :service-levels="targetStockParams.serviceLevels"
        :replenishment-params="targetStockParams.replenishmentParams"
        @show-result-modal="openTargetStockResultModal" 
      />
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
  <AnalysisResultModal 
    :open="showResultModal" 
    @close="closeResultModal"
    @remove-from-gondola="removeFromGondola" 
  />
  
  <TargetStockResultModal 
    :open="showTargetStockResultModal" 
    @close="showTargetStockResultModal = false" 
  /> 
  
  <BCGResultModalImproved 
  v-model:open="showBCGResultModal" 
  />
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { NutIcon, PrinterIcon, Paintbrush, FileTextIcon, FileSpreadsheetIcon } from 'lucide-vue-next';  

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

// Stores
const analysisResultStore = useAnalysisResultStore();
const editorStore = useEditorStore();

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
async function generateReport(format: 'excel' | 'pdf') {
  if (!editorStore.getCurrentGondola) {
    alert('Nenhuma gôndola selecionada para gerar relatório.');
    return;
  }

  isGeneratingReport.value = true;
  showReportOptions.value = false;

  try {
    const gondolaId = editorStore.getCurrentGondola.id;
    const endpoint = format === 'excel' 
      ? `/api/plannerate/gondola-report/${gondolaId}/excel`
      : `/api/plannerate/gondola-report/${gondolaId}/pdf`;

    // Fazer download do arquivo
    const response = await fetch(endpoint, {
      method: 'GET',
      headers: {
        'Accept': format === 'excel' 
          ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
          : 'application/pdf',
        'Authorization': `Bearer ${localStorage.getItem('auth_token') || ''}`,
      },
    });

    if (!response.ok) {
      throw new Error(`Erro ao gerar relatório: ${response.statusText}`);
    }

    // Obter o nome do arquivo do header ou usar um padrão
    const contentDisposition = response.headers.get('content-disposition');
    let filename = `relatorio-gondola-${gondolaId}.${format === 'excel' ? 'xlsx' : 'pdf'}`;
    
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
</script>