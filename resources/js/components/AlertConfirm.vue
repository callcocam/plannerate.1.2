<template>
    <AlertDialog>
        <AlertDialogTrigger>
            <slot />
        </AlertDialogTrigger>
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ title }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ message }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="$emit('cancel', false)">{{ cancelButtonText }}</AlertDialogCancel>
                <AlertDialogAction @click="$emit('confirm', record)">{{ confirmButtonText }}</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>

<script setup lang="ts"> 

interface ConfirmModalProps {
    title?: string;
    message?: string;
    confirmButtonText?: string;
    cancelButtonText?: string;
    isDangerous?: boolean;
    type?: string;
    record?: any;
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
defineEmits<{
    (e: 'cancel', value: boolean): void;
    (e: 'confirm', value: any): void;
}>();

</script>