<template>
  <Dialog v-model:open="isOpen">
    <DialogContent class="max-w-2xl z-[1000]">
      <DialogHeader>
        <DialogTitle>Gerar Planograma Automaticamente</DialogTitle>
        <DialogDescription>
          Configure os parâmetros para distribuição automática de produtos ou defina zonas personalizadas.
        </DialogDescription>
      </DialogHeader>

      <!-- Sistema de Abas -->
      <Tabs v-model="activeTab" class="w-full">
        <TabsList class="grid w-full grid-cols-2">
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
        <TabsContent value="zones" class="space-y-4 mt-4">
          <div class="text-center py-8">
            <div class="mb-4 text-4xl">📍</div>
            <h3 class="text-lg font-medium mb-2">Sistema de Zonas</h3>
            <p class="text-sm text-gray-600 mb-4">
              Configure zonas de performance para distribuição inteligente baseada em altura e visibilidade.
            </p>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-left">
              <h4 class="font-medium text-sm mb-2">📋 Funcionalidades Futuras:</h4>
              <ul class="text-sm text-gray-700 space-y-1">
                <li>• <strong>Zona Premium</strong> (altura dos olhos): Marcas de referência, maior margem</li>
                <li>• <strong>Zona Topo</strong>: Produtos complementares, cross-sell</li>
                <li>• <strong>Zona Base</strong>: Preços combate, baixa margem</li>
                <li>• <strong>Exposição Vertical/Horizontal</strong>: Por zona configurável</li>
                <li>• <strong>Consideração de Fluxo</strong>: Direção do cliente na loja</li>
              </ul>
            </div>
            <Button variant="outline" class="mt-4" disabled>
              Em desenvolvimento
            </Button>
          </div>
        </TabsContent>
      </Tabs>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
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

// Computed
const isOpen = computed({
  get: () => props.open,
  set: (value) => emit('update:open', value)
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
  }
});

// Métodos
const closeModal = () => {
  isOpen.value = false;
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

