<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Loader2, CheckCircle2, XCircle, ChevronDown, ChevronUp } from 'lucide-vue-next';

interface QueueJob {
  id: string;
  job_name: string;
  status: 'processing' | 'completed' | 'failed';
  metadata: {
    planogram_id?: string;
    planogram_name?: string;
    gondola_id?: string;
    section_id?: string;
    shelf_id?: string;
    error?: string;
  };
  timestamp: string;
}

const activeJobs = ref<Map<string, QueueJob>>(new Map());
const totalJobs = ref(0);
const completedJobs = ref(0);
const failedJobs = ref(0);
const isVisible = ref(false);
const isExpanded = ref(false);
const currentProcessingJob = ref<string>('');

const jobLabels: Record<string, string> = {
  'SavePlanogramMetadataJob': 'Metadados',
  'SaveGondolaJob': 'Gôndola',
  'SaveSectionJob': 'Seção',
  'SaveShelfJob': 'Prateleira'
};

const progressPercentage = computed(() => {
  if (totalJobs.value === 0) return 0;
  return Math.round((completedJobs.value / totalJobs.value) * 100);
});

const currentJobLabel = computed(() => {
  if (completedJobs.value === totalJobs.value && totalJobs.value > 0) {
    return 'Concluído com sucesso!';
  }
  if (currentProcessingJob.value) {
    return `Processando: ${currentProcessingJob.value}`;
  }
  return 'Aguardando...';
});

function updateJob(data: any) {
  const jobId = `${data.job_name}-${data.metadata.planogram_id || ''}-${data.metadata.gondola_id || ''}-${data.metadata.section_id || ''}-${data.metadata.shelf_id || ''}`;
  
  if (data.status === 'processing') {
    totalJobs.value++;
    isVisible.value = true;
    currentProcessingJob.value = jobLabels[data.job_name] || data.job_name;
  } else if (data.status === 'completed') {
    completedJobs.value++;
  } else if (data.status === 'failed') {
    failedJobs.value++;
  }

  activeJobs.value.set(jobId, {
    id: jobId,
    job_name: data.job_name,
    status: data.status,
    metadata: data.metadata,
    timestamp: data.timestamp
  });

  // Remover jobs concluídos após 3 segundos
  if (data.status === 'completed' || data.status === 'failed') {
    setTimeout(() => {
      activeJobs.value.delete(jobId);
      if (activeJobs.value.size === 0) {
        setTimeout(() => {
          isVisible.value = false;
          isExpanded.value = false;
          resetCounters();
        }, 2000);
      }
    }, 3000);
  }
}

function resetCounters() {
  totalJobs.value = 0;
  completedJobs.value = 0;
  failedJobs.value = 0;
  currentProcessingJob.value = '';
}

onMounted(() => {
  // Aguardar Echo estar disponível (pode ser carregado pelo app principal)
  const checkEcho = () => {
    if (typeof window !== 'undefined' && window.Echo) { 
      
      const channel = window.Echo.channel('queue-activity'); 
      
      channel.listen('.queue.activity.updated', (data: any) => { 
        
        if (data.queue_name === 'planogramas') { 
          updateJob(data);
        }
      });
      
    } else {
      setTimeout(checkEcho, 100); // Tentar novamente em 100ms
    }
  };
  
  checkEcho();
});

onUnmounted(() => { 
  if (typeof window !== 'undefined' && window.Echo) {
    window.Echo.leaveChannel('queue-activity'); 
  }
});
</script>

<template>
  <Transition
    enter-active-class="transition ease-out duration-300"
    enter-from-class="opacity-0 -translate-y-full"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition ease-in duration-200"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 -translate-y-full"
  >
    <div 
      v-if="isVisible" 
      class="fixed top-2 right-4 z-50"
    >
      <!-- Botão compacto sempre visível -->
      <Button 
        variant="secondary" 
        size="sm" 
        @click="isExpanded = !isExpanded"
        class="flex items-center gap-2 shadow-lg bg-background/95 backdrop-blur-sm border"
      >
        <Loader2 v-if="completedJobs < totalJobs" class="h-3 w-3 animate-spin text-primary" />
        <CheckCircle2 v-else class="h-3 w-3 text-green-600" />
        
        <span class="text-xs font-medium">
          {{ completedJobs }}/{{ totalJobs }}
        </span>
        
        <ChevronDown v-if="!isExpanded" class="h-3 w-3" />
        <ChevronUp v-else class="h-3 w-3" />
      </Button>

      <!-- Painel expandido (detalhes) -->
      <Transition
        enter-active-class="transition-all duration-200 ease-out"
        enter-from-class="max-h-0 opacity-0 scale-95"
        enter-to-class="max-h-48 opacity-100 scale-100"
        leave-active-class="transition-all duration-150 ease-in"
        leave-from-class="max-h-48 opacity-100 scale-100"
        leave-to-class="max-h-0 opacity-0 scale-95"
      >
        <div v-if="isExpanded" class="mt-2 w-80 bg-background/95 backdrop-blur-sm rounded-lg shadow-lg border overflow-hidden">
          <div class="p-4 space-y-3">
            <!-- Header -->
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <Loader2 v-if="completedJobs < totalJobs" class="h-4 w-4 animate-spin text-primary" />
                <CheckCircle2 v-else class="h-4 w-4 text-green-600" />
                <span class="font-medium text-sm">Processando Planograma</span>
              </div>
              <Badge variant="outline" class="text-xs">
                {{ progressPercentage }}%
              </Badge>
            </div>

            <!-- Barra de Progresso -->
            <div class="space-y-1">
              <div class="h-2 bg-muted rounded-full overflow-hidden">
                <div 
                  class="h-full bg-primary transition-all duration-300 ease-out"
                  :style="{ width: `${progressPercentage}%` }"
                />
              </div>
              <p class="text-xs text-muted-foreground">
                {{ currentJobLabel }}
              </p>
            </div>

            <!-- Mensagem de erro se houver -->
            <div v-if="failedJobs > 0" class="flex items-center gap-2 text-sm text-destructive">
              <XCircle class="h-4 w-4" />
              <span>{{ failedJobs }} {{ failedJobs === 1 ? 'erro' : 'erros' }} encontrado{{ failedJobs === 1 ? '' : 's' }}</span>
            </div>
          </div>
        </div>
      </Transition>
    </div>
  </Transition>
</template>
