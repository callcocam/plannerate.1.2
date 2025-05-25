<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="max-w-4xl">
      <DialogHeader>
        <DialogTitle>Resultados da Matriz BCG</DialogTitle>
        <DialogDescription>
          Análise de produtos baseada na participação de mercado e taxa de crescimento
        </DialogDescription>
      </DialogHeader>

      <div class="mt-4">
        <!-- Filtros -->
        <div class="flex gap-4 mb-4">
          <Select v-model="selectedCategory" class="w-48">
            <SelectTrigger>
              <SelectValue placeholder="Filtrar por categoria" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="">Todas as categorias</SelectItem>
              <SelectItem v-for="category in categories" :key="category" :value="category">
                {{ category }}
              </SelectItem>
            </SelectContent>
          </Select>

          <Select v-model="selectedClassification" class="w-48">
            <SelectTrigger>
              <SelectValue placeholder="Filtrar por classificação" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="">Todas as classificações</SelectItem>
              <SelectItem value="Alto valor - manutenção">Alto valor - manutenção</SelectItem>
              <SelectItem value="Incentivo - volume">Incentivo - volume</SelectItem>
              <SelectItem value="Incentivo - lucro">Incentivo - lucro</SelectItem>
              <SelectItem value="Baixo valor - descontinuar">Baixo valor - descontinuar</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <!-- Tabela de Resultados -->
        <div class="border rounded-lg">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>EAN</TableHead>
                <TableHead>Descrição</TableHead>
                <TableHead>Categoria</TableHead>
                <TableHead>EIXO Y(Vertical)</TableHead>
                <TableHead>EIXO X(Horizontal)</TableHead>
                <TableHead>Classificação BCG</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="item in filteredResults" :key="item.ean">
                <TableCell>{{ item.ean }}</TableCell>
                <TableCell>{{ item.description }}</TableCell>
                <TableCell>{{ item.category }}</TableCell>
                <TableCell>{{ item.yValue }}%</TableCell>
                <TableCell>{{ item.xValue }}%</TableCell>
                <TableCell>
                  <span
                    class="px-2 py-1 rounded-full text-xs font-medium"
                    :class="getClassificationClass(item.classification)"
                  >
                    {{ getClassificationLabel(item.classification) }}
                  </span>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>

        <!-- Legenda -->
        <div class="mt-4 flex gap-4">
          <div v-for="(label, classification) in classificationLabels" :key="classification" class="flex items-center gap-2">
            <div
              class="w-3 h-3 rounded-full"
              :class="getClassificationClass(classification)"
            ></div>
            <span class="text-sm">{{ label }}</span>
          </div>
        </div>
      </div>

      <DialogFooter class="mt-6">
        <Button variant="outline" @click="$emit('update:open', false)">
          Fechar
        </Button>
        <Button variant="default" @click="exportResults">
          Exportar Resultados
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { useBCGResultStore } from '@plannerate/store/editor/bcgResult';
import type { BCGClassification } from '@plannerate/composables/useBCGMatrix';

const props = defineProps<{
  open: boolean;
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
}>();

const bcgResultStore = useBCGResultStore();
const selectedCategory = ref('');
const selectedClassification = ref('');

const classificationLabels: Record<BCGClassification, string> = {
  'Alto valor - manutenção': 'Alto valor - manutenção',
  'Incentivo - volume': 'Incentivo - volume',
  'Incentivo - lucro': 'Incentivo - lucro',
  'Baixo valor - descontinuar': 'Baixo valor - descontinuar'
};

const categories = computed(() => {
  if (!bcgResultStore.result) return [];
  return [...new Set(bcgResultStore.result.map(item => item.category))];
});

const filteredResults = computed(() => {
  if (!bcgResultStore.result) return [];
  
  return bcgResultStore.result.filter(item => {
    const categoryMatch = !selectedCategory.value || item.category === selectedCategory.value;
    const classificationMatch = !selectedClassification.value || item.classification === selectedClassification.value;
    return categoryMatch && classificationMatch;
  });
});

const getClassificationClass = (classification: BCGClassification) => {
  const classes: Record<BCGClassification, string> = {
    'Alto valor - manutenção': 'bg-green-100 text-green-800',
    'Incentivo - volume': 'bg-blue-100 text-blue-800',
    'Incentivo - lucro': 'bg-purple-100 text-purple-800',
    'Baixo valor - descontinuar': 'bg-red-100 text-red-800'
  };
  return classes[classification] || '';
};

const getClassificationLabel = (classification: BCGClassification) => {
  return classificationLabels[classification] || classification;
};

const exportResults = () => {
  // Implementar lógica de exportação
  console.log('Exportando resultados...');
};
</script> 