<template>
  <Dialog :open="isOpen">
    <DialogPersonaCloseContent
      class="flex max-h-[90vh] w-full max-w-4xl flex-col p-0 dark:border-gray-700 dark:bg-gray-800"
    >
      <DialogClose
        @click="closeModal"
        class="absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:pointer-events-none data-[state=open]:bg-accent data-[state=open]:text-muted-foreground"
      >
        <X class="h-4 w-4" />
        <span class="sr-only">Fechar</span>
      </DialogClose>
      <!-- Rodapé Fixo -->
      <div class="p-6 bg-white rounded-lg shadow-md">
        <h2 class="text-2xl font-semibold mb-4">Importar Gôndolas</h2>
        <div class="mb-4">
          <label for="file" class="block text-sm font-medium text-gray-700 mb-2"
            >Selecione o arquivo CSV/XLS/XLSX:</label
          >
          <!-- xl -->
          <input
            type="file"
            id="file"
            @change="handleFileChange"
            accept=".csv,.xls,.xlsx"
            class="border border-gray-300 rounded-md p-2 w-full"
          />
        </div>
        <div
          class="flex justify-end border-t bg-white p-4 transition-colors duration-300 dark:border-gray-700 dark:bg-gray-800"
        >
          <!-- Botão Salvar -->
          <Button
            @click="handleImport"
            :disabled="isSending"
            class="text-white transition-colors duration-300"
          >
            <SaveIcon v-if="!isSending" class="mr-2 h-4 w-4" />
            <Loader2Icon v-else class="mr-2 h-4 w-4 animate-spin" />
            Importar Gôndola
          </Button>
        </div>
      </div>
    </DialogPersonaCloseContent>
  </Dialog>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { Button } from "@/components/ui/button";
import { useRoute, useRouter } from "vue-router";

import { useGondolaService } from "@plannerate/services/gondolaService";

import { Dialog, DialogClose } from "@/components/ui/dialog";
import DialogPersonaCloseContent from "@plannerate/components/ui/dialog/DialogPersonaCloseContent.vue";
import { Loader2Icon, SaveIcon, X } from "lucide-vue-next";
// Vue Router Hooks
const route = useRoute();
const router = useRouter();

const { uploadGondolaCSV } = useGondolaService();

const isSending = ref(false);
// Definição das Props do Componente
/**
 * Props do componente.
 * @property {boolean} open - Controla a visibilidade do diálogo. Recebido do componente pai.
 */
const props = defineProps({
  open: {
    type: Boolean,
    default: true, // Padrão para aberto conforme última edição do usuário
  },
});
const file = ref<File | null>(null);

const handleFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement;
  if (target.files && target.files.length > 0) {
    file.value = target.files[0];
  }
};

// Functions

const isOpen = ref(props.open);
/** ID do planograma atual da rota. */
const planogramId = ref(route.params.id as string); // Assume que id é sempre uma string

const gondolaId = ref(route.params.gondolaId as string); // Assume que gondolaId é sempre uma string

const handleImport = async () => {
  if (file.value) {
    // Lógica para importar o arquivo CSV
    console.log(
      "Importando arquivo:",
      file.value.name,
      "para o planograma ID:",
      planogramId.value,
      "e gôndola ID:",
      gondolaId.value
    );
    // Aqui você pode adicionar a lógica para enviar o arquivo para o servidor
    isSending.value = true;
    try {
      const formData = new FormData();
      formData.append("gondolaCsv", file.value);
      formData.append("gondolaId", gondolaId.value);
      formData.append("planogramId", planogramId.value);
      await uploadGondolaCSV(formData);
      // Simulação de atraso para importação
      router.back();
    } catch (error) {
      console.error("Erro ao importar arquivo:", error);
    } finally {
      isSending.value = false;
    }
  }
};
/**
 * Fecha o diálogo.
 */
const closeModal = () => {
  router.back();
};
</script>
