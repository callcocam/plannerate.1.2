<template>
  <Dialog v-model:open="isOpen">
    <DialogContent :class="[
      'z-[1000] transition-all duration-300',
      activeTab === 'zones' ? 'max-w-7xl h-full p-4 flex flex-col' : 'max-w-2xl'
    ]">
      <DialogHeader :class="activeTab === 'zones' ? 'mb-2 flex-shrink-0' : ''">
        <DialogTitle>Gerar Planograma Automaticamente</DialogTitle>
        <DialogDescription>
          Configure os parâmetros para distribuição automática de produtos ou defina zonas personalizadas.
        </DialogDescription>
      </DialogHeader>

      <!-- Sistema de Abas -->
      <Tabs v-model="activeTab" :class="activeTab === 'zones' ? 'w-full -mt-2 flex-1 flex flex-col overflow-hidden' : 'w-full'">
        <TabsList :class="activeTab === 'zones' ? 'grid w-full grid-cols-2 flex-shrink-0' : 'grid w-full grid-cols-2'">
          <TabsTrigger value="auto">🚀 Gerar Auto</TabsTrigger>
          <TabsTrigger value="zones">📍 Definir Zonas</TabsTrigger>
        </TabsList>

        <!-- Tab 1: Gerar Auto -->
        <TabsContent value="auto" class="space-y-4 mt-4">
          <!-- Parâmetros ABC -->
          <div>
            <h4 class="font-medium text-sm mb-2">Pesos ABC</h4>
            <div class="grid grid-cols-3 gap-2">
              <div>
                <label class="text-xs text-gray-600">Quantidade</label>
                <input type="number" v-model.number="params.weights.quantity" 
                  step="0.1" min="0" max="1" class="w-full px-2 py-1 border rounded" />
              </div>
              <div>
                <label class="text-xs text-gray-600">Valor</label>
                <input type="number" v-model.number="params.weights.value" 
                  step="0.1" min="0" max="1" class="w-full px-2 py-1 border rounded" />
              </div>
              <div>
                <label class="text-xs text-gray-600">Margem</label>
                <input type="number" v-model.number="params.weights.margin" 
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
                  <input type="number" v-model.number="params.targetStock.serviceLevel.A" 
                    step="0.01" min="0" max="1" class="w-full px-2 py-1 border rounded text-sm" />
                </div>
                <div>
                  <label class="text-xs text-gray-500">Classe B</label>
                  <input type="number" v-model.number="params.targetStock.serviceLevel.B" 
                    step="0.01" min="0" max="1" class="w-full px-2 py-1 border rounded text-sm" />
                </div>
                <div>
                  <label class="text-xs text-gray-500">Classe C</label>
                  <input type="number" v-model.number="params.targetStock.serviceLevel.C" 
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
                  <input type="number" v-model.number="params.targetStock.coverageDays.A" 
                    step="1" min="1" class="w-full px-2 py-1 border rounded text-sm" />
                </div>
                <div>
                  <label class="text-xs text-gray-500">Classe B</label>
                  <input type="number" v-model.number="params.targetStock.coverageDays.B" 
                    step="1" min="1" class="w-full px-2 py-1 border rounded text-sm" />
                </div>
                <div>
                  <label class="text-xs text-gray-500">Classe C</label>
                  <input type="number" v-model.number="params.targetStock.coverageDays.C" 
                    step="1" min="1" class="w-full px-2 py-1 border rounded text-sm" />
                </div>
              </div>
            </div>
          </div>

          <!-- Filtros de Produtos -->
          <div>
            <h4 class="font-medium text-sm mb-2">Filtros de Produtos</h4>
            
            <!-- Status de uso -->
            <div class="mb-3">
              <label class="text-xs text-gray-600 font-medium block mb-1">Status de uso</label>
              <div class="space-y-2">
                <div class="flex items-center space-x-2">
                  <input id="status-all" type="radio" value="all" v-model="params.filters.usageStatus"
                    class="h-4 w-4 border-gray-300 text-primary focus:ring-2 focus:ring-primary" />
                  <label for="status-all" class="text-sm font-normal">Todos os produtos</label>
                </div>
                <div class="flex items-center space-x-2">
                  <input id="status-unused" type="radio" value="unused" v-model="params.filters.usageStatus"
                    class="h-4 w-4 border-gray-300 text-primary focus:ring-2 focus:ring-primary" />
                  <label for="status-unused" class="text-sm font-normal">Apenas não usados</label>
                </div>
                <div class="flex items-center space-x-2">
                  <input id="status-used" type="radio" value="used" v-model="params.filters.usageStatus"
                    class="h-4 w-4 border-gray-300 text-primary focus:ring-2 focus:ring-primary" />
                  <label for="status-used" class="text-sm font-normal">Apenas já usados</label>
                </div>
              </div>
            </div>

            <!-- Checkbox para incluir produtos sem dimensões -->
            <div class="flex items-center space-x-2">
              <input id="include-dimensionless" type="checkbox" v-model="params.filters.includeDimensionless"
                class="h-4 w-4 border-gray-300 text-primary focus:ring-2 focus:ring-primary rounded" />
              <label for="include-dimensionless" class="text-sm font-normal">Incluir produtos sem dimensões</label>
            </div>
          </div>

          <!-- Opções de Zonas -->
          <div class="border-t pt-4">
            <h4 class="font-medium text-sm mb-2">📍 Zonas de Performance</h4>
            <div class="flex items-start space-x-2">
              <input id="use-zones" type="checkbox" v-model="params.useZones"
                class="h-4 w-4 border-gray-300 text-primary focus:ring-2 focus:ring-primary rounded mt-0.5" />
              <div>
                <label for="use-zones" class="text-sm font-normal block">Usar zonas configuradas</label>
                <p class="text-xs text-gray-500 mt-0.5">
                  Distribui produtos seguindo as regras de cada zona definida (ABC, margem, prioridade, etc.)
                </p>
              </div>
            </div>
          </div>

          <!-- Botões de ação -->
          <div class="flex justify-end gap-2 pt-4">
            <Button variant="outline" @click="closeModal">
              Cancelar
            </Button>
            <Button variant="default" @click="executeAutoGeneration" :disabled="isExecuting">
              {{ isExecuting ? 'Gerando...' : '🚀 Gerar Planograma' }}
            </Button>
          </div>
        </TabsContent>

        <!-- Tab 2: Definir Zonas -->
        <TabsContent value="zones" class="mt-2 flex-1 flex flex-col overflow-hidden">
          <!-- Área de conteúdo com scroll -->
          <div class="flex-1 overflow-y-auto pr-1 pb-2">
            <ZoneConfiguration
              :shelf-count="totalShelvesCount"
              :zones="zoneConfiguration"
              :modules="modulesStructure"
              @update:zones="handleZonesUpdate"
            />
          </div>

          <!-- Botões de ação FIXOS -->
          <div class="flex justify-end gap-2 pt-3 border-t bg-white dark:bg-gray-900 flex-shrink-0">
            <Button variant="outline" @click="closeModal" :disabled="isExecuting">
              Cancelar
            </Button>
            <Button variant="default" @click="saveZoneConfiguration" :disabled="zoneConfiguration.length === 0 || isExecuting">
              {{ isExecuting ? 'Salvando...' : '💾 Salvar Configuração de Zonas' }}
            </Button>
          </div>
        </TabsContent>
      </Tabs>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle
} from '@plannerate/components/ui/dialog';
import { Button } from '@plannerate/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@plannerate/components/ui/tabs';
import { useEditorStore } from '@plannerate/store/editor';
import ZoneConfiguration from '@plannerate/components/zones/ZoneConfiguration.vue';

// Tipos
interface ZoneRules {
  priority: string;
  exposure_type: string;
  abc_filter?: string[];
  min_margin_percent?: number;
  max_margin_percent?: number;
}

interface Zone {
  id: string;
  name: string;
  shelf_indexes: number[];
  performance_multiplier: number;
  rules: ZoneRules;
}

// Props
const props = defineProps<{
  open: boolean;
}>();

// Emits
const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
}>();

// State
const editorStore = useEditorStore();
const activeTab = ref('auto');
const isExecuting = ref(false);
const zoneConfiguration = ref<Zone[]>([]);

// Computed
const isOpen = computed({
  get: () => props.open,
  set: (value) => emit('update:open', value)
});

const totalShelvesCount = computed(() => {
  const gondola = editorStore.getCurrentGondola;
  if (!gondola) return 0;
  
  // Contar total de prateleiras em todas as seções
  return gondola.sections.reduce((total: number, section: any) => {
    return total + (section.shelves?.length || 0);
  }, 0);
});

const modulesStructure = computed(() => {
  const gondola = editorStore.getCurrentGondola;
  if (!gondola) return [];
  
  // Converter sections para estrutura de módulos
  return gondola.sections.map((section: any) => ({
    id: section.id,
    shelves: section.shelves || []
  }));
});

// Parâmetros de geração automática
const params = ref({
  weights: {
    quantity: 0.30,
    value: 0.30,
    margin: 0.40,
  },
  targetStock: {
    serviceLevel: {
      A: 0.70,
      B: 0.80,
      C: 0.90
    },
    coverageDays: {
      A: 2,
      B: 5,
      C: 7
    }
  },
  filters: {
    usageStatus: 'unused',
    includeDimensionless: false
  },
  useZones: false // Usar zonas configuradas na distribuição
});

// Watchers
watch(() => props.open, (newValue) => {
  if (newValue && activeTab.value === 'zones') {
    // Carregar zonas existentes quando o modal for aberto na aba de zonas
    loadZoneConfiguration();
  }
});

watch(activeTab, (newTab) => {
  if (newTab === 'zones' && props.open) {
    // Carregar zonas quando alternar para a aba de zonas
    loadZoneConfiguration();
  }
});

// Métodos
const closeModal = () => {
  isOpen.value = false;
};

const handleZonesUpdate = (zones: Zone[]) => {
  zoneConfiguration.value = zones;
};

const saveZoneConfiguration = async () => {
  const gondola = editorStore.getCurrentGondola;
  if (!gondola) {
    alert('Nenhuma gôndola selecionada.');
    return;
  }

  if (zoneConfiguration.value.length === 0) {
    alert('Nenhuma zona configurada para salvar.');
    return;
  }

  isExecuting.value = true;

  try {
    // Usar apiService para garantir autenticação correta
    const { apiService } = await import('@plannerate/services');
    
    const data = await apiService.post(`/gondolas/${gondola.id}/zones`, {
      zones: zoneConfiguration.value.map((zone) => ({
        name: zone.name,
        shelf_indexes: zone.shelf_indexes,
        performance_multiplier: zone.performance_multiplier,
        rules: zone.rules,
      })),
    });

    console.log('Zonas salvas com sucesso:', data);
    
    const zonesText = zoneConfiguration.value.length === 1 ? 'zona' : 'zonas';
    alert(`✅ Configuração de ${zoneConfiguration.value.length} ${zonesText} salva com sucesso!\n\n` +
          `Esta configuração será usada na próxima geração automática do planograma.`);
    
    closeModal();
  } catch (error: any) {
    console.error('Erro ao salvar configuração de zonas:', error);
    const errorMessage = error?.message || error?.response?.data?.message || 'Erro desconhecido';
    alert('❌ Erro ao salvar configuração: ' + errorMessage);
  } finally {
    isExecuting.value = false;
  }
};

const loadZoneConfiguration = async () => {
  const gondola = editorStore.getCurrentGondola;
  if (!gondola) {
    return;
  }

  try {
    // Usar apiService para garantir autenticação correta
    const { apiService } = await import('@plannerate/services');
    
    const data = await apiService.get(`/gondolas/${gondola.id}/zones`);

    if (data.data && Array.isArray(data.data) && data.data.length > 0) {
      zoneConfiguration.value = data.data;
      console.log('Zonas carregadas:', data.data);
    }
  } catch (error: any) {
    console.error('Erro ao carregar configuração de zonas:', error);
    // Não mostrar erro ao usuário, apenas manter configuração vazia
  }
};

const executeAutoGeneration = async () => {
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
  const totalWeight = params.value.weights.quantity + 
                      params.value.weights.value + 
                      params.value.weights.margin;
  
  if (Math.abs(totalWeight - 1.0) > 0.01) {
    alert('A soma dos pesos deve ser igual a 1.0');
    return;
  }

  isExecuting.value = true;

  try {
    // Usar apiService para garantir que o CSRF token seja enviado
    const { apiService } = await import('@plannerate/services');
    
    const result = await apiService.post('/analysis/hierarchical-distribution', {
      gondola_id: gondola.id,
      planogram: editorStore.currentState.id,
      products: [],
      weights: params.value.weights,
      targetStock: params.value.targetStock,
      filters: params.value.filters,
      useZones: params.value.useZones, // Parâmetro para habilitar/desabilitar zonas
      storeId: editorStore.currentState.store_id ? parseInt(editorStore.currentState.store_id) : undefined
    });

    if (result.success) {
      alert(`✅ Distribuição concluída!\n\n` +
            `Total: ${result.data.placed_products}/${result.data.total_products} produtos\n` +
            `Falhas: ${result.data.failed_products}\n\n` +
            `A página será recarregada para exibir as mudanças.`);
      
      // Fechar modal e recarregar
      closeModal();
      window.location.reload();
    } else {
      alert('❌ Erro na distribuição: ' + result.message);
    }
  } catch (error: any) {
    console.error('Erro ao executar distribuição hierárquica:', error);
    alert('❌ Erro ao executar distribuição: ' + (error?.message || 'Erro desconhecido'));
  } finally {
    isExecuting.value = false;
  }
};
</script>

