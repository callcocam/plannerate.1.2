import { computed, ref, watch } from "vue";

// BCGConfigurationRules.ts
export interface BCGConfigurationRule {
    classifyBy: string;
    displayBy: string;
    label: string;
}

export const HIERARCHY_LEVELS = [
    'segmento_varejista',
    'departamento',
    'subdepartamento',
    'categoria',
    'subcategoria',
    'produto'
] as const;

export type HierarchyLevel = typeof HIERARCHY_LEVELS[number];

export const LEVEL_LABELS: Record<HierarchyLevel, string> = {
    segmento_varejista: 'Segmento Varejista',
    departamento: 'Departamento',
    subdepartamento: 'Subdepartamento',
    categoria: 'Categoria',
    subcategoria: 'Subcategoria',
    produto: 'Produto'
};

// Regras válidas de configuração
export const VALID_BCG_COMBINATIONS: BCGConfigurationRule[] = [
    // Segmento Varejista como base
    { classifyBy: 'segmento_varejista', displayBy: 'departamento', label: 'Classificar por Segmento → Exibir por Departamento' },
    { classifyBy: 'segmento_varejista', displayBy: 'subdepartamento', label: 'Classificar por Segmento → Exibir por Subdepartamento' },
    { classifyBy: 'segmento_varejista', displayBy: 'categoria', label: 'Classificar por Segmento → Exibir por Categoria' },
    { classifyBy: 'segmento_varejista', displayBy: 'subcategoria', label: 'Classificar por Segmento → Exibir por Subcategoria' },
    { classifyBy: 'segmento_varejista', displayBy: 'produto', label: 'Classificar por Segmento → Exibir por Produto' },

    // Departamento como base
    { classifyBy: 'departamento', displayBy: 'subdepartamento', label: 'Classificar por Departamento → Exibir por Subdepartamento' },
    { classifyBy: 'departamento', displayBy: 'categoria', label: 'Classificar por Departamento → Exibir por Categoria' },
    { classifyBy: 'departamento', displayBy: 'produto', label: 'Classificar por Departamento → Exibir por Produto' },

    // Subdepartamento como base
    { classifyBy: 'subdepartamento', displayBy: 'categoria', label: 'Classificar por Subdepartamento → Exibir por Categoria' },
    { classifyBy: 'subdepartamento', displayBy: 'produto', label: 'Classificar por Subdepartamento → Exibir por Produto' },

    // Categoria como base
    { classifyBy: 'categoria', displayBy: 'subcategoria', label: 'Classificar por Categoria → Exibir por Subcategoria' },
    { classifyBy: 'categoria', displayBy: 'produto', label: 'Classificar por Categoria → Exibir por Produto' },

    // Subcategoria como base
    { classifyBy: 'subcategoria', displayBy: 'produto', label: 'Classificar por Subcategoria → Exibir por Produto' }
];

/**
 * Valida se uma combinação de classificação/exibição é válida
 */
export function isValidCombination(classifyBy: string, displayBy: string): boolean {
    return VALID_BCG_COMBINATIONS.some(
        rule => rule.classifyBy === classifyBy && rule.displayBy === displayBy
    );
}

/**
 * Retorna as opções válidas de exibição para um nível de classificação
 */
export function getValidDisplayOptions(classifyBy: string): HierarchyLevel[] {
    const validRules = VALID_BCG_COMBINATIONS.filter(rule => rule.classifyBy === classifyBy);
    return validRules.map(rule => rule.displayBy as HierarchyLevel);
}

/**
 * Retorna as opções válidas de classificação para um nível de exibição
 */
export function getValidClassifyOptions(displayBy: string): HierarchyLevel[] {
    const validRules = VALID_BCG_COMBINATIONS.filter(rule => rule.displayBy === displayBy);
    return validRules.map(rule => rule.classifyBy as HierarchyLevel);
}

/**
 * Verifica se um nível está acima de outro na hierarquia
 */
export function isHigherInHierarchy(level1: HierarchyLevel, level2: HierarchyLevel): boolean {
    const index1 = HIERARCHY_LEVELS.indexOf(level1);
    const index2 = HIERARCHY_LEVELS.indexOf(level2);
    return index1 < index2;
}

/**
 * Composable para gerenciar configurações BCG
 */
export function useBCGConfiguration() {
    const classifyBy = ref<HierarchyLevel>('categoria');
    const displayBy = ref<HierarchyLevel>('produto');

    const availableDisplayOptions = computed(() => {
        return getValidDisplayOptions(classifyBy.value);
    });

    const availableClassifyOptions = computed(() => {
        return getValidClassifyOptions(displayBy.value);
    });

    const isCurrentCombinationValid = computed(() => {
        return isValidCombination(classifyBy.value, displayBy.value);
    });

    const currentRuleLabel = computed(() => {
        const rule = VALID_BCG_COMBINATIONS.find(
            r => r.classifyBy === classifyBy.value && r.displayBy === displayBy.value
        );
        return rule?.label || 'Combinação inválida';
    });

    // Quando classifyBy muda, ajustar displayBy para uma opção válida
    watch(classifyBy, (newClassifyBy) => {
        const validOptions = getValidDisplayOptions(newClassifyBy);
        if (validOptions.length > 0 && !validOptions.includes(displayBy.value)) {
            displayBy.value = validOptions[0];
        }
    });

    // Quando displayBy muda, verificar se a combinação ainda é válida
    watch(displayBy, (newDisplayBy) => {
        if (!isValidCombination(classifyBy.value, newDisplayBy)) {
            const validOptions = getValidClassifyOptions(newDisplayBy);
            if (validOptions.length > 0) {
                classifyBy.value = validOptions[0];
            }
        }
    });

    return {
        classifyBy,
        displayBy,
        availableDisplayOptions,
        availableClassifyOptions,
        isCurrentCombinationValid,
        currentRuleLabel,
        LEVEL_LABELS,
        VALID_BCG_COMBINATIONS
    };
}