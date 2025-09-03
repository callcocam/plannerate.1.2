import domtoimage from 'dom-to-image';
import jsPDF from 'jspdf';

/**
 * Serviço de impressão para planogramas
 * Permite capturar e imprimir módulos individuais ou planograma completo
 */
export class PrintService {
    constructor() {
        this.defaultConfig = {
            scale: 2, // Escala padrão para melhor qualidade
            format: 'A4', // A4, A3, custom
            orientation: 'landscape', // landscape, portrait
            margins: {
                top: 20,
                right: 20,
                bottom: 20,
                left: 20
            },
            quality: 0.95, // Qualidade da imagem (0-1)
            backgroundColor: '#ffffff'
        };
    }

    /**
     * Detecta automaticamente os módulos disponíveis no planograma
     * Cada módulo é composto por: cremalheira_esquerda + seção + cremalheira_direita
     * @returns {Array} Lista de módulos COMPLETOS encontrados
     */
    detectModules() {
        console.log('=== INICIANDO DETECÇÃO DE MÓDULOS COMPLETOS ===');
        
        const modules = [];
        
        // Primeiro: detecta todas as seções disponíveis no planograma
        const sections = document.querySelectorAll('[data-section-id]');
        console.log(`🔍 Encontradas ${sections.length} seções no planograma`);
        
        if (sections.length === 0) {
            console.warn('❌ Nenhuma seção encontrada no planograma!');
            return [];
        }
        
        // Segundo: para cada seção, monta o módulo completo
        sections.forEach((sectionElement, index) => {
            const sectionId = sectionElement.getAttribute('data-section-id');
            const sectionIndex = index; // Índice da seção (0-8)
            
            console.log(`🔧 Montando módulo ${sectionIndex + 1} (seção: ${sectionId})...`);
            
            try {
                // Busca cremalheira esquerda (índice atual)
                const cremalheiraEsquerda = document.querySelector(`[data-cremalheira-index="${sectionIndex}"]`);
                
                // Busca cremalheira direita (próximo índice ou LastRack)
                let cremalheiraDireita;
                if (sectionIndex === sections.length - 1) {
                    // Último módulo: usa LastRack como cremalheira direita
                    cremalheiraDireita = document.querySelector('[data-last-rack="true"] [data-cremalheira="true"]');
                    console.log(`📍 Módulo ${sectionIndex + 1}: Usando LastRack como cremalheira direita`);
                } else {
                    // Módulos normais: usa próxima cremalheira
                    cremalheiraDireita = document.querySelector(`[data-cremalheira-index="${sectionIndex + 1}"]`);
                    console.log(`📍 Módulo ${sectionIndex + 1}: Usando cremalheira ${sectionIndex + 1} como direita`);
                }
                
                // Valida se encontrou todos os componentes necessários
                if (!sectionElement) {
                    console.warn(`❌ Módulo ${sectionIndex + 1}: Seção não encontrada`);
                    return;
                }
                
                if (!cremalheiraEsquerda) {
                    console.warn(`❌ Módulo ${sectionIndex + 1}: Cremalheira esquerda (${sectionIndex}) não encontrada`);
                    return;
                }
                
                if (!cremalheiraDireita) {
                    console.warn(`❌ Módulo ${sectionIndex + 1}: Cremalheira direita não encontrada`);
                    return;
                }
                
                // Cria container virtual para o módulo completo
                const moduleContainer = this.createVirtualModuleContainer({
                    sectionId,
                    sectionIndex,
                    sectionElement,
                    cremalheiraEsquerda,
                    cremalheiraDireita
                });
                
                if (moduleContainer && this.isElementValid(moduleContainer)) {
                    const moduleData = {
                        id: sectionId,
                        name: `Módulo ${sectionIndex + 1}`,
                        element: moduleContainer,
                        moduleType: 'COMPLETE_MODULE',
                        hasCremalheira: true,
                        hasSection: true,
                        cremalheiraCount: 2, // Esquerda + direita
                        sectionCount: 1,
                        isValid: true,
                        sectionIndex: sectionIndex,
                        components: {
                            section: sectionElement,
                            cremalheiraEsquerda,
                            cremalheiraDireita
                        }
                    };
                    
                    modules.push(moduleData);
                    console.log(`✅ Módulo ${sectionIndex + 1} criado com sucesso`);
                } else {
                    console.warn(`❌ Módulo ${sectionIndex + 1}: Container virtual inválido`);
                }
                
            } catch (error) {
                console.error(`❌ Erro ao montar módulo ${sectionIndex + 1}:`, error);
            }
        });
        
        console.log(`🎯 RESULTADO FINAL: ${modules.length} módulos completos detectados`);
        
        return modules;
    }

    /**
     * Método antigo - removido para evitar conflitos
     */
    oldDetectModules() {
        // Código antigo movido para evitar conflitos
        const lastRackSelectors = [];
        lastRackSelectors.forEach(selector => {
            try {
                const elements = document.querySelectorAll(selector);
                console.log(`Seletor LastRack "${selector}" encontrou ${elements.length} elementos`);
                
                elements.forEach(element => {
                    // LastRack é válido se tem cremalheira (pode ou não ter seção)
                    const hasSection = element.querySelector('[data-section-id]') !== null;
                    const hasCremalheira = element.querySelector('[data-cremalheira="true"]') !== null;
                    
                    if (hasCremalheira) {
                        allElements.add(element);
                        console.log(`✅ LastRack válido encontrado: cremalheira final (hasSection=${hasSection})`);
                    } else {
                        console.log(`❌ LastRack inválido ignorado: hasCremalheira=${hasCremalheira}`);
                    }
                });
            } catch (error) {
                console.warn(`Erro ao usar seletor LastRack "${selector}":`, error);
            }
        });
        
        // Terceiro: busca seções órfãs (fallback)
        if (allElements.size === 0) {
            console.log('Nenhum wrapper ou LastRack encontrado, tentando fallback...');
            
            fallbackSelectors.forEach(selector => {
                try {
                    const elements = document.querySelectorAll(selector);
                    console.log(`Seletor fallback "${selector}" encontrou ${elements.length} elementos`);
                    elements.forEach(el => allElements.add(el));
                } catch (error) {
                    console.warn(`Erro ao usar seletor fallback "${selector}":`, error);
                }
            });
        }
        
        console.log(`Total de elementos candidatos encontrados: ${allElements.size}`);
        
        // Processa elementos únicos e filtra duplicatas
        const processedElements = [];
        const seenSectionIds = new Set();
        
        Array.from(allElements).forEach((element, index) => {
            try {
                // Identifica o ID da seção
                let sectionId = element.getAttribute('data-section-id');
                
                // Tratamento especial para LastRack (cremalheira final sem seção)
                if (!sectionId && element.hasAttribute('data-last-rack')) {
                    sectionId = 'last-rack';
                    console.log(`🏁 LastRack detectado com ID: ${sectionId}`);
                }
                // Se é um wrapper, busca o ID da seção filha
                else if (!sectionId) {
                    const childSection = element.querySelector('[data-section-id]');
                    if (childSection) {
                        sectionId = childSection.getAttribute('data-section-id');
                        console.log(`🔍 Wrapper com seção filha ID: ${sectionId}`);
                    } else {
                        sectionId = `module-${index}`;
                        console.log(`⚠️  Elemento sem ID, gerado: ${sectionId}`);
                    }
                }
                
                // Evita duplicatas por sectionId
                if (seenSectionIds.has(sectionId)) {
                    console.log(`🚫 Duplicata ignorada (ID: ${sectionId})`);
                    return;
                }
                
                seenSectionIds.add(sectionId);
                
                // Analisa características do elemento
                const hasCremalheira = element.querySelector('[data-cremalheira="true"]') !== null;
                const hasSection = element.querySelector('[data-section-id]') !== null || element.hasAttribute('data-section-id');
                const isLastRackElement = element.hasAttribute('data-last-rack');
                const cremalheiraCount = element.querySelectorAll('[data-cremalheira="true"]').length;
                const sectionCount = element.querySelectorAll('[data-section-id]').length + (element.hasAttribute('data-section-id') ? 1 : 0);
                
                // Determina tipo do módulo
                let moduleType = 'DESCONHECIDO';
                let isValidModule = false;
                
                if (isLastRackElement) {
                    // LastRack: sempre válido se tem cremalheira
                    moduleType = 'LAST_RACK';
                    isValidModule = hasCremalheira; // Sempre aceita LastRack com cremalheira
                    console.log(`🔍 Validação LastRack: hasCremalheira=${hasCremalheira}, hasSection=${hasSection}, isValidModule=${isValidModule}`);
                } else if (hasCremalheira && hasSection && sectionCount === 1) {
                    // Módulo completo: cremalheira + exatamente 1 seção
                    moduleType = 'MÓDULO_COMPLETO';
                    isValidModule = true;
                } else if (hasSection && sectionCount === 1) {
                    // Módulo parcial: só seção, sem cremalheira
                    moduleType = 'MÓDULO_PARCIAL';
                    isValidModule = true;
                }
                
                console.log(`📊 Analisando elemento ${index}:`, {
                    sectionId,
                    moduleType,
                    hasCremalheira,
                    hasSection,
                    cremalheiraCount,
                    sectionCount,
                    isValidModule,
                    dimensions: `${element.offsetWidth}x${element.offsetHeight}`
                });
                
                // Valida se o elemento é visível e tem conteúdo
                const elementValid = this.isElementValid(element);
                if (isLastRackElement) {
                    console.log(`🔍 Debug LastRack: isValidModule=${isValidModule}, elementValid=${elementValid}, dimensions=${element.offsetWidth}x${element.offsetHeight}`);
                }
                
                if (isValidModule && elementValid) {
                    const moduleName = this.extractModuleName(element, processedElements.length);
                    
                    processedElements.push({
                        id: sectionId,
                        name: moduleName,
                        element: element,
                        index: processedElements.length,
                        moduleType: moduleType,
                        hasCremalheira: hasCremalheira,
                        hasSection: hasSection,
                        isLastRack: isLastRackElement,
                        cremalheiraCount: cremalheiraCount,
                        sectionCount: sectionCount
                    });
                    
                    console.log(`✅ ${moduleType} válido: ${moduleName} (ID: ${sectionId})`);
                } else {
                    console.log(`❌ Elemento inválido ignorado: ${moduleType} (ID: ${sectionId})`);
                }
                
            } catch (error) {
                console.error(`Erro ao processar elemento ${index}:`, error);
            }
        });
        
        console.log(`🎯 RESULTADO FINAL: ${processedElements.length} módulos individuais detectados`);
        
        console.log(`🎯 RESULTADO FINAL: ${modules.length} módulos completos detectados`);
        
        return modules;
    }

    /**
     * Cria um container virtual que agrupa os componentes de um módulo completo
     * @param {Object} components - Componentes do módulo (seção + cremaalheiras)
     * @returns {HTMLElement} Container virtual
     */
    createVirtualModuleContainer({ sectionId, sectionIndex, sectionElement, cremalheiraEsquerda, cremalheiraDireita }) {
        console.log(`🏗️  Criando container virtual para módulo ${sectionIndex + 1}...`);
        
        // Cria um container div virtual
        const container = document.createElement('div');
        container.setAttribute('data-virtual-module', 'true');
        container.setAttribute('data-module-id', sectionId);
        container.setAttribute('data-module-index', sectionIndex);
        container.className = 'virtual-module-container flex items-center relative';
        
        // Clona os elementos para o container virtual
        const clonedCremalheiraEsquerda = cremalheiraEsquerda.cloneNode(true);
        const clonedSection = sectionElement.cloneNode(true);
        const clonedCremalheiraDireita = cremalheiraDireita.cloneNode(true);
        
        // Adiciona os componentes na ordem correta
        container.appendChild(clonedCremalheiraEsquerda);
        container.appendChild(clonedSection);
        container.appendChild(clonedCremalheiraDireita);
        
        // Adiciona temporariamente ao DOM para cálculos
        // Posiciona dentro da viewport mas invisível ao usuário
        container.style.position = 'fixed';
        container.style.top = '0px';
        container.style.left = '0px';
        container.style.opacity = '0'; // Invisível ao usuário
        container.style.visibility = 'visible'; // Visível para captura
        container.style.zIndex = '-1000';
        container.style.pointerEvents = 'none'; // Evita interferência
        container.style.transform = 'translateZ(0)'; // Force hardware acceleration
        document.body.appendChild(container);
        
        console.log(`✅ Container virtual criado: ${container.offsetWidth}x${container.offsetHeight}px`);
        
        return container;
    }

    /**
     * Remove containers virtuais criados do DOM
     */
    cleanupVirtualContainers() {
        const virtualContainers = document.querySelectorAll('[data-virtual-module="true"]');
        virtualContainers.forEach(container => {
            if (container.parentNode) {
                container.parentNode.removeChild(container);
            }
        });
        console.log(`🧹 Removidos ${virtualContainers.length} containers virtuais`);
    }

    /**
     * Detecta container principal para planograma completo
     * @returns {HTMLElement|null} Container principal ou null
     */
    detectPlanogramContainer() {
        console.log('=== DETECTANDO CONTAINER PRINCIPAL PARA PLANOGRAMA COMPLETO ===');
        
        // Seletores específicos baseados na estrutura Vue real
        // PRIORIDADE MÁXIMA: ID específico criado para captura de planograma
        const containerSelectors = [
            '#planogram-container-full', // ⭐ ID específico no Sections.vue - PRIORIDADE MÁXIMA
            '[ref="sectionsContainer"]', // Container específico com ref do Sections.vue
            '.mt-28.flex.md\\\\:flex-row', // Container interno dos módulos (Sections.vue linha 5)
            '.flex.flex-col.overflow-auto.relative.w-full', // Container principal (Gondola.vue linha 13)
            '[style*="width: 3618px"]', // Container com largura específica detectada
            '.flex.md\\\\:flex-row', // Container genérico que tem os SectionWrapper + LastRack
        ];
        
        // Busca containers candidatos
        const candidates = [];
        
        containerSelectors.forEach(selector => {
            try {
                const elements = document.querySelectorAll(selector);
                console.log(`🔍 Seletor "${selector}" encontrou ${elements.length} elementos`);
                
                elements.forEach(element => {
                    if (!candidates.includes(element)) {
                        candidates.push(element);
                    }
                });
            } catch (error) {
                console.warn(`⚠️  Erro ao usar seletor "${selector}":`, error);
            }
        });
        
        console.log(`📋 Total de candidatos encontrados: ${candidates.length}`);
        
        // Avalia cada candidato
        let bestContainer = null;
        let bestScore = 0;
        
        candidates.forEach((element, index) => {
            try {
                const sectionCount = element.querySelectorAll('[data-section-id]').length;
                const cremalheiraCount = element.querySelectorAll('[data-cremalheira="true"]').length;
                const wrapperCount = element.querySelectorAll('.flex.items-center.relative').length;
                const lastRackCount = element.querySelectorAll('[data-last-rack="true"]').length;
                
                // Calcula pontuação baseada na estrutura HTML real
                let score = 0;
                
                // 🏆 PRIORIDADE ABSOLUTA: ID específico criado para captura
                if (element.id === 'planogram-container-full') {
                    score += 1000;
                    console.log(`🏆 CONTAINER PERFEITO ENCONTRADO! ID: ${element.id}`);
                }
                
                // PRIORIDADE MÁXIMA: Container com largura específica de 3618px
                else if (element.offsetWidth >= 3600 && element.offsetWidth <= 3650) {
                    score += 200;
                    console.log(`🎯 CONTAINER IDEAL ENCONTRADO! Largura: ${element.offsetWidth}px`);
                }
                
                // Container deve ter TODOS os 9 módulos (seções)
                if (sectionCount >= 9) score += sectionCount * 20;
                else if (sectionCount >= 7) score += sectionCount * 15;
                else if (sectionCount >= 5) score += sectionCount * 10;
                else if (sectionCount >= 2) score += sectionCount * 5;
                
                // Deve ter cremalheiras (9 módulos + 1 LastRack = 10 cremalheiras)
                if (cremalheiraCount >= 10) score += 50;
                else if (cremalheiraCount >= 9) score += 30;
                else if (cremalheiraCount >= 5) score += 15;
                else if (cremalheiraCount >= 1) score += 5;
                
                // Deve ter wrappers (SectionWrapper para cada módulo)
                if (wrapperCount >= 9) score += 40;
                else if (wrapperCount >= 7) score += 25;
                else if (wrapperCount >= 5) score += 10;
                else if (wrapperCount >= 1) score += 3;
                
                // Deve ter LastRack
                if (lastRackCount >= 1) score += 15;
                
                // Bônus por classes específicas da estrutura real
                if (element.classList.contains('overflow-auto')) score += 10;
                if (element.classList.contains('relative')) score += 5;
                if (element.classList.contains('w-full')) score += 5;
                
                // Área deve ser suficiente para todos os módulos
                const area = element.offsetWidth * element.offsetHeight;
                if (area > 2000000) score += 50;
                else if (area > 1500000) score += 30;
                else if (area > 1000000) score += 20;
                else if (area > 500000) score += 10;
                else if (element.offsetWidth > 1000) score += 15;
                else if (element.offsetWidth > 500) score += 5;
                
                // Penaliza se for muito pequeno
                if (element.offsetWidth < 300 || element.offsetHeight < 100) score -= 50;
                
                // Bônus se contém tanto módulos quanto LastRack (planograma completo)
                if (sectionCount >= 9 && lastRackCount >= 1) score += 50;
                else if (sectionCount >= 1 && lastRackCount >= 1) score += 25;
                
                // NOVO: Bônus por tipo de elemento (containers mais amplos)
                const tagName = element.tagName.toLowerCase();
                if (tagName === 'main') score += 40;
                else if (tagName === 'section') score += 20;
                else if (tagName === 'div' && element.className.includes('container')) score += 15;
                
                console.log(`📊 Candidato ${index + 1}:`, {
                    element: element.tagName + (element.className ? `.${element.className.split(' ').join('.')}` : ''),
                    tagName: tagName,
                    sectionCount,
                    cremalheiraCount, 
                    wrapperCount,
                    lastRackCount,
                    dimensions: `${element.offsetWidth}x${element.offsetHeight}`,
                    area: area,
                    score: score,
                    isVisible: this.isElementValid(element)
                });
                
                // Deve ser visível e ter pontuação mínima
                if (score > bestScore && score >= 20 && this.isElementValid(element)) {
                    bestContainer = element;
                    bestScore = score;
                    console.log(`🏆 Novo melhor candidato com pontuação: ${score}`);
                }
                
            } catch (error) {
                console.error(`❌ Erro ao avaliar candidato ${index}:`, error);
            }
        });
        
        if (bestContainer) {
            console.log(`✅ CONTAINER PRINCIPAL ENCONTRADO com pontuação: ${bestScore}`);
            console.log(`📐 Dimensões finais: ${bestContainer.offsetWidth}x${bestContainer.offsetHeight}`);
            
            // Log adicional para debug
            const finalSectionCount = bestContainer.querySelectorAll('[data-section-id]').length;
            const finalCremalheiraCount = bestContainer.querySelectorAll('[data-cremalheira="true"]').length;
            const finalLastRackCount = bestContainer.querySelectorAll('[data-last-rack="true"]').length;
            
            console.log(`📈 Container final contém: ${finalSectionCount} seções, ${finalCremalheiraCount} cremalheiras, ${finalLastRackCount} LastRacks`);
            
            return bestContainer;
        }
        
        console.log('❌ NENHUM CONTAINER PRINCIPAL ADEQUADO ENCONTRADO');
        
        // Fallback: busca containers mais amplos que possam conter todo o planograma
        console.log('🔄 Tentando fallback para containers mais amplos...');
        
        // Busca por containers que possam incluir TODO o planograma
        const fallbackSelectors = [
            'body', // Container mais amplo possível
            '#app', // Container principal da aplicação
            '[id*="app"]', // Qualquer elemento com "app" no ID
            '.app', // Classe app
            '.main-wrapper', // Wrapper principal
            '.content-wrapper', // Wrapper de conteúdo
            'div[class*="wrapper"]', // Qualquer div com wrapper na classe
            'div[class*="container"]', // Qualquer div com container na classe
        ];
        
        for (const selector of fallbackSelectors) {
            try {
                const elements = document.querySelectorAll(selector);
                console.log(`🔄 Fallback seletor "${selector}" encontrou ${elements.length} elementos`);
                
                for (const element of elements) {
                    const sectionCount = element.querySelectorAll('[data-section-id]').length;
                    const area = element.offsetWidth * element.offsetHeight;
                    
                    // Deve conter TODAS as 9 seções e ter área grande
                    if (sectionCount >= 9 && area > 1000000 && this.isElementValid(element)) {
                        console.log(`🆘 FALLBACK AMPLO: Container encontrado!`, {
                            selector: selector,
                            sectionCount: sectionCount,
                            dimensions: `${element.offsetWidth}x${element.offsetHeight}`,
                            area: area
                        });
                        return element;
                    }
                }
            } catch (error) {
                console.warn(`Erro no fallback seletor "${selector}":`, error);
            }
        }
        
        // Último recurso: busca o container com MAIOR número de seções
        console.log('🔄 Último recurso: buscando container com mais seções...');
        
        let bestFallback = null;
        let bestSectionCount = 0;
        
        const allElements = document.querySelectorAll('*');
        for (const element of allElements) {
            const sectionCount = element.querySelectorAll('[data-section-id]').length;
            const area = element.offsetWidth * element.offsetHeight;
            
            if (sectionCount > bestSectionCount && area > 500000 && this.isElementValid(element)) {
                bestFallback = element;
                bestSectionCount = sectionCount;
                
                console.log(`🔍 Novo melhor fallback: ${sectionCount} seções, ${element.offsetWidth}x${element.offsetHeight}`);
            }
        }
        
        if (bestFallback) {
            console.log(`🆘 ÚLTIMO RECURSO: Container com ${bestSectionCount} seções encontrado`);
            return bestFallback;
        }
        
        console.log('💥 TODOS OS FALLBACKS FALHARAM - Nenhum container adequado encontrado');
        return null;
    }

    /**
     * Extrai o nome do módulo de diferentes fontes
     * @param {HTMLElement} element - Elemento do módulo
     * @param {number} index - Índice do módulo
     * @returns {string} Nome do módulo
     */
    extractModuleName(element, index) {
        // Tratamento especial para LastRack
        if (element.hasAttribute('data-last-rack')) {
            return 'Cremalheira Final';
        }
        
        // Lista de seletores para encontrar o nome do módulo
        const nameSelectors = [
            '.module-label',
            '.section-title',
            '.module-title',
            '.planogram-title',
            '[class*="title"]',
            '[class*="label"]',
            'h1, h2, h3, h4, h5, h6'
        ];

        for (const selector of nameSelectors) {
            try {
                const nameElement = element.querySelector(selector);
                if (nameElement) {
                    const text = (nameElement.textContent || nameElement.innerText || '').trim();
                    if (text && text.length > 0) {
                        return text;
                    }
                }
            } catch (error) {
                // Continua tentando outros seletores
            }
        }

        // Se não encontrou nome específico, tenta usar atributos
        const title = element.getAttribute('title') || 
                     element.getAttribute('data-title') || 
                     element.getAttribute('data-name');
        
        if (title && title.trim()) {
            return title.trim();
        }

        // Nome padrão
        return `MÓDULO ${index + 1}`;
    }

    /**
     * Verifica se um elemento é válido para captura
     * @param {HTMLElement} element - Elemento a ser verificado
     * @returns {boolean} Se o elemento é válido
     */
    isElementValid(element) {
        // Tratamento especial para containers virtuais
        const isVirtualModule = element.hasAttribute('data-virtual-module');
        if (isVirtualModule) {
            // Containers virtuais sempre são válidos se têm dimensões
            return element.offsetWidth > 0 && element.offsetHeight > 0;
        }

        // Verifica se o elemento está visível
        const style = window.getComputedStyle(element);
        if (style.display === 'none' || 
            style.visibility === 'hidden' || 
            style.opacity === '0') {
            return false;
        }

        // Tratamento especial para LastRack - dimensões muito menores são aceitáveis
        const isLastRack = element.hasAttribute('data-last-rack');
        const minWidth = isLastRack ? 5 : 50;  // LastRack pode ser muito estreito (apenas cremalheira)
        const minHeight = isLastRack ? 100 : 50; // Mas deve ter altura mínima

        // Verifica se tem tamanho mínimo
        if (element.offsetWidth < minWidth || element.offsetHeight < minHeight) {
            return false;
        }

        // Verifica se não é um container pai demais
        const rect = element.getBoundingClientRect();
        const isInViewport = rect.top < window.innerHeight && rect.bottom > 0;
        
        return isInViewport || rect.width > 0; // Permite elementos fora da viewport se tiverem tamanho
    }

    /**
     * Captura um elemento específico como imagem
     * @param {HTMLElement} element - Elemento a ser capturado
     * @param {Object} config - Configurações de captura
     * @returns {Promise<string>} Data URL da imagem capturada
     */
    async captureElement(element, config = {}) {
        const finalConfig = { ...this.defaultConfig, ...config };
        
        console.log('Iniciando captura do elemento:', element);
        console.log('Dimensões do elemento:', element.offsetWidth, 'x', element.offsetHeight);
        
        try {
            // Garante que o elemento está visível
            this.ensureElementVisible(element);
            
            // Remove elementos desnecessários temporariamente
            const elementsToHide = this.hideUIElements(element);
            
            // Aguarda um pequeno delay para garantir que o DOM se estabilize
            await this.wait(100);
            
            // Configurações do dom-to-image com tratamento melhorado
            const options = {
                quality: finalConfig.quality,
                bgcolor: finalConfig.backgroundColor,
                width: element.offsetWidth * finalConfig.scale,
                height: element.offsetHeight * finalConfig.scale,
                style: {
                    transform: `scale(${finalConfig.scale})`,
                    transformOrigin: 'top left',
                    width: element.offsetWidth + 'px',
                    height: element.offsetHeight + 'px',
                    // 🎯 CONFIGURAÇÃO ANTI-QUEBRA DE LINHA
                    whiteSpace: 'nowrap',
                    overflow: 'visible',
                    textOverflow: 'visible'
                },
                useCORS: true,
                allowTaint: true,
                // Filtro melhorado para lidar com imagens problemáticas e evitar quebra de linha
                filter: (node) => {
                    // Aplica configuração anti-quebra em elementos de texto
                    if (node.textContent && node.textContent.trim() && node.style) {
                        node.style.whiteSpace = 'nowrap';
                        node.style.overflow = 'visible';
                        node.style.textOverflow = 'visible';
                    }
                    
                    if (node.tagName === 'IMG') {
                        const src = node.src || '';
                        // Lista de domínios problemáticos
                        const problematicDomains = [
                            'digitaloceanspaces.com',
                            'amazonaws.com',
                            'cloudflare.com'
                        ];
                        
                        if (problematicDomains.some(domain => src.includes(domain))) {
                            console.log('Ignorando imagem problemática:', src);
                            return false;
                        }
                    }
                    
                    // Ignora elementos completamente transparentes ou invisíveis
                    if (node.style) {
                        const opacity = node.style.opacity;
                        const display = node.style.display;
                        if (opacity === '0' || display === 'none') {
                            return false;
                        }
                    }
                    
                    return true;
                }
            };

            console.log('Configurações de captura:', options);

            // Captura a imagem
            const dataUrl = await domtoimage.toPng(element, options);
            
            console.log('Captura realizada com sucesso');
            
            // Restaura elementos escondidos
            this.showUIElements(elementsToHide);
            
            return dataUrl;
        } catch (error) {
            console.error('Erro ao capturar elemento:', error);
            console.log('Tentando captura alternativa...');
            
            // Tenta captura alternativa sem imagens problemáticas
            try {
                const elementsToHide = this.hideUIElements(element);
                
                // Remove todas as imagens externas temporariamente
                const problematicImages = this.hideProblematicImages(element);

                const fallbackOptions = {
                    quality: Math.max(finalConfig.quality - 0.1, 0.7),
                    bgcolor: finalConfig.backgroundColor,
                    width: element.offsetWidth * (finalConfig.scale * 0.8),
                    height: element.offsetHeight * (finalConfig.scale * 0.8),
                    style: {
                        transform: `scale(${finalConfig.scale * 0.8})`,
                        transformOrigin: 'top left',
                        width: element.offsetWidth + 'px',
                        height: element.offsetHeight + 'px',
                        // 🎯 CONFIGURAÇÃO ANTI-QUEBRA DE LINHA (FALLBACK)
                        whiteSpace: 'nowrap',
                        overflow: 'visible',
                        textOverflow: 'visible'
                    },
                    useCORS: false,
                    allowTaint: false,
                    // Aplica mesma configuração anti-quebra do método principal
                    filter: (node) => {
                        // Aplica configuração anti-quebra em elementos de texto
                        if (node.textContent && node.textContent.trim() && node.style) {
                            node.style.whiteSpace = 'nowrap';
                            node.style.overflow = 'visible';
                            node.style.textOverflow = 'visible';
                        }
                        
                        // Ignora elementos completamente transparentes ou invisíveis
                        if (node.style) {
                            const opacity = node.style.opacity;
                            const display = node.style.display;
                            if (opacity === '0' || display === 'none') {
                                return false;
                            }
                        }
                        
                        return true;
                    }
                };

                console.log('Tentativa de fallback com opções:', fallbackOptions);
                const dataUrl = await domtoimage.toPng(element, fallbackOptions);
                
                // Restaura elementos
                this.showUIElements(elementsToHide);
                this.showUIElements(problematicImages);
                
                console.log('Captura de fallback bem-sucedida');
                return dataUrl;
            } catch (fallbackError) {
                console.error('Erro na captura de fallback:', fallbackError);
                throw new Error(`Falha na captura do módulo: ${fallbackError.message || 'Erro desconhecido'}`);
            }
        }
    }

    /**
     * Garante que o elemento esteja visível para captura
     * @param {HTMLElement} element - Elemento a ser tornado visível
     */
    ensureElementVisible(element) {
        // Se o elemento não está na viewport, rola para ele
        const rect = element.getBoundingClientRect();
        const isInViewport = rect.top >= 0 && rect.bottom <= window.innerHeight;
        
        if (!isInViewport) {
            element.scrollIntoView({ behavior: 'auto', block: 'center' });
        }

        // Força visibilidade se necessário (temporariamente)
        const originalStyle = {
            visibility: element.style.visibility,
            display: element.style.display,
            opacity: element.style.opacity
        };

        if (element.style.visibility === 'hidden') element.style.visibility = 'visible';
        if (element.style.display === 'none') element.style.display = 'block';
        if (element.style.opacity === '0') element.style.opacity = '1';
        
        // Para containers virtuais, força opacidade total durante captura
        if (element.hasAttribute('data-virtual-module')) {
            element.style.opacity = '1';
        }

        // Armazena estilo original para restaurar depois
        element._originalCaptureStyle = originalStyle;
    }

    /**
     * Esconde imagens problemáticas para captura
     * @param {HTMLElement} container - Container principal
     * @returns {Array} Imagens que foram escondidas
     */
    hideProblematicImages(container) {
        const hiddenImages = [];
        const images = container.querySelectorAll('img');
        
        images.forEach(img => {
            const src = img.src || '';
            const problematicDomains = [
                'digitaloceanspaces.com',
                'amazonaws.com',
                'cloudfront.net',
                'cloudflare.com'
            ];
            
            if (problematicDomains.some(domain => src.includes(domain))) {
                hiddenImages.push({
                    element: img,
                    originalDisplay: img.style.display,
                    originalVisibility: img.style.visibility
                });
                img.style.display = 'none';
            }
        });
        
        return hiddenImages;
    }

    /**
     * Aguarda um tempo específico
     * @param {number} ms - Milissegundos para aguardar
     */
    async wait(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Captura múltiplos módulos ou planograma completo
     * @param {Array} moduleIds - IDs dos módulos a serem capturados ou ['all'] para planograma completo
     * @param {Object} config - Configurações de captura
     * @returns {Promise<Array>} Array de imagens capturadas
     */
    async captureModules(moduleIds, config = {}) {
        console.log('=== INICIANDO CAPTURA DE MÓDULOS ===');
        console.log('IDs solicitados:', moduleIds);
        
        let selectedModules = [];
        
        // LÓGICA SEPARADA: Planograma Completo vs Módulos Individuais
        if (moduleIds.includes('all')) {
            console.log('🎯 MODO: PLANOGRAMA COMPLETO');
            
            // Detecta container principal para planograma completo
            const planogramContainer = this.detectPlanogramContainer();
            
            if (planogramContainer) {
                selectedModules = [{
                    element: planogramContainer,
                    name: 'PLANOGRAMA COMPLETO',
                    id: 'planogram-complete',
                    index: 0,
                    moduleType: 'PLANOGRAM_COMPLETE',
                    isComplete: true
                }];
                
                console.log('✅ Container principal encontrado para planograma completo');
                console.log(`📐 Dimensões: ${planogramContainer.offsetWidth}x${planogramContainer.offsetHeight}`);
            } else {
                console.log('❌ Container principal não encontrado');
                console.log('🔄 FALLBACK: Tentando capturar todos os módulos individuais');
                
                // Fallback: captura todos os módulos individuais
                const individualModules = this.detectModules();
                if (individualModules.length > 0) {
                    selectedModules = individualModules;
                    console.log(`🆘 Usando ${individualModules.length} módulos individuais como fallback`);
                } else {
                    throw new Error('Nenhum módulo ou container principal encontrado para captura');
                }
            }
        } else {
            console.log('🎯 MODO: MÓDULOS INDIVIDUAIS');
            
            // Detecta módulos individuais
            const availableModules = this.detectModules();
            console.log(`📋 Módulos disponíveis: ${availableModules.length}`);
            
            if (availableModules.length === 0) {
                throw new Error('Nenhum módulo individual detectado');
            }
            
            // Filtra módulos selecionados
            selectedModules = availableModules.filter(module => moduleIds.includes(module.id));
            
            if (selectedModules.length === 0) {
                console.warn('⚠️  Nenhum módulo correspondente aos IDs solicitados');
                console.log('IDs disponíveis:', availableModules.map(m => m.id));
                throw new Error(`Módulos não encontrados. IDs solicitados: ${moduleIds.join(', ')}`);
            }
            
            console.log(`✅ ${selectedModules.length} módulos selecionados para captura`);
        }
        
        // PROCESSO DE CAPTURA
        console.log(`🚀 Iniciando captura de ${selectedModules.length} elemento(s)`);
        const captures = [];
        
        for (let i = 0; i < selectedModules.length; i++) {
            const module = selectedModules[i];
            const progressMsg = `Capturando ${i + 1}/${selectedModules.length}: ${module.name}`;
            console.log(`📸 ${progressMsg}`);
            
            try {
                // Validação adicional antes da captura
                if (!module.element || !this.isElementValid(module.element)) {
                    throw new Error('Elemento inválido ou não visível');
                }
                
                // Captura a imagem
                const imageData = await this.captureElement(module.element, config);
                
                if (!imageData) {
                    throw new Error('Captura retornou dados vazios');
                }
                
                captures.push({
                    id: module.id,
                    name: module.name,
                    imageData: imageData,
                    element: module.element,
                    moduleType: module.moduleType || 'UNKNOWN',
                    capturedAt: new Date().toISOString()
                });
                
                console.log(`✅ ${module.name} capturado com sucesso (${imageData.length} bytes)`);
                
            } catch (error) {
                console.error(`❌ Erro ao capturar ${module.name}:`, error);
                
                // Adiciona entrada de erro para manter consistência
                captures.push({
                    id: module.id,
                    name: `${module.name} (ERRO)`,
                    imageData: null,
                    element: module.element,
                    moduleType: module.moduleType || 'UNKNOWN',
                    error: error.message,
                    capturedAt: new Date().toISOString()
                });
            }
        }
        
        // RESULTADO FINAL
        const successfulCaptures = captures.filter(c => c.imageData !== null);
        const failedCaptures = captures.filter(c => c.imageData === null);
        
        console.log(`🎯 CAPTURA CONCLUÍDA:`);
        console.log(`✅ Sucessos: ${successfulCaptures.length}`);
        console.log(`❌ Falhas: ${failedCaptures.length}`);
        console.log(`📊 Total processado: ${captures.length}`);
        
        if (failedCaptures.length > 0) {
            console.warn('⚠️  Módulos com falha:', failedCaptures.map(c => c.name));
        }
        
        // Limpa containers virtuais criados
        this.cleanupVirtualContainers();
        
        return captures;
    }

    /**
     * Gera PDF com as imagens capturadas
     * @param {Array} captures - Imagens capturadas
     * @param {Object} config - Configurações do PDF
     * @returns {Promise<jsPDF>} Documento PDF gerado
     */
    async generatePDF(captures, config = {}) {
        const finalConfig = { ...this.defaultConfig, ...config };
        
        console.log('Gerando PDF com', captures.length, 'capturas');
        
        // Configurações do PDF baseadas no formato
        const pdfConfig = this.getPDFDimensions(finalConfig.format, finalConfig.orientation);
        const pdf = new jsPDF({
            orientation: finalConfig.orientation,
            unit: 'mm',
            format: finalConfig.format.toLowerCase()
        });

        // Remove a primeira página em branco
        let isFirstPage = true;

        for (let i = 0; i < captures.length; i++) {
            const capture = captures[i];
            console.log(`Adicionando página ${i + 1}: ${capture.name}`);
            
            if (!isFirstPage) {
                pdf.addPage();
            }
            isFirstPage = false;

            // Adiciona título do módulo
            pdf.setFontSize(16);
            pdf.setFont(undefined, 'bold');
            pdf.text(capture.name, finalConfig.margins.left, finalConfig.margins.top);

            // Se houve erro na captura, adiciona mensagem de erro
            if (capture.error || !capture.imageData) {
                pdf.setFontSize(12);
                pdf.setFont(undefined, 'normal');
                const errorMsg = capture.error || 'Erro desconhecido ao capturar módulo';
                pdf.text(`Erro: ${errorMsg}`, finalConfig.margins.left, finalConfig.margins.top + 10);
                continue;
            }

            try {
            // Calcula dimensões para ajustar a imagem na página
            const availableWidth = pdfConfig.width - finalConfig.margins.left - finalConfig.margins.right;
                const availableHeight = pdfConfig.height - finalConfig.margins.top - finalConfig.margins.bottom - 15; // 15mm para título e espaçamento

            // Cria uma nova imagem para obter dimensões
            const img = new Image();
            await new Promise((resolve, reject) => {
                img.onload = resolve;
                img.onerror = reject;
                img.src = capture.imageData;
            });

            // Calcula escala para ajustar na página mantendo proporção
                const pixelToMM = 0.264583; // Conversão px para mm
                const scaleX = availableWidth / (img.width * pixelToMM);
                const scaleY = availableHeight / (img.height * pixelToMM);
                const scale = Math.min(scaleX, scaleY, 1); // Não aumenta além do tamanho original

                const finalWidth = (img.width * pixelToMM) * scale;
                const finalHeight = (img.height * pixelToMM) * scale;

            // Centraliza a imagem
            const x = finalConfig.margins.left + (availableWidth - finalWidth) / 2;
                const y = finalConfig.margins.top + 15; // 15mm abaixo do título

                console.log(`Dimensões da imagem no PDF: ${finalWidth}x${finalHeight}mm na posição ${x},${y}`);

            pdf.addImage(
                capture.imageData,
                'PNG',
                x,
                y,
                finalWidth,
                finalHeight
            );
            } catch (error) {
                console.error(`Erro ao adicionar imagem ao PDF para ${capture.name}:`, error);
                // Adiciona mensagem de erro no PDF
                pdf.setFontSize(12);
                pdf.setFont(undefined, 'normal');
                pdf.text(`Erro ao processar imagem: ${error.message}`, finalConfig.margins.left, finalConfig.margins.top + 15);
            }
        }

        console.log('PDF gerado com sucesso');
        return pdf;
    }

    /**
     * Imprime diretamente no navegador
     * @param {Array} captures - Imagens capturadas
     * @param {Object} config - Configurações de impressão
     */
    async printDirect(captures, config = {}) {
        console.log('Iniciando impressão direta de', captures.length, 'módulos');
        
        const printWindow = window.open('', '_blank');
        if (!printWindow) {
            throw new Error('Não foi possível abrir janela de impressão. Verifique se pop-ups estão bloqueados.');
        }

        const finalConfig = { ...this.defaultConfig, ...config };
        
        // Gera HTML para impressão
        let htmlContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Impressão do Planograma</title>
                <style>
                    @page {
                        size: ${finalConfig.format.toLowerCase()} ${finalConfig.orientation};
                        margin: ${finalConfig.margins.top}mm ${finalConfig.margins.right}mm ${finalConfig.margins.bottom}mm ${finalConfig.margins.left}mm;
                    }
                    body {
                        margin: 0;
                        padding: 0;
                        font-family: Arial, sans-serif;
                        background: white;
                    }
                    .module-page {
                        page-break-after: always;
                        text-align: center;
                        background: white;
                        min-height: auto;
                        display: flex;
                        flex-direction: column;
                        justify-content: flex-start;
                        align-items: center;
                        padding: 10mm;
                        height: fit-content;
                    }
                    .module-page:last-child {
                        page-break-after: avoid;
                        min-height: auto;
                    }
                    .module-title {
                        font-size: 18px;
                        font-weight: bold;
                        margin-bottom: 15px;
                        color: #333;
                        text-align: center;
                    }
                    .module-image {
                        max-width: 100%;
                        max-height: 80vh;
                        height: auto;
                        border: 1px solid #ddd;
                        border-radius: 4px;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    }
                    .error-message {
                        color: #d32f2f;
                        font-size: 14px;
                        text-align: center;
                        margin-top: 20px;
                        padding: 10px;
                        border: 1px solid #d32f2f;
                        border-radius: 4px;
                        background-color: #ffebee;
                    }
                    @media print {
                        .module-page {
                            page-break-after: always;
                            min-height: auto !important;
                            height: auto !important;
                        }
                        .module-page:last-child {
                            page-break-after: avoid !important;
                        }
                        body {
                            print-color-adjust: exact;
                            -webkit-print-color-adjust: exact;
                            margin: 0 !important;
                            padding: 0 !important;
                        }
                        @page {
                            margin: 10mm;
                            size: auto;
                        }
                    }
                </style>
            </head>
            <body>
        `;

        captures.forEach((capture, index) => {
            htmlContent += `<div class="module-page">`;
            htmlContent += `<h1 class="module-title">${capture.name}</h1>`;
            
            if (capture.imageData && !capture.error) {
                htmlContent += `<img src="${capture.imageData}" alt="${capture.name}" class="module-image" />`;
            } else {
                const errorMsg = capture.error || 'Erro desconhecido ao capturar módulo';
                htmlContent += `<div class="error-message">Erro: ${errorMsg}</div>`;
            }
            
            htmlContent += `</div>`;
        });

        htmlContent += `</body></html>`;

        printWindow.document.write(htmlContent);
        printWindow.document.close();

        // Aguarda o carregamento das imagens antes de imprimir
        setTimeout(() => {
            printWindow.focus();
            printWindow.print();
            
            // Fecha a janela após impressão (opcional)
            setTimeout(() => {
            printWindow.close();
        }, 1000);
        }, 2000); // Aumentado o tempo para garantir carregamento completo
    }

    /**
     * Remove elementos desnecessários da UI durante a captura
     * @param {HTMLElement} container - Container principal
     * @returns {Array} Elementos que foram escondidos
     */
    hideUIElements(container) {
        const elementsToHide = [];
        
        // Lista expandida de elementos para esconder durante captura
        const hideSelectors = [
            '.opacity-0',
            '.group-hover\\:opacity-100',
            '[class*="hover:"]',
            '.context-menu',
            '.tooltip',
            '.popover',
            '.modal',
            '.dropdown',
            '.overlay',
            '[aria-hidden="true"]',
            '.hidden',
            '.invisible',
            '.sr-only',
            '[style*="display: none"]',
            '[style*="visibility: hidden"]'
        ];

        hideSelectors.forEach(selector => {
            try {
            const elements = container.querySelectorAll(selector);
            elements.forEach(element => {
                    if (element.style.display !== 'none' && 
                        element.style.visibility !== 'hidden') {
                    elementsToHide.push({
                        element: element,
                            originalDisplay: element.style.display,
                            originalVisibility: element.style.visibility
                    });
                    element.style.display = 'none';
                }
            });
            } catch (error) {
                console.warn(`Erro ao aplicar seletor ${selector}:`, error);
            }
        });
        
        console.log(`Escondidos ${elementsToHide.length} elementos para captura`);
        return elementsToHide;
    }

    /**
     * Restaura elementos escondidos após a captura
     * @param {Array} elementsToShow - Elementos a serem restaurados
     */
    showUIElements(elementsToShow) {
        elementsToShow.forEach(({ element, originalDisplay, originalVisibility, originalOpacity }) => {
            if (originalDisplay !== undefined) {
            element.style.display = originalDisplay;
            }
            if (originalVisibility !== undefined) {
                element.style.visibility = originalVisibility;
            }
            if (originalOpacity !== undefined) {
                element.style.opacity = originalOpacity;
            }
        });
        
        // Restaura estilos originais de captura se existirem
        elementsToShow.forEach(({ element }) => {
            if (element._originalCaptureStyle) {
                Object.assign(element.style, element._originalCaptureStyle);
                delete element._originalCaptureStyle;
            }
        });
        
        console.log(`🔓 Restaurados ${elementsToShow.length} elementos após captura`);
    }

    /**
     * Obtém dimensões do PDF baseadas no formato
     * @param {string} format - Formato do papel (A4, A3, etc.)
     * @param {string} orientation - Orientação (portrait, landscape)
     * @returns {Object} Dimensões em mm
     */
    getPDFDimensions(format, orientation) {
        const dimensions = {
            A4: { width: 210, height: 297 },
            A3: { width: 297, height: 420 },
            A5: { width: 148, height: 210 },
            letter: { width: 216, height: 279 }
        };

        let dim = dimensions[format.toUpperCase()] || dimensions.A4;
        
        if (orientation === 'landscape') {
            return { width: dim.height, height: dim.width };
        }
        
        return dim;
    }

    /**
     * Valida se o navegador suporta as funcionalidades necessárias
     * @returns {Object} Status de compatibilidade
     */
    checkBrowserCompatibility() {
        const compatibility = {
            domToImage: typeof domtoimage !== 'undefined',
            canvas: !!document.createElement('canvas').getContext,
            download: 'download' in document.createElement('a'),
            print: typeof window.print === 'function'
        };

        compatibility.supported = Object.values(compatibility).every(Boolean);
        
        return compatibility;
    }
}

// Instância singleton
export const printService = new PrintService();

// Métodos de teste para debug (podem ser removidos em produção)
if (typeof window !== 'undefined') {
    window.debugPrint = {
        detectModules: () => printService.detectModules(),
        visualizeElements: () => printService.debugVisualizeElements(),
        clearDebug: () => printService.clearDebugVisualization(),
        testCapture: async (moduleIndex = 0) => {
            const modules = printService.detectModules();
            if (modules[moduleIndex]) {
                const capture = await printService.captureElement(modules[moduleIndex].element);
                const img = new Image();
                img.src = capture;
                img.style.maxWidth = '500px';
                img.style.border = '2px solid red';
                document.body.appendChild(img);
                return capture;
            }
            console.log('Módulo não encontrado:', moduleIndex);
        }
    };
}