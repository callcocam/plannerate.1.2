<!-- SyncModal.vue -->
<template>
  <!-- Modal Backdrop -->
  <div v-if="showModal" 
       class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
       @click="closeModal">
    
    <!-- Modal Content -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 p-6"
         @click.stop>
      
      <!-- Modal Header -->
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
          {{ modalTitle }}
        </h3>
        <button @click="closeModal"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="space-y-4">
        <div class="text-sm text-gray-600 dark:text-gray-400 mb-4">
          {{ modalDescription }}
        </div>

        <!-- Date Inputs -->
        <div class="grid grid-cols-1 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Data Início
            </label>
            <input 
              v-model="startDate"
              type="date" 
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
              :max="endDate"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Data Fim
            </label>
            <input 
              v-model="endDate"
              type="date" 
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
              :min="startDate"
            />
          </div>
        </div>

        <!-- Quick Date Options -->
        <div class="border-t dark:border-gray-700 pt-4">
          <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Opções Rápidas:
          </p>
          <div class="flex flex-wrap gap-2">
            <button @click="setDateRange('today')"
                    class="px-3 py-1 text-xs bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-md transition-colors dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50">
              Hoje
            </button>
            <button @click="setDateRange('week')"
                    class="px-3 py-1 text-xs bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-md transition-colors dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50">
              Esta Semana
            </button>
            <button @click="setDateRange('month')"
                    class="px-3 py-1 text-xs bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-md transition-colors dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50">
              Este Mês
            </button>
            <button @click="setDateRange('lastMonth')"
                    class="px-3 py-1 text-xs bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-md transition-colors dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50">
              Mês Passado
            </button>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="flex items-center justify-center py-4">
          <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
          <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Sincronizando...</span>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="flex justify-end space-x-3 mt-6 pt-4 border-t dark:border-gray-700">
        <button @click="closeModal"
                :disabled="isLoading"
                class="px-4 py-2 text-sm text-gray-700 hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100 transition-colors disabled:opacity-50">
          Cancelar
        </button>
        <button @click="confirmSync"
                :disabled="!startDate || !endDate || isLoading"
                class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded-md transition-colors dark:disabled:bg-gray-600">
          {{ isLoading ? 'Sincronizando...' : syncButtonText }}
        </button>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { ref, computed, watch } from 'vue';

interface Props {
    showModal: boolean;
    syncType: 'product' | 'sales' | 'purchases';
    isLoading?: boolean;
}

interface Emits {
    (e: 'close'): void;
    (e: 'confirm', data: { startDate: string; endDate: string; syncType: string }): void;
}

const props = withDefaults(defineProps<Props>(), {
    isLoading: false
});

const emit = defineEmits<Emits>();

const startDate = ref('');
const endDate = ref('');

// Computed properties for modal content
const modalTitle = computed(() => {
    switch (props.syncType) {
        case 'product':
            return 'Sincronizar Produto';
        case 'sales':
            return 'Sincronizar Vendas';
        case 'purchases':
            return 'Sincronizar Compras';
        default:
            return 'Sincronizar';
    }
});

const modalDescription = computed(() => {
    switch (props.syncType) {
        case 'product':
            return 'Selecione o período para sincronizar as informações do produto.';
        case 'sales':
            return 'Selecione o período para sincronizar os dados de vendas do produto.';
        case 'purchases':
            return 'Selecione o período para sincronizar os dados de compras do produto.';
        default:
            return 'Selecione o período para sincronização.';
    }
});

const syncButtonText = computed(() => {
    switch (props.syncType) {
        case 'product':
            return '🔄 Sincronizar Produto';
        case 'sales':
            return '📈 Sincronizar Vendas';
        case 'purchases':
            return '🛒 Sincronizar Compras';
        default:
            return 'Sincronizar';
    }
});

// Helper function to format date
const formatDate = (date: Date): string => {
    return date.toISOString().split('T')[0];
};

// Quick date range functions
const setDateRange = (range: string) => {
    const today = new Date();
    const currentDate = formatDate(today);

    switch (range) {
        case 'today':
            startDate.value = currentDate;
            endDate.value = currentDate;
            break;

        case 'week':
            const startOfWeek = new Date(today);
            startOfWeek.setDate(today.getDate() - today.getDay());
            startDate.value = formatDate(startOfWeek);
            endDate.value = currentDate;
            break;

        case 'month':
            const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            startDate.value = formatDate(startOfMonth);
            endDate.value = currentDate;
            break;

        case 'lastMonth':
            const startOfLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const endOfLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
            startDate.value = formatDate(startOfLastMonth);
            endDate.value = formatDate(endOfLastMonth);
            break;
    }
};

// Modal actions
const closeModal = () => {
    if (!props.isLoading) {
        startDate.value = '';
        endDate.value = '';
        emit('close');
    }
};

const confirmSync = () => {
    if (startDate.value && endDate.value && !props.isLoading) {
        emit('confirm', {
            startDate: startDate.value,
            endDate: endDate.value,
            syncType: props.syncType
        });
    }
};

// Initialize with current month when modal opens
const initializeDates = () => {
    if (props.showModal && !startDate.value && !endDate.value) {
        setDateRange('month');
    }
};

// Watch for modal opening
watch(() => props.showModal, (newValue) => {
    if (newValue) {
        initializeDates();
    }
});
</script>

<!-- NotificationToast.vue (componente adicional para feedback) -->
<template>
    <transition enter-active-class="transform ease-out duration-300 transition"
        enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0" leave-active-class="transition ease-in duration-100"
        leave-from-class="opacity-100" leave-to-class="opacity-0">

        <div v-if="show"
            class="fixed top-4 right-4 max-w-sm w-full bg-white dark:bg-gray-800 shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 z-50">
            <div class="p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <!-- Success Icon -->
                        <svg v-if="type === 'success'" class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <!-- Error Icon -->
                        <svg v-else-if="type === 'error'" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <!-- Info Icon -->
                        <svg v-else class="h-6 w-6 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-3 w-0 flex-1 pt-0.5">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ title }}</p>
                        <p v-if="message" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ message }}</p>
                    </div>
                    <div class="ml-4 flex-shrink-0 flex">
                        <button @click="close"
                            class="bg-white dark:bg-gray-800 rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none">
                            <span class="sr-only">Fechar</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </transition>
</template>

<script lang="ts" setup>
interface Props {
  show: boolean;
  type: 'success' | 'error' | 'info';
  title: string;
  message?: string;
  autoClose?: boolean;
  autoCloseDelay?: number;
}

interface Emits {
  (e: 'close'): void;
}

const props = withDefaults(defineProps<Props>(), {
  autoClose: true,
  autoCloseDelay: 5000
});

const emit = defineEmits<Emits>();

const close = () => {
  emit('close');
};

// Auto close functionality
if (props.autoClose && props.show) {
  setTimeout(() => {
    close();
  }, props.autoCloseDelay);
}
</script>

<!-- Exemplo de uso no componente principal -->
<script lang="ts">
// No seu componente principal, adicione:

const showSyncModal = ref(false);
const syncType = ref<'product' | 'sales' | 'purchases'>('product');
const isLoading = ref(false);

// Estados para notificação
const showNotification = ref(false);
const notificationType = ref<'success' | 'error' | 'info'>('success');
const notificationTitle = ref('');
const notificationMessage = ref('');

const handleSyncConfirm = async (data: { startDate: string; endDate: string; syncType: string }) => {
    if (!productInfo.value) return;

    isLoading.value = true;

    try {
        const syncParams = {
            product: productInfo.value.id,
            client_id: currentGondola.value?.client_id,
            start_date: data.startDate,
            end_date: data.endDate,
        };

        let syncAction: Promise<any>;

        switch (data.syncType) {
            case 'product':
                syncAction = updateSalesPurchasesProduct({
                    ...syncParams,
                    sync_products: true
                });
                break;
            case 'sales':
                syncAction = updateSalesPurchasesProduct({
                    ...syncParams,
                    sync_sales: true
                });
                break;
            case 'purchases':
                syncAction = updateSalesPurchasesProduct({
                    ...syncParams,
                    sync_purchases: true
                });
                break;
            default:
                throw new Error('Tipo de sincronização inválido');
        }

        await syncAction;

        // Fechar modal
        showSyncModal.value = false;

        // Mostrar notificação de sucesso
        showSuccessNotification(data.syncType);

    } catch (error) {
        console.error('Erro ao sincronizar:', error);
        showErrorNotification(error);
    } finally {
        isLoading.value = false;
    }
};

const showSuccessNotification = (syncType: string) => {
    const typeMessages = {
        product: 'Produto sincronizado com sucesso!',
        sales: 'Vendas sincronizadas com sucesso!',
        purchases: 'Compras sincronizadas com sucesso!'
    };

    notificationType.value = 'success';
    notificationTitle.value = 'Sincronização Concluída';
    notificationMessage.value = typeMessages[syncType] || 'Dados sincronizados com sucesso!';
    showNotification.value = true;
};

const showErrorNotification = (error: any) => {
    notificationType.value = 'error';
    notificationTitle.value = 'Erro na Sincronização';
    notificationMessage.value = error.message || 'Ocorreu um erro durante a sincronização. Tente novamente.';
    showNotification.value = true;
};

const closeNotification = () => {
    showNotification.value = false;
};
</script>