<template>
  <div class="zone-editor flex flex-col h-full">
    <!-- Header com instruções -->
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 rounded-lg p-2 mb-3">
      <h4 class="font-medium text-sm mb-1">✨ Como usar:</h4>
      <ul class="text-xs text-gray-700 dark:text-gray-300 space-y-0.5">
        <li>• Selecione as prateleiras desejadas (ex: 1 e 2)</li>
        <li>• Clique em "Criar Zona" no rodapé</li>
        <li>• Repita para criar mais zonas (ex: 3 e 4)</li>
        <li>• Ou use os templates rápidos para configuração automática</li>
      </ul>
    </div>

    <!-- Templates de Zonas Predefinidos (MOVIDO PARA O TOPO) -->
    <div class="border rounded-lg p-3 mb-3">
      <h4 class="font-medium text-sm mb-3">⚡ Templates Rápidos</h4>
      <div class="grid grid-cols-2 gap-2">
        <Button variant="outline" size="sm" @click="applyTemplate('premium')">
          🏆 Zona Premium
        </Button>
        <Button variant="outline" size="sm" @click="applyTemplate('combate')">
          💰 Zona Combate
        </Button>
        <Button variant="outline" size="sm" @click="applyTemplate('complementar')">
          🔗 Complementar
        </Button>
        <Button variant="outline" size="sm" @click="applyTemplate('auto')">
          🤖 Auto (3 zonas)
        </Button>
      </div>
    </div>

    <!-- Visualização da Gôndola (com select de módulo) -->
    <div class="flex-1 border rounded-lg p-3 bg-gray-50 dark:bg-gray-800 flex flex-col">
      <div class="flex items-center justify-between mb-2">
        <h4 class="font-medium text-sm">Estrutura da Gôndola</h4>
        <span v-if="selectedShelves.length > 0" class="text-xs px-2 py-1 rounded-full bg-blue-500 text-white font-medium">
          {{ selectedShelves.length }} selecionada{{ selectedShelves.length === 1 ? '' : 's' }}
        </span>
      </div>
      
      <!-- Toggle para aplicar em todos os módulos -->
      <div class="mb-3 p-2 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-md">
        <div class="flex items-center space-x-2">
          <input 
            id="apply-all-modules"
            type="checkbox" 
            v-model="applyToAllModules"
            class="h-4 w-4 border-gray-300 text-primary focus:ring-2 focus:ring-primary rounded"
          />
          <label for="apply-all-modules" class="text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer">
            🌐 Aplicar em todos os módulos
          </label>
        </div>
        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 ml-6">
          Se marcado, será criada uma única zona que inclui as prateleiras equivalentes de todos os módulos
        </p>
      </div>

      <!-- Select de Módulo -->
      <div class="mb-3" :class="{ 'opacity-50': applyToAllModules }">
        <label class="text-xs text-gray-600 dark:text-gray-400 block mb-2">
          Selecione o Módulo:
          <span v-if="applyToAllModules" class="text-yellow-600 dark:text-yellow-400 ml-1">
            (será aplicado em todos)
          </span>
        </label>
        <select 
          v-model="selectedModuleIndex"
          :disabled="applyToAllModules"
          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-sm disabled:cursor-not-allowed"
        >
          <option 
            v-for="(module, index) in modulesStructure" 
            :key="index" 
            :value="index"
          >
            📦 Módulo {{ index + 1 }} - {{ module.shelves.length }} {{ module.shelves.length === 1 ? 'prateleira' : 'prateleiras' }}
          </option>
        </select>
      </div>

      <!-- Prateleiras do Módulo Selecionado -->
      <div class="flex-1 overflow-y-auto">
        <div v-if="currentModule" class="space-y-2">
          <div
            v-for="shelf in currentModule.shelves"
            :key="shelf.globalIndex"
            class="shelf-row cursor-pointer p-3 rounded-md border-2 transition-all bg-white dark:bg-gray-700"
            :class="getShelfClasses(shelf.globalIndex)"
            @click="toggleShelfSelection(shelf.globalIndex)"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div class="text-sm font-medium">Prateleira {{ shelf.localIndex + 1 }}</div>
                <div v-if="getZoneForShelf(shelf.globalIndex)" class="text-xs px-2 py-1 rounded bg-blue-100 dark:bg-blue-800 font-medium">
                  {{ getZoneForShelf(shelf.globalIndex)?.name }}
                </div>
              </div>
              <div class="text-xs" :class="getZoneForShelf(shelf.globalIndex) ? 'text-gray-400' : 'text-gray-600 dark:text-gray-400'">
                {{ isShelfSelected(shelf.globalIndex) ? '✓ Selecionada' : getZoneForShelf(shelf.globalIndex) ? '🔒 Em zona' : 'Clique para selecionar' }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer Fixo com botão de criar zona -->
    <div class="mt-3 pt-3 border-t bg-white dark:bg-gray-800 sticky bottom-0">
      <Button 
        v-if="selectedShelves.length > 0"
        variant="default" 
        size="sm" 
        @click="createZoneFromSelection"
        class="w-full"
      >
        <span v-if="applyToAllModules">
          🌐 Criar Zona Única para Todos os Módulos ({{ modulesStructure.length }} módulos)
        </span>
        <span v-else>
          🎯 Criar Zona com {{ selectedShelves.length }} {{ selectedShelves.length === 1 ? 'prateleira selecionada' : 'prateleiras selecionadas' }}
        </span>
      </Button>
      <div v-else class="text-center text-sm text-gray-500 py-2">
        Selecione prateleiras acima para criar uma zona
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Button } from '@plannerate/components/ui/button';

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
  shelfCount: number;
  zones: Zone[];
  modules?: Array<{ shelves: Array<any> }>; // Estrutura de módulos da gôndola
}>();

// Emits
const emit = defineEmits<{
  (e: 'update:zones', zones: Zone[]): void;
  (e: 'zone-created', zone: Zone): void;
}>();

// State
const selectedShelves = ref<number[]>([]);
const selectedModuleIndex = ref<number>(0); // Módulo selecionado no select
const applyToAllModules = ref<boolean>(false); // Toggle para aplicar em todos os módulos

// Computed: Estrutura de módulos com índices globais e locais
const modulesStructure = computed(() => {
  if (props.modules && props.modules.length > 0) {
    // Se recebeu estrutura de módulos, usar ela
    let globalIndex = 0;
    return props.modules.map(module => {
      const shelves = module.shelves.map((shelf, localIndex) => {
        const shelfData = {
          globalIndex: globalIndex++,
          localIndex,
          ...shelf
        };
        return shelfData;
      });
      return { shelves };
    });
  } else {
    // Fallback: criar estrutura plana com todas as prateleiras em um módulo
    const shelves = Array.from({ length: props.shelfCount }, (_, i) => ({
      globalIndex: i,
      localIndex: i
    }));
    return [{ shelves }];
  }
});

// Computed: Módulo atualmente selecionado
const currentModule = computed(() => {
  return modulesStructure.value[selectedModuleIndex.value] || null;
});

// Computed
const isShelfSelected = (index: number): boolean => {
  return selectedShelves.value.includes(index);
};

const getShelfClasses = (index: number): string[] => {
  const zone = getZoneForShelf(index);
  const classes: string[] = [];
  
  if (isShelfSelected(index)) {
    classes.push('border-blue-500 bg-blue-100 dark:bg-blue-900 ring-2 ring-blue-400');
  } else if (zone) {
    // Zona já existe - indicar visualmente que não pode ser selecionada novamente
    const multiplier = zone.performance_multiplier;
    if (multiplier >= 1.0) {
      classes.push('border-green-500 bg-green-50 dark:bg-green-900/20 opacity-80');
    } else if (multiplier >= 0.7) {
      classes.push('border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20 opacity-80');
    } else {
      classes.push('border-red-500 bg-red-50 dark:bg-red-900/20 opacity-80');
    }
  } else {
    classes.push('border-gray-300 hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/10 bg-white dark:bg-gray-700 transition-colors');
  }
  
  return classes;
};

const getZoneForShelf = (shelfIndex: number): Zone | null => {
  return props.zones.find(z => z.shelf_indexes.includes(shelfIndex)) || null;
};

// Métodos
const toggleShelfSelection = (index: number) => {
  const existingZone = getZoneForShelf(index);
  if (existingZone) {
    // Se já está em uma zona, perguntar se quer remover
    if (confirm(`Esta prateleira já pertence à zona "${existingZone.name}". Deseja removê-la?`)) {
      removeShelfFromZone(index, existingZone);
    }
    return;
  }
  
  const idx = selectedShelves.value.indexOf(index);
  if (idx > -1) {
    selectedShelves.value.splice(idx, 1);
  } else {
    selectedShelves.value.push(index);
  }
  
  // Ordenar para melhor visualização
  selectedShelves.value.sort((a, b) => a - b);
};

/**
 * Extrai os índices locais das prateleiras selecionadas no módulo atual
 */
const getLocalIndexesFromSelected = (): number[] => {
  const currentMod = modulesStructure.value[selectedModuleIndex.value];
  if (!currentMod) return [];
  
  // Encontrar índices locais das prateleiras selecionadas
  const localIndexes: number[] = [];
  selectedShelves.value.forEach(globalIndex => {
    const shelf = currentMod.shelves.find((s: any) => s.globalIndex === globalIndex);
    if (shelf) {
      localIndexes.push(shelf.localIndex);
    }
  });
  
  return localIndexes.sort((a, b) => a - b);
};

/**
 * Cria uma única zona que se aplica a todos os módulos
 * Coleta todos os índices globais equivalentes de todos os módulos em uma única zona
 */
const createZonesForAllModules = (localIndexes: number[], baseZoneName: string, rules: ZoneRules, multiplier: number): Zone[] => {
  // Coletar TODOS os índices globais de TODOS os módulos
  const allGlobalIndexes: number[] = [];
  
  modulesStructure.value.forEach((module) => {
    // Para cada índice local, buscar o índice global equivalente neste módulo
    localIndexes.forEach(localIdx => {
      if (module.shelves[localIdx]) {
        allGlobalIndexes.push(module.shelves[localIdx].globalIndex);
      }
    });
  });
  
  // Ordenar os índices para melhor organização
  allGlobalIndexes.sort((a, b) => a - b);
  
  if (allGlobalIndexes.length === 0) {
    return [];
  }
  
  // Criar UMA ÚNICA zona com todos os índices
  const zoneName = modulesStructure.value.length > 1 
    ? `${baseZoneName} (Todos os Módulos)`
    : baseZoneName;
  
  return [{
    id: `zone-${Date.now()}`,
    name: zoneName,
    shelf_indexes: allGlobalIndexes,
    performance_multiplier: multiplier,
    rules: { ...rules }
  }];
};

const createZoneFromSelection = () => {
  if (selectedShelves.value.length === 0) return;
  
  const shelvesText = selectedShelves.value.length === 1 ? 'prateleira' : 'prateleiras';
  const baseZoneName = `Zona ${props.zones.length + 1}`;
  
  const defaultRules: ZoneRules = {
    priority: 'high_margin',
    exposure_type: 'vertical',
    abc_filter: [],
  };
  
  let newZones: Zone[] = [];
  
  if (applyToAllModules.value && modulesStructure.value.length > 1) {
    // Aplicar em todos os módulos
    const localIndexes = getLocalIndexesFromSelected();
    
    if (localIndexes.length === 0) {
      alert('❌ Erro ao identificar prateleiras locais. Tente novamente.');
      return;
    }
    
    // Criar uma única zona que se aplica a todos os módulos
    newZones = createZonesForAllModules(localIndexes, baseZoneName, defaultRules, 1.0);
    
    if (newZones.length === 0) {
      alert('❌ Não foi possível criar zona em todos os módulos. Verifique se os módulos têm o mesmo número de prateleiras.');
      return;
    }
    
    const updatedZones = [...props.zones, ...newZones];
    emit('update:zones', updatedZones);
    
    // Emitir a zona criada para seleção automática
    if (newZones.length > 0) {
      emit('zone-created', newZones[0]);
    }
    
    const totalShelvesInZone = newZones[0]?.shelf_indexes.length || 0;
    console.log(`✅ Zona "${newZones[0].name}" criada com ${totalShelvesInZone} prateleiras (aplicada em ${modulesStructure.value.length} módulos)`);
  } else {
    // Criar apenas no módulo atual
    const newZone: Zone = {
      id: `zone-${Date.now()}`,
      name: baseZoneName,
      shelf_indexes: [...selectedShelves.value],
      performance_multiplier: 1.0,
      rules: defaultRules
    };
    
    newZones = [newZone];
    const updatedZones = [...props.zones, newZone];
    emit('update:zones', updatedZones);
    emit('zone-created', newZone);
    
    console.log(`✅ ${baseZoneName} criada com ${selectedShelves.value.length} ${shelvesText}`);
  }
  
  // Limpar seleção para permitir criar nova zona
  selectedShelves.value = [];
};

const removeShelfFromZone = (shelfIndex: number, zone: Zone) => {
  const updatedZone = {
    ...zone,
    shelf_indexes: zone.shelf_indexes.filter(i => i !== shelfIndex)
  };
  
  let updatedZones = props.zones.map(z => z.id === zone.id ? updatedZone : z);
  
  // Se a zona ficar vazia, remover
  if (updatedZone.shelf_indexes.length === 0) {
    updatedZones = updatedZones.filter(z => z.id !== zone.id);
  }
  
  emit('update:zones', updatedZones);
};

/**
 * Aplica template em todos os módulos ou apenas no atual
 */
const applyTemplate = (templateType: string) => {
  // Verificar se todos os módulos têm o mesmo número de prateleiras
  const shelvesPerModule = modulesStructure.value.length > 0 
    ? modulesStructure.value[0].shelves.length 
    : props.shelfCount;
  
  const allModulesSameSize = modulesStructure.value.every(
    mod => mod.shelves.length === shelvesPerModule
  );
  
  // Se não tiver múltiplos módulos ou módulos com tamanhos diferentes, aplicar normalmente
  if (!applyToAllModules.value || modulesStructure.value.length <= 1 || !allModulesSameSize) {
    applyTemplateToCurrent(props.shelfCount, templateType);
    return;
  }
  
  // Aplicar template em todos os módulos
  applyTemplateToAllModules(templateType, shelvesPerModule);
};

/**
 * Aplica template apenas no módulo atual (ou em toda gôndola se for estrutura plana)
 */
const applyTemplateToCurrent = (totalShelves: number, templateType: string) => {
  let newZones: Zone[] = [];
  
  switch (templateType) {
    case 'premium':
      const midStart = Math.floor(totalShelves / 3);
      const midEnd = Math.floor((totalShelves * 2) / 3);
      newZones = [{
        id: `zone-premium-${Date.now()}`,
        name: '🏆 Premium - Altura dos Olhos',
        shelf_indexes: Array.from({ length: midEnd - midStart }, (_, i) => midStart + i),
        performance_multiplier: 1.0,
        rules: {
          priority: 'high_margin',
          exposure_type: 'vertical',
          abc_filter: ['A', 'B'],
          min_margin_percent: 30
        }
      }];
      break;
      
    case 'combate':
      const bottomCount = Math.ceil(totalShelves / 4);
      newZones = [{
        id: `zone-combate-${Date.now()}`,
        name: '💰 Combate - Base',
        shelf_indexes: Array.from({ length: bottomCount }, (_, i) => totalShelves - bottomCount + i),
        performance_multiplier: 0.5,
        rules: {
          priority: 'low_price',
          exposure_type: 'horizontal',
          abc_filter: ['C'],
          max_margin_percent: 20
        }
      }];
      break;
      
    case 'complementar':
      const topCount = Math.ceil(totalShelves / 5);
      newZones = [{
        id: `zone-complementar-${Date.now()}`,
        name: '🔗 Complementar - Topo',
        shelf_indexes: Array.from({ length: topCount }, (_, i) => i),
        performance_multiplier: 0.6,
        rules: {
          priority: 'complementary',
          exposure_type: 'horizontal',
        }
      }];
      break;
      
    case 'auto':
      const third = Math.floor(totalShelves / 3);
      newZones = [
        {
          id: `zone-top-${Date.now()}`,
          name: '🔗 Topo - Complementares',
          shelf_indexes: Array.from({ length: third }, (_, i) => i),
          performance_multiplier: 0.6,
          rules: {
            priority: 'complementary',
            exposure_type: 'horizontal'
          }
        },
        {
          id: `zone-mid-${Date.now()}`,
          name: '🏆 Meio - Premium',
          shelf_indexes: Array.from({ length: third }, (_, i) => third + i),
          performance_multiplier: 1.0,
          rules: {
            priority: 'high_margin',
            exposure_type: 'vertical',
            abc_filter: ['A', 'B'],
            min_margin_percent: 25
          }
        },
        {
          id: `zone-bottom-${Date.now()}`,
          name: '💰 Base - Combate',
          shelf_indexes: Array.from({ length: totalShelves - (third * 2) }, (_, i) => third * 2 + i),
          performance_multiplier: 0.5,
          rules: {
            priority: 'low_price',
            exposure_type: 'horizontal',
            abc_filter: ['C'],
            max_margin_percent: 20
          }
        }
      ];
      break;
  }
  
  emit('update:zones', newZones);
};

/**
 * Aplica template em todos os módulos baseado em índices locais
 */
const applyTemplateToAllModules = (templateType: string, shelvesPerModule: number) => {
  let templateZones: Array<{ name: string; localIndexes: number[]; multiplier: number; rules: ZoneRules }> = [];
  
  switch (templateType) {
    case 'premium':
      const midStart = Math.floor(shelvesPerModule / 3);
      const midEnd = Math.floor((shelvesPerModule * 2) / 3);
      templateZones = [{
        name: '🏆 Premium - Altura dos Olhos',
        localIndexes: Array.from({ length: midEnd - midStart }, (_, i) => midStart + i),
        multiplier: 1.0,
        rules: {
          priority: 'high_margin',
          exposure_type: 'vertical',
          abc_filter: ['A', 'B'],
          min_margin_percent: 30
        }
      }];
      break;
      
    case 'combate':
      const bottomCount = Math.ceil(shelvesPerModule / 4);
      templateZones = [{
        name: '💰 Combate - Base',
        localIndexes: Array.from({ length: bottomCount }, (_, i) => shelvesPerModule - bottomCount + i),
        multiplier: 0.5,
        rules: {
          priority: 'low_price',
          exposure_type: 'horizontal',
          abc_filter: ['C'],
          max_margin_percent: 20
        }
      }];
      break;
      
    case 'complementar':
      const topCount = Math.ceil(shelvesPerModule / 5);
      templateZones = [{
        name: '🔗 Complementar - Topo',
        localIndexes: Array.from({ length: topCount }, (_, i) => i),
        multiplier: 0.6,
        rules: {
          priority: 'complementary',
          exposure_type: 'horizontal',
        }
      }];
      break;
      
    case 'auto':
      const third = Math.floor(shelvesPerModule / 3);
      templateZones = [
        {
          name: '🔗 Topo - Complementares',
          localIndexes: Array.from({ length: third }, (_, i) => i),
          multiplier: 0.6,
          rules: {
            priority: 'complementary',
            exposure_type: 'horizontal'
          }
        },
        {
          name: '🏆 Meio - Premium',
          localIndexes: Array.from({ length: third }, (_, i) => third + i),
          multiplier: 1.0,
          rules: {
            priority: 'high_margin',
            exposure_type: 'vertical',
            abc_filter: ['A', 'B'],
            min_margin_percent: 25
          }
        },
        {
          name: '💰 Base - Combate',
          localIndexes: Array.from({ length: shelvesPerModule - (third * 2) }, (_, i) => third * 2 + i),
          multiplier: 0.5,
          rules: {
            priority: 'low_price',
            exposure_type: 'horizontal',
            abc_filter: ['C'],
            max_margin_percent: 20
          }
        }
      ];
      break;
  }
  
  // Criar zonas para cada template (uma por template que se aplica a todos os módulos)
  const allZones: Zone[] = [];
  templateZones.forEach(templateZone => {
    const zonesForTemplate = createZonesForAllModules(
      templateZone.localIndexes,
      templateZone.name,
      templateZone.rules,
      templateZone.multiplier
    );
    allZones.push(...zonesForTemplate);
  });
  
  emit('update:zones', allZones);
  const totalShelves = allZones.reduce((sum, zone) => sum + zone.shelf_indexes.length, 0);
  console.log(`✅ Template "${templateType}" aplicado em todos os módulos: ${allZones.length} zona(s) criada(s) com ${totalShelves} prateleiras total`);
};
</script>

<style scoped>
.shelf-row {
  transition: all 0.2s ease-in-out;
}

.shelf-row:hover {
  transform: translateX(4px);
}
</style>

