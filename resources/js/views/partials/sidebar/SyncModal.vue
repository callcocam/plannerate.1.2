<script setup lang="ts">
import { ref, computed } from "vue"
import { Button } from "@/components/ui/button"
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"

const props = defineProps<{ type: string, startDate: string, endDate: string }>()
const emit = defineEmits<{
    save: [type: string, dates: { startDate: string | null, endDate: string | null }]
    close: []
}>()

const startDate = ref(props.startDate || new Date().toISOString().substring(0, 10))
const endDate = ref(props.endDate || new Date().toISOString().substring(0, 10))
const touched = ref(false)
const isLoading = ref(false)
const isDialogOpen = ref(false)

const error = computed(() => {
    if (!touched.value) return ""
    if (!startDate.value || !endDate.value) return "Preencha as datas obrigatórias."
    return ""
})

async function onSync() {
    touched.value = true
    if (!startDate.value || !endDate.value) return
    
    isLoading.value = true
    try {
        // Emitir evento - o parent precisa retornar uma promise
        emit("save", props.type, { startDate: startDate.value, endDate: endDate.value })
        
        // Simular delay mínimo para mostrar loading
        await new Promise(resolve => setTimeout(resolve, 1000))
        
        // Fechar modal após sucesso
        isDialogOpen.value = false
    } catch (error) {
        console.error('Erro na sincronização:', error)
        // Modal permanece aberta em caso de erro
    } finally {
        isLoading.value = false
    }
}

function onOpenChange(open: boolean) {
    isDialogOpen.value = open
    if (!open) {
        startDate.value = props.startDate || new Date().toISOString().substring(0, 10)
        endDate.value = props.endDate || new Date().toISOString().substring(0, 10)
        touched.value = false
        isLoading.value = false
    }
}
</script>

<template>
    <Dialog v-model:open="isDialogOpen" @update:open="onOpenChange">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Sincronizar dados</DialogTitle>
                <DialogDescription>
                    Selecione o período desejado para sincronização.
                </DialogDescription>
            </DialogHeader>
            
            <!-- Overlay de Loading -->
            <div v-if="isLoading" class="absolute inset-0 bg-white/80 flex items-center justify-center z-50 rounded-lg">
                <div class="flex flex-col items-center gap-3">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <p class="text-sm text-gray-600">Sincronizando...</p>
                </div>
            </div>
            
            <div class="grid gap-4 py-4 grid-cols-2" :class="{ 'opacity-50': isLoading }">
                <div class="flex flex-col space-y-1">
                    <Label for="start-date" class="text-right">
                        Data inicial <span class="text-red-500">*</span>
                    </Label>
                    <Input id="start-date" v-model="startDate" class="col-span-3" type="date" required :disabled="isLoading" />
                </div>
                <div class="flex flex-col space-y-1">
                    <Label for="end-date" class="text-right">
                        Data final <span class="text-red-500">*</span>
                    </Label>
                    <Input id="end-date" v-model="endDate" class="col-span-3" type="date" required :disabled="isLoading" />
                </div>
            </div>
            <div v-if="error" class="text-red-500 text-sm mb-2 col-span-2">{{ error }}</div>
            <DialogFooter>
                <Button type="button" variant="outline" @click="isDialogOpen = false" :disabled="isLoading">
                    Cancelar
                </Button>
                <Button type="button" class="ml-auto" :disabled="!startDate || !endDate || isLoading" @click="onSync">
                    <div v-if="isLoading" class="flex items-center gap-2">
                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                        Sincronizando...
                    </div>
                    <span v-else>Sincronizar</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>