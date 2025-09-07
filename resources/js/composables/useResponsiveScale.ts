import { computed, onMounted, onUnmounted, ref } from 'vue';

/**
 * Composable para calcular scale factor responsivo baseado no dispositivo e zoom
 */
export function useResponsiveScale(baseScaleFactor: number | (() => number)) {
    // Estado reativo para forçar recálculo em resize
    const windowSize = ref({ width: window.innerWidth, height: window.innerHeight });
    
    /**
     * Atualiza as dimensões da janela
     */
    const updateWindowSize = () => {
        windowSize.value = {
            width: window.innerWidth,
            height: window.innerHeight
        };
    };

    /**
     * Calcula o scale factor responsivo
     */
    const responsiveScaleFactor = computed(() => {
        const scaleFactor = typeof baseScaleFactor === 'function' 
            ? baseScaleFactor() 
            : baseScaleFactor;
            
        // Detecta device pixel ratio para telas retina
        const devicePixelRatio = window.devicePixelRatio || 1;
        
        // Detecta tamanho da tela
        const screenWidth = windowSize.value.width;
        const isMobile = screenWidth < 768;
        const isTablet = screenWidth >= 768 && screenWidth < 1024;
        const isSmallMobile = screenWidth < 480;
        
        // Ajusta o scale factor baseado no dispositivo
        let responsiveFactor = scaleFactor;
        
        if (isSmallMobile) {
            // Em mobile muito pequeno, reduz ainda mais
            responsiveFactor = scaleFactor * 0.6;
        } else if (isMobile) {
            // Em mobile, reduz o scale factor para melhor encaixe
            responsiveFactor = scaleFactor * 0.7;
        } else if (isTablet) {
            // Em tablet, reduz um pouco menos
            responsiveFactor = scaleFactor * 0.85;
        }
        
        // Ajusta para telas retina (pixel ratio alto)
        if (devicePixelRatio > 1.5) {
            responsiveFactor = responsiveFactor / Math.min(devicePixelRatio * 0.3, 0.8);
        }
        
        return Math.max(0.5, responsiveFactor); // Nunca menor que 0.5
    });

    /**
     * Detecta se é mobile
     */
    const isMobile = computed(() => windowSize.value.width < 768);
    
    /**
     * Detecta se é tablet
     */
    const isTablet = computed(() => {
        const width = windowSize.value.width;
        return width >= 768 && width < 1024;
    });
    
    /**
     * Detecta se é desktop
     */
    const isDesktop = computed(() => windowSize.value.width >= 1024);

    /**
     * Detecta tela retina
     */
    const isRetina = computed(() => (window.devicePixelRatio || 1) > 1.5);

    /**
     * Configura listeners
     */
    onMounted(() => {
        window.addEventListener('resize', updateWindowSize, { passive: true });
        updateWindowSize(); // Inicializa
    });

    onUnmounted(() => {
        window.removeEventListener('resize', updateWindowSize);
    });

    return {
        responsiveScaleFactor,
        isMobile,
        isTablet,
        isDesktop,
        isRetina,
        windowSize: computed(() => windowSize.value)
    };
}

/**
 * Calcula dimensões responsivas para produtos
 */
export function useResponsiveProductDimensions(
    width: number | (() => number),
    height: number | (() => number),
    scaleFactor: number | (() => number)
) {
    const { responsiveScaleFactor, isMobile, isTablet } = useResponsiveScale(scaleFactor);

    const dimensions = computed(() => {
        const w = typeof width === 'function' ? width() : width;
        const h = typeof height === 'function' ? height() : height;
        const scale = responsiveScaleFactor.value;

        const scaledWidth = w * scale;
        const scaledHeight = h * scale;

        // Define tamanhos mínimos baseado no dispositivo
        let minWidth = 12;
        let minHeight = 12;

        if (isMobile.value) {
            minWidth = 15;
            minHeight = 15;
        } else if (isTablet.value) {
            minWidth = 18;
            minHeight = 18;
        } else {
            minWidth = 20;
            minHeight = 20;
        }

        return {
            width: Math.max(scaledWidth, minWidth),
            height: Math.max(scaledHeight, minHeight),
            scaledWidth,
            scaledHeight,
            originalWidth: w,
            originalHeight: h,
            scale
        };
    });

    return {
        dimensions,
        responsiveScaleFactor,
        isMobile,
        isTablet
    };
}
