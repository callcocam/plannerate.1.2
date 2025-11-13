<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppLayout from './layouts/AppLayout.vue';
import { Toaster, toast } from 'vue-sonner';

const route = useRoute();
const router = useRouter();
const appLayoutKey = route.fullPath.concat('-app-layout');

// Interface para dados de atividade de fila
interface QueueActivityData {
  job_name: string;
  queue_name: string;
  status: string;
  metadata: {
    planogram_id?: string;
    planogram_name?: string;
    started_at?: string;
    completed_at?: string;
    failed_at?: string;
    error?: string;
  };
  tenant_id: string | null;
  timestamp: string;
}

// Configurar listener para atividades da fila de planogramas
onMounted(() => {
  if (typeof window !== 'undefined' && window.Echo) {
    console.log('🎧 [Plannerate] Configurando listener para SavePlanogramJob...');

    // Escutar no canal público de atividades de fila
    window.Echo.channel('queue-activity')
      .listen('.queue.activity.updated', (data: QueueActivityData) => {
        // Filtrar apenas eventos da fila 'planogramas' e do job 'SavePlanogramJob'
        if (data.queue_name !== 'planogramas' || data.job_name !== 'SavePlanogramJob') {
          return;
        }

        console.log('📢 [Plannerate] Atividade de SavePlanogramJob recebida:', data);

        // Determinar o tipo de notificação baseado no status
        const statusConfig = {
          'processing': {
            title: '⚙️ Processando Planograma',
            description: data.metadata.planogram_name
              ? `Salvando "${data.metadata.planogram_name}"...`
              : 'Salvando planograma...',
            type: 'info' as const
          },
          'completed': {
            title: '✅ Planograma Salvo',
            description: data.metadata.planogram_name
              ? `"${data.metadata.planogram_name}" foi salvo com sucesso!`
              : 'Planograma salvo com sucesso!',
            type: 'success' as const
          },
          'failed': {
            title: '❌ Erro ao Salvar',
            description: data.metadata.planogram_name
              ? `Falha ao salvar "${data.metadata.planogram_name}": ${data.metadata.error || 'Erro desconhecido'}`
              : `Erro ao salvar planograma: ${data.metadata.error || 'Erro desconhecido'}`,
            type: 'error' as const
          }
        };

        const config = statusConfig[data.status as keyof typeof statusConfig];

        if (config) {
          // Mostrar notificação
          toast[config.type](config.title, {
            description: config.description,
            duration: data.status === 'failed' ? 5000 : 3000,
          });

          // Se o job foi concluído com sucesso, recarregar a página atual
          if (data.status === 'completed') {
            console.log('🔄 [Plannerate] Recarregando página após salvamento...');
            setTimeout(() => {
              router.go(0); // Recarrega a página atual
            }, 1500);
          }
        }
      });

    console.log('✅ [Plannerate] Listener de SavePlanogramJob configurado');
  } else {
    console.warn('⚠️ [Plannerate] Echo não está disponível. Notificações de fila não funcionarão.');
  }
});

// Limpar listeners ao desmontar
onUnmounted(() => {
  if (typeof window !== 'undefined' && window.Echo) {
    window.Echo.leaveChannel('queue-activity');
    console.log('🔌 [Plannerate] Desconectado do canal de atividades de fila');
  }
});
</script>

<template>
    <AppLayout>
        <div class="px-10">
            <router-view :key="appLayoutKey" />
        </div>
        <Toaster />
    </AppLayout>
</template>
