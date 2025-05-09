<template>
    <AlertDialog class="alert-confirm"  >
        <AlertDialogTrigger>
            <Trash2 class="h-4 w-4" />
        </AlertDialogTrigger>
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ title }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ message }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel class="border-destructive">{{ cancelButtonText }}</AlertDialogCancel>
                <AlertDialogAction>{{ confirmButtonText }}</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
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
} from './ui/alert-dialog'

import { Trash2 } from 'lucide-vue-next';

interface ConfirmModalProps { 
    title?: string;
    message?: string;
    confirmButtonText?: string;
    cancelButtonText?: string;
    isDangerous?: boolean;
    type?: string;
}
withDefaults(defineProps<ConfirmModalProps>(), {
    isOpen: false,
    title: 'Confirmar',
    message: 'Tem certeza que deseja realizar esta ação?',
    confirmButtonText: 'Confirmar',
    cancelButtonText: 'Cancelar',
    isDangerous: false,
    type: 'question',
});

const isOpen = defineModel<boolean>('isOpen', { required: true });

</script>