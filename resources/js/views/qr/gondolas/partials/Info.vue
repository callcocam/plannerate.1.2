// Info.vue - Componente Principal (Versão QR Code - Apenas Escala)
<script setup lang="ts">
import { Minus, Plus, Printer, FileTextIcon, FileSpreadsheetIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useEditorStore } from '@plannerate/store/editor';
import type { Gondola } from '@plannerate/types/gondola';
// Componente de impressão
import PrintModal from '@plannerate/components/PrintModal.vue';
// Componentes UI
import {
  Popover,
  PopoverContent,
  PopoverTrigger
} from '@plannerate/components/ui/popover';
import { Button } from '@plannerate/components/ui/button';

const props = defineProps({
    gondola: {
        type: Object as () => Gondola | undefined,
        required: false,
    },
    readonly: {
        type: Boolean,
        default: true,
    },
});

const showPrintModal = ref(false);
const showReportOptions = ref(false);
const isGeneratingReport = ref(false);

const editorStore = useEditorStore();
const scaleFactor = computed(() => editorStore.currentScaleFactor);

const sections: any[] = [];//computed(() => editorStore.currentGondola?.sections.map(section => section.id) || []);

const updateScale = (newScale: number) => {
    const clampedScale = Math.max(2, Math.min(10, newScale));
    editorStore.setScaleFactor(clampedScale);
};

// Função para gerar relatório (implementação igual ao AnalysisPopover.vue)
async function generateReport(format: 'excel' | 'pdf' | 'compra' | 'dimensao' | 'image') {
  if (!props.gondola?.id) {
    alert('Nenhuma gôndola selecionada para gerar relatório.');
    return;
  }

  isGeneratingReport.value = true;
  showReportOptions.value = false;

  try {
    const gondolaId = props.gondola.id;

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
</script>

<template>
    <!-- Header QR Code - Apenas Escala -->
    <div class="fixed top-0 left-0 right-0 z-50 border-b border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-2">
            <div class="flex items-center justify-between gap-2">
                <!-- Nome da Gôndola -->
                <div class="flex items-center gap-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ gondola?.name || 'Gôndola' }}
                    </h2>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        📱 Visualização via QR Code
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                </div>
                <!-- Controles -->
                <div class="flex items-center space-x-2">
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

                    <Button @click="showPrintModal = true" type="button" variant="outline">
                        <Printer class="w-4 h-4" />
                    </Button>
                    <label class="text-sm text-gray-600 dark:text-gray-400">Escala:</label>
                    <div class="flex items-center space-x-2">
                        <Button type="button" variant="outline" size="sm" :disabled="scaleFactor <= 2"
                            @click="updateScale(scaleFactor - 1)" title="Diminuir escala">
                            <Minus class="h-4 w-4" />
                        </Button>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ scaleFactor }}x
                        </span>
                        <Button type="button" variant="outline" size="sm" :disabled="scaleFactor >= 10"
                            @click="updateScale(scaleFactor + 1)" title="Aumentar escala">
                            <Plus class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
        <PrintModal v-model:open="showPrintModal" @close="showPrintModal = false" :section-ids="sections" />
    </div>
</template>
