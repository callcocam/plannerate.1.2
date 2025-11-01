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
      <!-- Conteúdo -->
      <div class="p-6 bg-white dark:bg-gray-800">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200 mb-6">
          Importar Gôndolas
        </h2>

        <!-- Drag & Drop Area -->
        <div
          @drop.prevent="handleDrop"
          @dragover.prevent="isDragging = true"
          @dragleave.prevent="isDragging = false"
          @click="triggerFileInput"
          :class="[
            'relative border-2 border-dashed rounded-lg p-8 transition-all duration-200 cursor-pointer mb-6',
            isDragging
              ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
              : 'border-gray-300 dark:border-gray-600 hover:border-blue-400 hover:bg-gray-50 dark:hover:bg-gray-700/50'
          ]"
        >
          <input
            ref="fileInput"
            type="file"
            @change="handleFileChange"
            accept=".csv,.xls,.xlsx"
            class="hidden"
          />

          <div class="flex flex-col items-center justify-center space-y-4">
            <!-- Icon -->
            <div
              :class="[
                'w-16 h-16 rounded-full flex items-center justify-center transition-all duration-200',
                isDragging
                  ? 'bg-blue-100 dark:bg-blue-800'
                  : 'bg-gray-100 dark:bg-gray-700'
              ]"
            >
              <svg
                v-if="!file"
                class="w-8 h-8 text-gray-400 dark:text-gray-500"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                />
              </svg>
              <svg
                v-else
                class="w-8 h-8 text-green-500"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
            </div>

            <!-- Text -->
            <div class="text-center">
              <p v-if="!file" class="text-lg font-medium text-gray-700 dark:text-gray-300">
                <span class="text-blue-600 dark:text-blue-400">Clique para selecionar</span> ou
                arraste o arquivo aqui
              </p>
              <p v-else class="text-lg font-medium text-gray-700 dark:text-gray-300">
                Arquivo selecionado:
                <span class="text-blue-600 dark:text-blue-400">{{ file.name }}</span>
              </p>
              <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                Formatos suportados: CSV, XLS, XLSX (até 10MB)
              </p>
            </div>

            <!-- File info -->
            <div v-if="file" class="w-full max-w-md">
              <div
                class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 flex items-center justify-between"
              >
                <div class="flex items-center space-x-3">
                  <svg
                    class="w-6 h-6 text-blue-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                    />
                  </svg>
                  <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                      {{ file.name }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                      {{ formatFileSize(file.size) }}
                    </p>
                  </div>
                </div>
                <button
                  type="button"
                  @click.stop="removeFile"
                  class="text-red-500 hover:text-red-700 dark:hover:text-red-400 transition-colors"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M6 18L18 6M6 6l12 12"
                    />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Error Message -->
        <div
          v-if="errorMessage"
          class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md"
        >
          <div class="flex items-start space-x-3">
            <svg
              class="w-5 h-5 text-red-500 mt-0.5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <p class="text-sm text-red-700 dark:text-red-400">{{ errorMessage }}</p>
          </div>
        </div>

        <!-- Success Message -->
        <div
          v-if="successMessage"
          class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md"
        >
          <div class="flex items-start space-x-3">
            <svg
              class="w-5 h-5 text-green-500 mt-0.5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <p class="text-sm text-green-700 dark:text-green-400">{{ successMessage }}</p>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div
        class="flex items-center justify-between  bg-white dark:bg-gray-800 p-4 transition-colors duration-300 dark:border-gray-700"
      >
        <Button
          v-if="file"
          type="button"
          @click="removeFile"
          variant="outline"
          class="transition-colors duration-300"
        >
          Cancelar
        </Button>
        <div v-else></div>

        <!-- Botão Salvar -->
        <Button
          @click="handleImport"
          :disabled="!file || isSending"
          class="text-white transition-colors duration-300"
        >
          <SaveIcon v-if="!isSending" class="mr-2 h-4 w-4" />
          <Loader2Icon v-else class="mr-2 h-4 w-4 animate-spin" />
          Importar Gôndola
        </Button>
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
const fileInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const errorMessage = ref("");
const successMessage = ref("");

const handleFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement;
  if (target.files && target.files.length > 0) {
    validateAndSetFile(target.files[0]);
  }
};

const handleDrop = (event: DragEvent) => {
  isDragging.value = false;

  if (event.dataTransfer?.files && event.dataTransfer.files.length > 0) {
    validateAndSetFile(event.dataTransfer.files[0]);
  }
};

const validateAndSetFile = (selectedFile: File) => {
  errorMessage.value = "";
  successMessage.value = "";

  // Validar tipo de arquivo
  const validExtensions = [".csv", ".xls", ".xlsx"];
  const fileExtension = selectedFile.name
    .substring(selectedFile.name.lastIndexOf("."))
    .toLowerCase();

  if (!validExtensions.includes(fileExtension)) {
    errorMessage.value = "Por favor, selecione um arquivo CSV, XLS ou XLSX válido.";
    return;
  }

  // Validar tamanho (10MB)
  if (selectedFile.size > 10 * 1024 * 1024) {
    errorMessage.value = "O arquivo é muito grande. O tamanho máximo é 10MB.";
    return;
  }

  file.value = selectedFile;
};

const triggerFileInput = () => {
  fileInput.value?.click();
};

const removeFile = () => {
  file.value = null;
  errorMessage.value = "";
  successMessage.value = "";
  if (fileInput.value) {
    fileInput.value.value = "";
  }
};

const formatFileSize = (bytes: number): string => {
  if (bytes === 0) return "0 Bytes";
  const k = 1024;
  const sizes = ["Bytes", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
};

// Functions

const isOpen = ref(props.open);
/** ID do planograma atual da rota. */
const planogramId = ref(route.params.id as string); // Assume que id é sempre uma string

const gondolaId = ref(route.params.gondolaId as string); // Assume que gondolaId é sempre uma string

const handleImport = async () => {
  if (!file.value) return;

  errorMessage.value = "";
  successMessage.value = "";
  isSending.value = true;

  try {
    const formData = new FormData();
    formData.append("gondolaCsv", file.value);
    formData.append("gondolaId", gondolaId.value);
    formData.append("planogramId", planogramId.value);

    await uploadGondolaCSV(formData);

    successMessage.value = "Gôndolas importadas com sucesso!";

    // Aguardar um pouco para mostrar a mensagem de sucesso antes de fechar
    setTimeout(() => {
      router.back();
    }, 1500);
  } catch (error: any) {
    console.error("Erro ao importar arquivo:", error);
    errorMessage.value =
      error.response?.data?.message || "Erro ao importar gôndolas. Tente novamente.";
  } finally {
    isSending.value = false;
  }
};
/**
 * Fecha o diálogo.
 */
const closeModal = () => {
  router.back();
};
</script>
