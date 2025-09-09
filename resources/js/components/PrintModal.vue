<template>
  <Dialog v-model:open="isOpen">
    <DialogContent class="w-auto max-w-2xl max-h-[90vh] z-[100] flex flex-col p-0">
      <div class="px-6 pt-6 pb-4">
        <DialogTitle>Configurações de Impressão</DialogTitle>
        <DialogDescription>
          Configure as opções de impressão do planograma e selecione os módulos desejados.
        </DialogDescription>
      </div>

      <div class="flex-1 overflow-y-auto px-6">
        <div class="flex flex-col gap-4 pb-4">
        <!-- Modo de Captura -->
        <div class="space-y-3">
          <h4 class="font-medium text-sm">Modo de Captura</h4>
          
          <div class="flex items-center space-x-3">
            <label class="flex items-center space-x-2 cursor-pointer">
              <input
                type="radio"
                :value="false"
                v-model="captureFullPlanogram"
                class="text-blue-600 focus:ring-blue-500"
              />
              <span class="text-sm">Módulos Individuais</span>
            </label>
            
            <label class="flex items-center space-x-2 cursor-pointer">
              <input
                type="radio"
                :value="true"
                v-model="captureFullPlanogram"
                class="text-blue-600 focus:ring-blue-500"
              />
              <span class="text-sm">Planograma Completo</span>
            </label>
          </div>
          
          <p class="text-xs text-gray-600 dark:text-gray-400">
            <span v-if="captureFullPlanogram">Captura todo o planograma em uma única imagem</span>
            <span v-else>Captura cada módulo separadamente</span>
          </p>
        </div>

        <!-- Seleção de Módulos -->
        <div v-if="!captureFullPlanogram" class="space-y-3">
          <h4 class="font-medium text-sm">Módulos para Impressão</h4>
          
          <div class="flex flex-wrap gap-2">
            <!-- Opção Todos os Módulos -->
            <label class="flex items-center space-x-2 cursor-pointer">
              <input
                type="checkbox"
                v-model="selectAllModules"
                @change="handleSelectAll"
                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
              <span class="text-sm font-medium">Todos os Módulos</span>
            </label>
          </div>

          <!-- Lista de Módulos Individuais -->
          <div class="grid grid-cols-2 md:grid-cols-3 gap-2 max-h-32 overflow-y-auto">
            <label
              v-for="module in availableModules"
              :key="module.id"
              class="flex items-center space-x-2 cursor-pointer p-2 rounded border hover:bg-gray-50 dark:hover:bg-gray-800"
            >
              <input
                type="checkbox"
                :value="module.id"
                v-model="selectedModules"
                @change="updateSelectAll"
                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
              <span class="text-sm">{{ module.name }}</span>
            </label>
          </div>

          <p v-if="!captureFullPlanogram && selectedModules.length === 0" class="text-sm text-red-500">
            Selecione pelo menos um módulo para impressão.
          </p>
        </div>

        <!-- Configurações de Qualidade -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <!-- Escala -->
          <div class="space-y-2">
            <Label for="scale">Escala de Qualidade</Label>
            <Select v-model="printConfig.scale">
              <SelectTrigger>
                <SelectValue placeholder="Selecione a escala" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="1">1x (Padrão)</SelectItem>
                <SelectItem value="2">2x (Alta Qualidade)</SelectItem>
                <SelectItem value="3">3x (Qualidade Superior)</SelectItem>
                <SelectItem value="4">4x (Ultra Qualidade)</SelectItem>
                <SelectItem value="5">5x (Máxima Qualidade)</SelectItem>
              </SelectContent>
            </Select>
            <p class="text-xs text-gray-500">Escalas maiores produzem melhor qualidade, mas são mais lentas. Recomendado: 2x para uso geral, 4x-5x para impressões profissionais</p>
          </div>

          <!-- Formato do Papel -->
          <div class="space-y-2">
            <Label for="format">Formato do Papel</Label>
            <Select v-model="printConfig.format">
              <SelectTrigger>
                <SelectValue placeholder="Selecione o formato" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="A4">A4 (210 x 297 mm)</SelectItem>
                <SelectItem value="A3">A3 (297 x 420 mm)</SelectItem>
                <SelectItem value="A5">A5 (148 x 210 mm)</SelectItem>
                <SelectItem value="letter">Letter (216 x 279 mm)</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <!-- Orientação -->
          <div class="space-y-2">
            <Label for="orientation">Orientação</Label>
            <Select v-model="printConfig.orientation">
              <SelectTrigger>
                <SelectValue placeholder="Selecione a orientação" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="landscape">Paisagem (Horizontal)</SelectItem>
                <SelectItem value="portrait">Retrato (Vertical)</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <!-- Cor de Fundo -->
          <div class="space-y-2">
            <Label for="background">Cor de Fundo</Label>
            <Select v-model="printConfig.backgroundColor">
              <SelectTrigger>
                <SelectValue placeholder="Selecione a cor de fundo" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="#ffffff">Branco</SelectItem>
                <SelectItem value="#f8f9fa">Cinza Claro</SelectItem>
                <SelectItem value="transparent">Transparente</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        <!-- Margens Personalizadas -->
        <div class="space-y-2">
          <h4 class="font-medium text-sm">Margens (mm)</h4>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            <div class="space-y-1">
              <Label for="marginTop" class="text-xs">Superior</Label>
              <Input
                id="marginTop"
                type="number"
                v-model.number="printConfig.margins.top"
                min="0"
                max="50"
                class="text-sm"
              />
            </div>
            <div class="space-y-1">
              <Label for="marginRight" class="text-xs">Direita</Label>
              <Input
                id="marginRight"
                type="number"
                v-model.number="printConfig.margins.right"
                min="0"
                max="50"
                class="text-sm"
              />
            </div>
            <div class="space-y-1">
              <Label for="marginBottom" class="text-xs">Inferior</Label>
              <Input
                id="marginBottom"
                type="number"
                v-model.number="printConfig.margins.bottom"
                min="0"
                max="50"
                class="text-sm"
              />
            </div>
            <div class="space-y-1">
              <Label for="marginLeft" class="text-xs">Esquerda</Label>
              <Input
                id="marginLeft"
                type="number"
                v-model.number="printConfig.margins.left"
                min="0"
                max="50"
                class="text-sm"
              />
            </div>
          </div>
        </div>

        <!-- Preview -->
        <div v-if="showPreview && previewImages.length > 0" class="space-y-2">
          <h4 class="font-medium text-sm">Preview</h4>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-2 max-h-48 overflow-y-auto">
            <div
              v-for="preview in previewImages"
              :key="preview.id"
              class="border rounded-lg p-2 space-y-2"
            >
              <p class="text-xs font-medium text-center">{{ preview.name }}</p>
              <img
                :src="preview.imageData"
                :alt="preview.name"
                class="w-full h-20 object-contain border rounded"
              />
            </div>
          </div>
        </div>

        <!-- Status de Processamento -->
        <div v-if="isProcessing" class="flex items-center justify-center space-x-2 py-4">
          <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
          <span class="text-sm">{{ processingStatus }}</span>
        </div>

        <!-- Verificação de Compatibilidade -->
        <div v-if="!browserCompatible" class="bg-yellow-50 border border-yellow-200 rounded-md p-3">
          <div class="flex">
            <AlertTriangle class="h-5 w-5 text-yellow-400" />
            <div class="ml-3">
              <h3 class="text-sm font-medium text-yellow-800">
                Navegador Incompatível
              </h3>
              <div class="mt-2 text-sm text-yellow-700">
                <p>Algumas funcionalidades podem não funcionar corretamente neste navegador.</p>
                <p>Recomendamos usar Chrome, Firefox ou Edge para melhor compatibilidade.</p>
              </div>
            </div>
          </div>
        </div>
        </div>
      </div>

      <div class="flex justify-between px-6 py-4 border-t bg-white mt-auto">
        <div class="flex gap-2">
          <Button
            v-if="!isProcessing"
            variant="outline"
            @click="generatePreview"
            :disabled="!canPrint"
          >
            <Eye class="h-4 w-4 mr-2" />
            Preview
          </Button>
        </div>
        
        <div class="flex gap-2">
          <Button variant="outline" @click="closeModal" :disabled="isProcessing">
            Cancelar
          </Button>
          <Button
            @click="handlePrintDirect"
            :disabled="!canPrint || isProcessing"
          >
            <PrinterIcon class="h-4 w-4 mr-2" />
            Imprimir
          </Button>
          <Button
            @click="handleDownloadPDF"
            :disabled="!canPrint || isProcessing"
          >
            <Download class="h-4 w-4 mr-2" />
            Baixar PDF
          </Button>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { 
  Dialog, 
  DialogContent, 
  DialogTitle, 
  DialogDescription 
} from '@plannerate/components/ui/dialog/index';
import { 
  Select, 
  SelectContent, 
  SelectItem, 
  SelectTrigger, 
  SelectValue 
} from '@plannerate/components/ui/select/index';
import { Button } from '@plannerate/components/ui/button/index';
import { Input } from '@plannerate/components/ui/input/index';
import { Label } from '@plannerate/components/ui/label/index';
import { 
  PrinterIcon, 
  Download, 
  Eye, 
  AlertTriangle 
} from 'lucide-vue-next';
import { printService } from '@plannerate/services/printService';
import { toast } from 'vue-sonner';

// Props & Emits
const props = defineProps<{
  open: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'update:open', value: boolean): void;
}>();

// Tipos
interface Module {
  id: string;
  name: string;
  element: HTMLElement;
  index: number;
}

interface PreviewImage {
  id: string;
  name: string;
  imageData: string;
  element: HTMLElement;
}

// Estado reativo
const isOpen = computed({
  get: () => props.open,
  set: (value) => emit('update:open', value)
});

const availableModules = ref<Module[]>([]);
const selectedModules = ref<string[]>([]);
const selectAllModules = ref(false);
const captureFullPlanogram = ref(false);
const showPreview = ref(false);
const previewImages = ref<PreviewImage[]>([]);
const isProcessing = ref(false);
const processingStatus = ref('');
const browserCompatible = ref(true);

// Configurações de impressão
const printConfig = ref({
  scale: '2',
  format: 'A4',
  orientation: 'landscape',
  backgroundColor: '#ffffff',
  margins: {
    top: 20,
    right: 20,
    bottom: 20,
    left: 20
  }
});

// Métodos
const detectModules = () => {
  const modules = printService.detectModules();
  availableModules.value = modules;
  
  if (modules.length === 0) {
    toast.warning('Nenhum módulo detectado', {
      description: 'Certifique-se de que o planograma esteja carregado corretamente.'
    });
  }
};

const handleSelectAll = () => {
  if (selectAllModules.value) {
    selectedModules.value = availableModules.value.map(m => m.id);
  } else {
    selectedModules.value = [];
  }
};

const updateSelectAll = () => {
  selectAllModules.value = selectedModules.value.length === availableModules.value.length;
};

const generatePreview = async () => {
  if (selectedModules.value.length === 0) return;

  isProcessing.value = true;
  processingStatus.value = 'Gerando preview...';
  showPreview.value = true;

  try {
    const captures = await printService.captureModules(selectedModules.value, {
      scale: 1, // Preview com escala menor para performance
      backgroundColor: printConfig.value.backgroundColor
    });

    previewImages.value = captures;
    toast.success('Preview gerado com sucesso!');
  } catch (error: any) {
    console.error('Erro ao gerar preview:', error);
    toast.error('Erro ao gerar preview', {
      description: error?.message || 'Erro desconhecido'
    });
  } finally {
    isProcessing.value = false;
    processingStatus.value = '';
  }
};

const handlePrintDirect = async () => {
  if (!captureFullPlanogram.value && selectedModules.value.length === 0) return;

  isProcessing.value = true;
  processingStatus.value = captureFullPlanogram.value ? 'Capturando planograma completo...' : 'Capturando módulos para impressão...';

  try {
    const config = {
      ...printConfig.value,
      scale: parseInt(printConfig.value.scale)
    };
    
    // Determina quais módulos capturar
    const modulesToCapture = captureFullPlanogram.value ? ['all'] : selectedModules.value;
    const captures = await printService.captureModules(modulesToCapture, config);
    
    processingStatus.value = 'Preparando impressão...';
    await printService.printDirect(captures, config);
    
    toast.success('Impressão iniciada!', {
      description: 'Verifique a janela de impressão do navegador.'
    });
    
    closeModal();
  } catch (error: any) {
    console.error('Erro na impressão:', error);
    toast.error('Erro na impressão', {
      description: error?.message || 'Erro desconhecido'
    });
  } finally {
    isProcessing.value = false;
    processingStatus.value = '';
  }
};

const handleDownloadPDF = async () => {
  if (!captureFullPlanogram.value && selectedModules.value.length === 0) return;

  isProcessing.value = true;
  processingStatus.value = captureFullPlanogram.value ? 'Capturando planograma completo...' : 'Capturando módulos...';

  try {
    const config = {
      ...printConfig.value,
      scale: parseInt(printConfig.value.scale)
    };
    // Determina quais módulos capturar
    const modulesToCapture = captureFullPlanogram.value ? ['all'] : selectedModules.value;
    const captures = await printService.captureModules(modulesToCapture, config);
    
    processingStatus.value = 'Gerando PDF...';
    const pdf = await printService.generatePDF(captures, config);
    
    processingStatus.value = 'Baixando arquivo...';
    pdf.save(`planograma-${new Date().toISOString().split('T')[0]}.pdf`);
    
    toast.success('PDF gerado com sucesso!', {
      description: 'O arquivo foi baixado para sua pasta de downloads.'
    });
    
    closeModal();
  } catch (error: any) {
    console.error('Erro ao gerar PDF:', error);
    toast.error('Erro ao gerar PDF', {
      description: error?.message || 'Erro desconhecido'
    });
  } finally {
    isProcessing.value = false;
    processingStatus.value = '';
  }
};

const closeModal = () => {
  isOpen.value = false;
  showPreview.value = false;
  previewImages.value = [];
  selectedModules.value = [];
  selectAllModules.value = false;
};

const checkCompatibility = () => {
  const compatibility = printService.checkBrowserCompatibility();
  browserCompatible.value = compatibility.supported;
  
  if (!compatibility.supported) {
    toast.warning('Navegador incompatível', {
      description: 'Algumas funcionalidades podem não funcionar corretamente.'
    });
  }
};

// Computed
const canPrint = computed(() => {
  if (captureFullPlanogram.value) {
    return true; // Planograma completo não precisa de seleção
  }
  return selectedModules.value.length > 0; // Módulos individuais precisam de seleção
});

// Watchers
watch(isOpen, (newValue) => {
  console.log('PrintModal isOpen changed:', newValue);
  if (newValue) {
    detectModules();
    checkCompatibility();
  }
});

// Lifecycle
onMounted(() => {
  checkCompatibility();
});
</script>




