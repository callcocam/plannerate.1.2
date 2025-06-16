import { ref, readonly, computed, type Ref } from 'vue';

/**
 * Composable para gerenciar o estado e a navegação de um formulário multi-etapas (wizard).
 *
 * @param totalSteps - O número total de etapas no wizard (1-indexed).
 * @param initialStep - A etapa inicial (0-indexed, padrão 0).
 * @returns Objeto com o estado reativo e funções de controle do wizard.
 */
export function useWizard(totalSteps: number | Ref<number>, initialStep: number = 0) {
    // Garante que totalSteps seja um valor numérico
    const totalStepsValue = typeof totalSteps === 'number' ? totalSteps : totalSteps.value;

    if (totalStepsValue <= 0) {
        console.warn('useWizard: totalSteps deve ser maior que zero.');
    }
    if (initialStep < 0 || initialStep >= totalStepsValue) {
         console.warn(`useWizard: initialStep (${initialStep}) fora dos limites [0, ${totalStepsValue - 1}]. Resetando para 0.`);
         initialStep = 0;
    }

    const currentStep = ref(initialStep);

    const isFirstStep = computed(() => currentStep.value === 0);
    const isLastStep = computed(() => {
        const currentTotalSteps = typeof totalSteps === 'number' ? totalSteps : totalSteps.value;
        return currentStep.value === currentTotalSteps - 1;
    });

    /**
     * Avança para a próxima etapa, se não for a última.
     */
    const nextStep = () => {
        const currentTotalSteps = typeof totalSteps === 'number' ? totalSteps : totalSteps.value;
        if (currentStep.value < currentTotalSteps - 1) {
            currentStep.value++;
        }
    };

    /**
     * Retorna para a etapa anterior, se não for a primeira.
     */
    const previousStep = () => {
        if (currentStep.value > 0) {
            currentStep.value--;
        }
    };

     /**
     * Vai para uma etapa específica.
     * @param stepIndex - O índice da etapa (0-indexed).
     */
    const goToStep = (stepIndex: number) => {
        const currentTotalSteps = typeof totalSteps === 'number' ? totalSteps : totalSteps.value;
         if (stepIndex >= 0 && stepIndex < currentTotalSteps) {
            currentStep.value = stepIndex;
        } else {
             console.warn(`useWizard: Tentativa de ir para etapa inválida (${stepIndex}). Limites são [0, ${currentTotalSteps - 1}].`);
        }
    };


    return {
        currentStep: readonly(currentStep), // Expõe como readonly para o consumidor
        nextStep,
        previousStep,
        goToStep,
        isFirstStep,
        isLastStep,
    };
} 