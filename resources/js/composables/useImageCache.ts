// composables/useImageCache.js
import { ref, reactive } from 'vue';

// Cache global compartilhado entre todos os componentes
const imageCache = reactive(new Map());
const loadingImages = reactive(new Set());
const failedImages = reactive(new Set());

export function useImageCache() {
    const isLoading = ref(false);
    const error = ref(null);

    const loadImage = async (url: string) => {
        if (!url) return null;

        // Se já falhou antes, não tenta novamente
        if (failedImages.has(url)) {
            throw new Error(`Imagem falhou anteriormente: ${url}`);
        }

        // Se já está em cache, retorna imediatamente
        if (imageCache.has(url)) {
            return imageCache.get(url);
        }

        // Se já está carregando, aguarda
        if (loadingImages.has(url)) {
            return new Promise((resolve, reject) => {
                const checkCache = () => {
                    if (imageCache.has(url)) {
                        resolve(imageCache.get(url));
                    } else if (failedImages.has(url)) {
                        reject(new Error('Falha no carregamento'));
                    } else {
                        setTimeout(checkCache, 10);
                    }
                };
                checkCache();
            });
        }

        isLoading.value = true;
        loadingImages.add(url);
        error.value = null;

        try {
            const img = new Image();

            const loadPromise = new Promise((resolve, reject) => {
                img.onload = () => {
                    // Armazenar tanto a URL quanto o elemento Image
                    imageCache.set(url, {
                        element: img,
                        url: url,
                        width: img.naturalWidth,
                        height: img.naturalHeight,
                        timestamp: Date.now()
                    });
                    resolve(url);
                };

                img.onerror = () => {
                    failedImages.add(url);
                    reject(new Error(`Falha ao carregar: ${url}`));
                };
            });

            img.src = url;
            await loadPromise;

            return url;

        } catch (err: any) {
            error.value = err.message;
            throw err;
        } finally {
            isLoading.value = false;
            loadingImages.delete(url);
        }
    };

    const preloadImages = async (urls: string[]) => {
        const promises = urls.map(url =>
            loadImage(url).catch(err => ({ error: err, url }))
        );
        return Promise.allSettled(promises);
    };

    const isImageCached = (url: string) => {
        return imageCache.has(url);
    };

    const isImageFailed = (url: string) => {
        return failedImages.has(url);
    };

    const clearCache = () => {
        imageCache.clear();
        loadingImages.clear();
        failedImages.clear();
    };

    const clearFailedImages = () => {
        failedImages.clear();
    };

    const getCacheInfo = () => ({
        cached: imageCache.size,
        loading: loadingImages.size,
        failed: failedImages.size,
        cacheUrls: Array.from(imageCache.keys()),
        failedUrls: Array.from(failedImages),
        totalMemory: estimateMemoryUsage()
    });

    const estimateMemoryUsage = () => {
        let totalSize = 0;
        for (const [url, data] of imageCache) {
            // Estimativa básica baseada nas dimensões
            totalSize += (data.width * data.height * 4); // 4 bytes por pixel (RGBA)
        }
        return `${(totalSize / 1024 / 1024).toFixed(2)} MB`;
    };

    // Limpeza automática de cache antigo (opcional)
    const cleanOldCache = (maxAge = 30 * 60 * 1000) => { // 30 minutos
        const now = Date.now();
        for (const [url, data] of imageCache) {
            if (now - data.timestamp > maxAge) {
                imageCache.delete(url);
            }
        }
    };

    return {
        loadImage,
        preloadImages,
        isImageCached,
        isImageFailed,
        clearCache,
        clearFailedImages,
        cleanOldCache,
        getCacheInfo,
        isLoading,
        error
    };
}