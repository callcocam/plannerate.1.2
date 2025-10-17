<template>
  <div class="zone-configuration">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
      <!-- Coluna Esquerda: Editor Visual -->
      <div class="space-y-3">
        <ZoneEditor 
          :shelf-count="shelfCount"
          :zones="localZones"
          :modules="modules"
          @update:zones="handleZonesUpdate"
          @zone-created="handleZoneCreated"
        />
      </div>

      <!-- Coluna Direita: Lista de Zonas e Configuração -->
      <div class="space-y-3">
        <!-- Lista de Zonas -->
        <div class="border rounded-lg p-3">
          <div class="flex items-center justify-between mb-2">
            <h4 class="font-medium text-sm">Zonas Configuradas</h4>
            <span class="text-xs text-gray-500">{{ localZones.length }} {{ localZones.length === 1 ? 'zona' : 'zonas' }}</span>
          </div>

          <div v-if="localZones.length === 0" class="text-center py-8 text-gray-500">
            <div class="text-4xl mb-2">📍</div>
            <p class="text-sm">Nenhuma zona configurada</p>
            <p class="text-xs mt-1">Use o editor ao lado ou templates rápidos</p>
          </div>

          <div v-else class="space-y-2">
            <ZonePreview
              v-for="zone in localZones"
              :key="zone.id"
              :zone="zone"
              :is-selected="selectedZone?.id === zone.id"
              @select="selectZone"
            />
          </div>
        </div>

        <!-- Configuração da Zona Selecionada -->
        <div v-if="selectedZone" class="border rounded-lg p-3">
          <div class="flex items-center justify-between mb-2">
            <h4 class="font-medium text-sm">Configurar Zona</h4>
            <Button variant="ghost" size="sm" @click="deleteZone(selectedZone.id)">
              🗑️ Remover
            </Button>
          </div>

          <ZoneRuleForm
            :zone="selectedZone"
            @update:zone="handleZoneRuleUpdate"
          />
        </div>

        <!-- Mensagem se nenhuma zona selecionada -->
        <div v-else-if="localZones.length > 0" class="border rounded-lg p-4 text-center py-8 text-gray-500">
          <div class="text-4xl mb-2">👆</div>
          <p class="text-sm">Selecione uma zona acima para configurar</p>
        </div>
      </div>
    </div>

    <!-- Rodapé com resumo -->
    <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
      <div class="grid grid-cols-3 gap-4 text-center">
        <div>
          <div class="text-2xl font-bold text-blue-600">{{ localZones.length }}</div>
          <div class="text-xs text-gray-500">Zonas</div>
        </div>
        <div>
          <div class="text-2xl font-bold text-green-600">{{ totalShelvesInZones }}</div>
          <div class="text-xs text-gray-500">Prateleiras configuradas</div>
        </div>
        <div>
          <div class="text-2xl font-bold" :class="coverageColor">
            {{ coveragePercent }}%
          </div>
          <div class="text-xs text-gray-500">Cobertura</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Button } from '@plannerate/components/ui/button';
import ZoneEditor from './ZoneEditor.vue';
import ZonePreview from './ZonePreview.vue';
import ZoneRuleForm from './ZoneRuleForm.vue';

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
  zones?: Zone[];
  modules?: Array<{ id: string; shelves: Array<any> }>;
}>();

// Emits
const emit = defineEmits<{
  (e: 'update:zones', zones: Zone[]): void;
}>();

// State
const localZones = ref<Zone[]>(props.zones || []);
const selectedZone = ref<Zone | null>(null);

// Watch props
watch(() => props.zones, (newZones) => {
  if (newZones) {
    localZones.value = [...newZones];
  }
}, { deep: true });

// Computed
const totalShelvesInZones = computed(() => {
  const uniqueShelves = new Set<number>();
  localZones.value.forEach(zone => {
    zone.shelf_indexes.forEach(index => uniqueShelves.add(index));
  });
  return uniqueShelves.size;
});

const coveragePercent = computed(() => {
  if (props.shelfCount === 0) return 0;
  return Math.round((totalShelvesInZones.value / props.shelfCount) * 100);
});

const coverageColor = computed(() => {
  const percent = coveragePercent.value;
  if (percent >= 80) return 'text-green-600';
  if (percent >= 50) return 'text-yellow-600';
  return 'text-red-600';
});

// Métodos
const handleZonesUpdate = (zones: Zone[]) => {
  localZones.value = zones;
  emit('update:zones', zones);
  
  // Se a zona selecionada foi removida, desselecionar
  if (selectedZone.value && !zones.find(z => z.id === selectedZone.value?.id)) {
    selectedZone.value = null;
  }
};

const handleZoneCreated = (zone: Zone) => {
  // Selecionar automaticamente a zona recém-criada
  selectedZone.value = zone;
};

const selectZone = (zone: Zone) => {
  selectedZone.value = zone;
};

const handleZoneRuleUpdate = (updatedZone: Zone) => {
  const index = localZones.value.findIndex(z => z.id === updatedZone.id);
  if (index !== -1) {
    localZones.value[index] = updatedZone;
    selectedZone.value = updatedZone;
    emit('update:zones', localZones.value);
  }
};

const deleteZone = (zoneId: string) => {
  if (confirm('Tem certeza que deseja remover esta zona?')) {
    localZones.value = localZones.value.filter(z => z.id !== zoneId);
    selectedZone.value = null;
    emit('update:zones', localZones.value);
  }
};
</script>

<style scoped>
.zone-configuration {
  scrollbar-width: thin;
}
</style>

