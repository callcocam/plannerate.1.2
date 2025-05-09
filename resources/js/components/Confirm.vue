<template>
    <Dialog :open="isOpen" @update:open="handleUpdateOpen">
        <DialogContent 
            :class="[
                'max-w-md p-0 gap-0 overflow-hidden',
                isDangerous ? 'border-destructive' : 'border-primary/20'
            ]"
        >
            <div class="flex flex-col gap-0">
                <!-- Cabeçalho com cor de fundo baseada no tipo -->
                <div 
                    :class="[
                        'py-6 px-6 relative',
                        isDangerous 
                            ? 'bg-gradient-to-r from-destructive/5 to-destructive/20' 
                            : 'bg-gradient-to-r from-primary/5 to-primary/15'
                    ]"
                >
                    <div class="flex items-start gap-4">
                        <!-- Ícone apropriado para o tipo de confirmação -->
                        <div 
                            :class="[
                                'rounded-full p-2 flex items-center justify-center transition-all duration-300',
                                isDangerous 
                                    ? 'bg-destructive/20 text-destructive' 
                                    : 'bg-primary/20 text-primary'
                            ]"
                        >
                            <AlertTriangle v-if="isDangerous" class="h-6 w-6 animate-pulse" />
                            <HelpCircle v-else-if="type === 'question'" class="h-6 w-6" />
                            <Info v-else-if="type === 'info'" class="h-6 w-6" />
                            <CheckCircle v-else class="h-6 w-6" />
                        </div>
                        
                        <!-- Título e mensagem -->
                        <DialogHeader class="gap-1.5 text-left">
                            <DialogTitle class="text-lg font-semibold">{{ title }}</DialogTitle>
                            <DialogDescription v-if="message" class="text-sm opacity-90">
                                {{ message }}
                            </DialogDescription>
                            <slot name="description"></slot>
                        </DialogHeader>
                    </div>
                </div>

                <!-- Conteúdo personalizado, caso exista -->
                <div class="px-6 py-4" v-if="$slots.default">
                    <slot></slot>
                </div>

                <!-- Rodapé com ações -->
                <DialogFooter class="px-6 py-4 bg-muted/20">
                    <div class="flex flex-row justify-end gap-2 w-full">
                        <Button 
                            type="button"
                            variant="outline" 
                            @click="cancel"
                            class="min-w-[100px]"
                        >
                            {{ cancelButtonText }}
                        </Button>
                        <Button 
                            type="button"
                            :variant="isDangerous ? 'destructive' : 'default'" 
                            @click="confirm"
                            class="min-w-[100px]"
                        >
                            {{ confirmButtonText }}
                        </Button>
                    </div>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button';
import { AlertTriangle, HelpCircle, Info, CheckCircle } from 'lucide-vue-next';

interface ConfirmModalProps {
    isOpen: boolean;
    title?: string;
    message?: string;
    confirmButtonText?: string;
    cancelButtonText?: string;
    isDangerous?: boolean;
    type?: string;
}

const props = withDefaults(defineProps<ConfirmModalProps>(), {
    isOpen: false,
    title: 'Confirmar',
    message: 'Tem certeza que deseja realizar esta ação?',
    confirmButtonText: 'Confirmar',
    cancelButtonText: 'Cancelar',
    isDangerous: false,
    type: 'question',
});

const emit = defineEmits<{
    (e: 'confirm'): void;
    (e: 'cancel'): void;
    (e: 'update:isOpen', value: boolean): void;
}>();

function confirm(): void {
    emit('confirm');
    emit('update:isOpen', false);
}

function cancel(): void {
    emit('cancel');
    emit('update:isOpen', false);
}

function handleUpdateOpen(value: boolean): void {
    emit('update:isOpen', value);
    
    // Se o diálogo estiver sendo fechado pelo "X" ou ESC, tratar como cancelamento
    if (!value && props.isOpen) {
        emit('cancel');
    }
}
</script>

<style scoped>
.border-destructive {
    border-color: hsl(var(--destructive));
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.6;
    }
}
</style>
