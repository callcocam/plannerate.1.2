<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Loader2, CheckCircle2, XCircle } from 'lucide-vue-next';

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
  
  console.log('🔄 [QueueMonitor] updateJob chamado:', {
    jobId,
    status: data.status,
    totalJobs: totalJobs.value,
    completedJobs: completedJobs.value,
    isVisible: isVisible.value
  });
  
  if (data.status === 'processing') {
    totalJobs.value++;
    isVisible.value = true;
    currentProcessingJob.value = jobLabels[data.job_name] || data.job_name;
    console.log('🟢 [QueueMonitor] Card agora visível! Total jobs:', totalJobs.value);
  } else if (data.status === 'completed') {
    completedJobs.value++;
    console.log('✅ [QueueMonitor] Job completado! Progress:', completedJobs.value, '/', totalJobs.value);
  } else if (data.status === 'failed') {
    failedJobs.value++;
    console.log('❌ [QueueMonitor] Job falhou!');
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
      console.log('🗑️ [QueueMonitor] Removendo job:', jobId);
      activeJobs.value.delete(jobId);
      if (activeJobs.value.size === 0) {
        console.log('👋 [QueueMonitor] Todos jobs removidos, escondendo card em 2s...');
        setTimeout(() => {
          isVisible.value = false;
          resetCounters();
          console.log('🔄 [QueueMonitor] Contadores resetados');
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
  console.log('🎯 [QueueMonitor] Componente montado');
  
  // Aguardar Echo estar disponível (pode ser carregado pelo app principal)
  const checkEcho = () => {
    if (typeof window !== 'undefined' && window.Echo) { 
      
      const channel = window.Echo.channel('queue-activity'); 
      
      channel.listen('.queue.activity.updated', (data: any) => { 
        
        if (data.queue_name === 'planogramas') { 
          updateJob(data);
        } else {
          console.log('⏭️ [QueueMonitor] Ignorando fila:', data.queue_name);
        }
      });
      
      console.log('✅ [QueueMonitor] Listener configurado');
    } else {
      console.log('⏳ [QueueMonitor] Echo ainda não disponível, tentando novamente...');
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
    enter-active-class="transition ease-out duration-200"
    enter-from-class="opacity-0 translate-y-4"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition ease-in duration-150"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 translate-y-4"
  >
    <Card 
      v-if="isVisible" 
      class="fixed top-4 right-4 w-96 shadow-lg z-50 border-primary/20"
    >
      <CardContent class="p-4">
        <div class="space-y-3">
          <!-- Header com título e contador -->
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Loader2 v-if="completedJobs < totalJobs" class="h-4 w-4 animate-spin text-primary" />
              <CheckCircle2 v-else class="h-4 w-4 text-green-600" />
              <span class="font-semibold text-sm">Processando Planograma</span>
            </div>
            <Badge variant="outline" class="text-xs">
              {{ completedJobs }}/{{ totalJobs }}
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
          <div v-if="failedJobs > 0" class="flex items-center gap-2 text-xs text-destructive">
            <XCircle class="h-3 w-3" />
            <span>{{ failedJobs }} {{ failedJobs === 1 ? 'erro' : 'erros' }}</span>
          </div>
        </div>
      </CardContent>
    </Card>
  </Transition>
</template>
