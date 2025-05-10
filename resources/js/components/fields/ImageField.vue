<template>
    <div class="grid gap-2">
        <Label :for="id">{{ label }}</Label>
        <div class="relative w-full h-48 overflow-hidden rounded-md border border-input">
                <Cropper
                    ref="cropperRef"
                    :src="imageUrl || modelValue" 
                    @change="handleCrop"
                    class="w-full h-64"
                /> 
                <Button v-if="(previewUrl || modelValue)" type="button" variant="destructive" size="icon"
                    class="absolute top-2 right-2 h-8 w-8" @click="removeImage"
                    title="Remover imagem">
                    <Trash2 class="h-4 w-4" />
                </Button>
                <Button v-if="previewUrl" type="button" variant="outline" size="icon"
                    class="absolute top-2 left-2 h-8 w-8" @click="cancelImageSelection"
                    title="Cancelar seleção">
                    Cancelar
                </Button>
        </div> 
        <!-- Upload de nova imagem -->
        <div class="flex items-center gap-2 mt-2">
            <input type="file" :id="id" accept="image/*" ref="fileInput"
                @change="selectImage" class="hidden" />
            <div class="flex-1 flex gap-2">
                <Button type="button" variant="outline" class="flex-1"
                    @click="triggerFileInput">
                    <Upload class="h-4 w-4 mr-2" />
                    Selecionar imagem
                </Button> 
            </div>
        </div>
        <p v-if="selectedFile" class="text-sm text-green-600 mt-1">
            Imagem selecionada: {{ selectedFile.name }}
        </p>
        <span v-if="error" class="text-red-500 text-xs">{{ error }}</span>
    </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { Trash2, Upload } from 'lucide-vue-next';
import { Cropper } from "vue-advanced-cropper";
import "vue-advanced-cropper/dist/style.css";
import type { CropperResult } from 'vue-advanced-cropper';
import { Label } from '../ui/label';
import { Button } from '../ui/button';

const props = defineProps<{
    modelValue?: string;
    label?: string;
    id?: string;
    alt?: string;
    error?: string | null;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'crop', value: CropperResult): void;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const imageUrl = ref<string | null>(null);
const previewUrl = ref<string | null>(null);
const selectedFile = ref<File | null>(null);
const croppedImage = ref<CropperResult | null>(null);
const cropperRef = ref<any>(null);

// Observar mudanças no modelValue para atualizar o imageUrl
watch(() => props.modelValue, (newValue) => {
    if (newValue && !imageUrl.value) {
        imageUrl.value = newValue;
    }
}, { immediate: true });

const triggerFileInput = () => {
    fileInput.value?.click();
};

const selectImage = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0] || null;
    selectedFile.value = file;
    if (file) {
        imageUrl.value = URL.createObjectURL(file);
    } else {
        imageUrl.value = null;
        previewUrl.value = null;
    }
};

const handleCrop = (result: CropperResult) => { 
    croppedImage.value = result; 
    emit('crop', result);
};
 

const removeImage = () => {
    emit('update:modelValue', '');
    previewUrl.value = null;
    imageUrl.value = null;
    croppedImage.value = null;
    selectedFile.value = null;
};

const cancelImageSelection = () => {
    selectedFile.value = null;
    previewUrl.value = null;
    imageUrl.value = props.modelValue || null;
    croppedImage.value = null;
};
 
</script> 