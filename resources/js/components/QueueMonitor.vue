<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Loader2, CheckCircle2, XCircle, Clock } from 'lucide-vue-next';

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

const jobLabels: Record<string, string> = {
  'SavePlanogramMetadataJob': 'Metadados',
  'SaveGondolaJob': 'Gôndola',
  'SaveSectionJob': 'Seção',
  'SaveShelfJob': 'Prateleira'
};

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

  // Remover jobs concluídos após 5 segundos (aumentado para melhor visualização)
  if (data.status === 'completed' || data.status === 'failed') {
    setTimeout(() => {
      console.log('🗑️ [QueueMonitor] Removendo job:', jobId);
      activeJobs.value.delete(jobId);
      if (activeJobs.value.size === 0) {
        console.log('👋 [QueueMonitor] Todos jobs removidos, escondendo card em 3s...');
        setTimeout(() => {
          isVisible.value = false;
          resetCounters();
          console.log('🔄 [QueueMonitor] Contadores resetados');
        }, 3000);
      }
    }, 5000);
  }
}

function resetCounters() {
  totalJobs.value = 0;
  completedJobs.value = 0;
  failedJobs.value = 0;
}

onMounted(() => {
  console.log('🎯 [QueueMonitor] Componente montado');
  
  // Aguardar Echo estar disponível (pode ser carregado pelo app principal)
  const checkEcho = () => {
    if (typeof window !== 'undefined' && window.Echo) {
      console.log('✅ [QueueMonitor] Echo disponível!');
      console.log('🎧 [QueueMonitor] Conectando ao canal queue-activity...');
      
      const channel = window.Echo.channel('queue-activity');
      console.log('🎧 [QueueMonitor] Channel criado:', channel);
      
      channel.listen('.queue.activity.updated', (data: any) => {
        console.log('📨 [QueueMonitor] ===== EVENTO RECEBIDO =====');
        console.log('📨 [QueueMonitor] Data completo:', JSON.stringify(data, null, 2));
        
        if (data.queue_name === 'planogramas') {
          console.log('✅ [QueueMonitor] Processando job de planogramas:', data.job_name, data.status);
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
  console.log('🔌 [QueueMonitor] Componente desmontando...');
  if (typeof window !== 'undefined' && window.Echo) {
    window.Echo.leaveChannel('queue-activity');
    console.log('👋 [QueueMonitor] Desconectado do canal');
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
      <CardHeader class="pb-3">
        <div class="flex items-center justify-between">
          <CardTitle class="text-base flex items-center gap-2">
            <Loader2 v-if="completedJobs < totalJobs" class="h-4 w-4 animate-spin" />
            <CheckCircle2 v-else class="h-4 w-4 text-green-600" />
            Processando Planograma
          </CardTitle>
          <div class="flex gap-2">
            <Badge variant="outline" class="text-xs">
              {{ completedJobs }}/{{ totalJobs }}
            </Badge>
            <Badge v-if="failedJobs > 0" variant="destructive" class="text-xs">
              {{ failedJobs }} erros
            </Badge>
          </div>
        </div>
        <CardDescription class="text-xs">
          Salvando alterações em segundo plano
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-2 max-h-64 overflow-y-auto">
        <div
          v-for="[id, job] in activeJobs"
          :key="id"
          class="flex items-center justify-between p-2 rounded-md bg-muted/50"
        >
          <div class="flex items-center gap-2">
            <Loader2 
              v-if="job.status === 'processing'" 
              class="h-3 w-3 animate-spin text-blue-600" 
            />
            <CheckCircle2 
              v-else-if="job.status === 'completed'" 
              class="h-3 w-3 text-green-600" 
            />
            <XCircle 
              v-else-if="job.status === 'failed'" 
              class="h-3 w-3 text-red-600" 
            />
            <span class="text-xs font-medium">
              {{ jobLabels[job.job_name] || job.job_name }}
            </span>
          </div>
          <Badge 
            :variant="
              job.status === 'processing' ? 'default' : 
              job.status === 'completed' ? 'secondary' : 
              'destructive'
            " 
            class="text-xs"
          >
            {{ 
              job.status === 'processing' ? 'Processando' : 
              job.status === 'completed' ? 'Concluído' : 
              'Falhou' 
            }}
          </Badge>
        </div>
      </CardContent>
    </Card>
  </Transition>
</template>
