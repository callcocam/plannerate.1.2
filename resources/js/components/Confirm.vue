<template>
    <Dialog :open="isOpen" @update:open="handleUpdateOpen">
        <DialogContent :class="{ 'border-destructive': isDangerous }">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="message">
                    {{ message }}
                </DialogDescription>
                <slot name="description"></slot>
            </DialogHeader>

            <slot></slot>

            <DialogFooter>
                <Button variant="outline" @click="cancel">
                    {{ cancelButtonText }}
                </Button>
                <Button :variant="isDangerous ? 'destructive' : 'default'" @click="confirm">
                    {{ confirmButtonText }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
interface ConfirmModalProps {
    isOpen: boolean;
    title?: string;
    message?: string;
    confirmButtonText?: string;
    cancelButtonText?: string;
    isDangerous?: boolean;
}

const props = withDefaults(defineProps<ConfirmModalProps>(), {
    isOpen: false,
    title: 'Confirmar',
    message: 'Tem certeza que deseja realizar esta ação?',
    confirmButtonText: 'Confirmar',
    cancelButtonText: 'Cancelar',
    isDangerous: false,
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
    if (value !== props.isOpen) {
        emit('update:isOpen', value);
    }

    if (!value) {
        // Se o diálogo estiver sendo fechado pelo "X" ou ESC, tratar como cancelamento
        emit('cancel');
    }
}
</script>

<style scoped>
.border-destructive {
    border-color: hsl(var(--destructive));
}
</style>
