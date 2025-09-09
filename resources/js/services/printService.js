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
        
        // Cache para fluxo detectado - evita detecções inconsistentes
        this._detectedFlow = null;
        this._flowDetectionTimestamp = null;
        this._flowCacheTimeout = 5000; // 5 segundos de cache
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
        
        // Filtrar seções únicas para evitar duplicatas
        const uniqueSections = [];
        const seenIds = new Set();
        
        sections.forEach(section => {
            const sectionId = section.getAttribute('data-section-id');
            if (!seenIds.has(sectionId)) {
                seenIds.add(sectionId);
                uniqueSections.push(section);
            }
        });
        
        console.log(`🔍 Seções únicas após filtro: ${uniqueSections.length}`);
        
        if (uniqueSections.length === 0) {
            console.warn('❌ Nenhuma seção única encontrada no planograma!');
            return [];
        }
        
        
        // Detecta o fluxo da gôndola
        const flow = this.detectGondolaFlow();
        console.log(`🌊 Fluxo detectado: ${flow}`);
        
        // Segundo: para cada seção única, monta o módulo completo
        uniqueSections.forEach((sectionElement, index) => {
            const sectionId = sectionElement.getAttribute('data-section-id');
            const sectionIndex = index; // Índice da seção (0-8)
            
            // Calcula o número do módulo baseado no fluxo
            const moduleNumber = this.calculateModuleNumber(sectionIndex, uniqueSections.length, flow);
            
            console.log(`🔧 Montando módulo ${moduleNumber} (seção: ${sectionId}, índice: ${sectionIndex}, fluxo: ${flow})...`);
            
            try {
                // Busca cremalheira esquerda (índice atual)
                const cremalheiraEsquerda = document.querySelector(`[data-cremalheira-index="${sectionIndex}"]`);
                
                // Busca cremalheira direita (próximo índice ou LastRack)
                let cremalheiraDireita;
                if (sectionIndex === uniqueSections.length - 1) {
                    // Último módulo: usa LastRack como cremalheira direita
                    cremalheiraDireita = document.querySelector('[data-last-rack="true"] [data-cremalheira="true"]');
                    console.log(`📍 Módulo ${moduleNumber}: Usando LastRack como cremalheira direita`);
                } else {
                    // Módulos normais: usa próxima cremalheira
                    cremalheiraDireita = document.querySelector(`[data-cremalheira-index="${sectionIndex + 1}"]`);
                    console.log(`📍 Módulo ${moduleNumber}: Usando cremalheira ${sectionIndex + 1} como direita`);
                }
                
                // Valida se encontrou todos os componentes necessários
                if (!sectionElement) {
                    console.warn(`❌ Módulo ${moduleNumber}: Seção não encontrada`);
                    return;
                }
                
                if (!cremalheiraEsquerda) {
                    console.warn(`❌ Módulo ${moduleNumber}: Cremalheira esquerda (${sectionIndex}) não encontrada`);
                    return;
                }
                
                if (!cremalheiraDireita) {
                    console.warn(`❌ Módulo ${moduleNumber}: Cremalheira direita não encontrada`);
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
                        name: `Módulo ${moduleNumber}`,
                        element: moduleContainer,
                        moduleType: 'COMPLETE_MODULE',
                        hasCremalheira: true,
                        hasSection: true,
                        cremalheiraCount: 2, // Esquerda + direita
                        sectionCount: 1,
                        isValid: true,
                        sectionIndex: sectionIndex,
                        moduleNumber: moduleNumber,
                        flow: flow,
                        components: {
                            section: sectionElement,
                            cremalheiraEsquerda,
                            cremalheiraDireita
                        }
                    };
                    
                    modules.push(moduleData);
                    console.log(`✅ Módulo ${moduleNumber} criado com sucesso - Nome: "${moduleData.name}"`);
                } else {
                    console.warn(`❌ Módulo ${moduleNumber}: Container virtual inválido`);
                }
                
            } catch (error) {
                console.error(`❌ Erro ao montar módulo ${moduleNumber}:`, error);
            }
        });
        
        // SEMPRE ordena do Módulo 1 para o último, independente do fluxo
        // O fluxo só afeta a posição física, não a ordem no relatório
        modules.sort((a, b) => a.moduleNumber - b.moduleNumber);
        
        if (flow === 'right_to_left') {
            console.log(`🔄 Fluxo right_to_left: Relatório sempre Módulo 1, 2, 3... (fisicamente: último, penúltimo, antepenúltimo...)`);
        } else {
            console.log(`➡️  Fluxo left_to_right: Relatório sempre Módulo 1, 2, 3... (fisicamente: primeiro, segundo, terceiro...)`);
        }
        
        // Valida a consistência da detecção
        const validation = this.validateModuleDetection(modules);
        
        if (!validation.isValid) {
            console.error('❌ Problemas críticos na detecção de módulos:', validation.issues);
        }
        
        if (validation.warnings.length > 0) {
            console.warn('⚠️ Avisos na detecção de módulos:', validation.warnings);
        }
        
        console.log(`🎯 RESULTADO FINAL: ${modules.length} módulos completos detectados`);
        console.log(`📋 Ordem dos módulos:`, modules.map(m => `${m.name} (índice: ${m.sectionIndex})`));
        
        return modules;
    }

    /**
     * Detecta o fluxo da gôndola baseado nos elementos do DOM
     * Usa cache para evitar detecções inconsistentes
     * @returns {string} 'left_to_right' ou 'right_to_left'
     */
    detectGondolaFlow() {
        // Verifica se há cache válido
        const now = Date.now();
        if (this._detectedFlow && this._flowDetectionTimestamp && 
            (now - this._flowDetectionTimestamp) < this._flowCacheTimeout) {
            console.log(`🔄 Usando fluxo em cache: ${this._detectedFlow}`);
            return this._detectedFlow;
        }
        
        console.log('🔍 Detectando fluxo da gôndola...');
        
        // MÉTODO 1: Tenta encontrar o FlowIndicator no DOM
        const flowIndicator = document.querySelector('[class*="flow"]') || 
                             document.querySelector('[data-flow]') ||
                             document.querySelector('.flow-indicator');
        
        if (flowIndicator) {
            // Verifica se há seta para a direita (left_to_right)
            const rightArrow = flowIndicator.querySelector('[class*="arrow-right"]') || 
                              flowIndicator.querySelector('.arrow-right') ||
                              flowIndicator.querySelector('[data-arrow="right"]');
            
            // Verifica se há seta para a esquerda (right_to_left)
            const leftArrow = flowIndicator.querySelector('[class*="arrow-left"]') || 
                             flowIndicator.querySelector('.arrow-left') ||
                             flowIndicator.querySelector('[data-arrow="left"]');
            
            if (rightArrow && !leftArrow) {
                this._cacheFlow('left_to_right', 'seta direita encontrada');
                return this._detectedFlow;
            } else if (leftArrow && !rightArrow) {
                this._cacheFlow('right_to_left', 'seta esquerda encontrada');
                return this._detectedFlow;
            }
        }
        
        // MÉTODO 2: Verifica elementos com classes específicas de fluxo
        const rightToLeftElements = document.querySelectorAll('[class*="right-to-left"], [class*="right_to_left"]');
        const leftToRightElements = document.querySelectorAll('[class*="left-to-right"], [class*="left_to_right"]');
        
        if (rightToLeftElements.length > leftToRightElements.length) {
            this._cacheFlow('right_to_left', 'elementos com classe right-to-left encontrados');
            return this._detectedFlow;
        } else if (leftToRightElements.length > rightToLeftElements.length) {
            this._cacheFlow('left_to_right', 'elementos com classe left-to-right encontrados');
            return this._detectedFlow;
        }
        
        // MÉTODO 3: Análise mais robusta do posicionamento das seções
        const sections = document.querySelectorAll('[data-section-id]');
        if (sections.length > 1) {
            // Coleta posições de todas as seções para análise mais precisa
            const sectionPositions = Array.from(sections).map(section => {
                const rect = section.getBoundingClientRect();
                return {
                    element: section,
                    left: rect.left,
                    right: rect.right,
                    center: rect.left + (rect.width / 2)
                };
            });
            
            // Ordena por posição horizontal
            sectionPositions.sort((a, b) => a.left - b.left);
            
            // Analisa o padrão de posicionamento
            const firstSection = sectionPositions[0];
            const lastSection = sectionPositions[sectionPositions.length - 1];
            
            // Calcula a diferença de posição
            const positionDiff = lastSection.left - firstSection.left;
            
            // Se a diferença é significativa e positiva, é left_to_right
            if (positionDiff > 50) { // 50px de tolerância
                this._cacheFlow('left_to_right', 'posicionamento das seções (análise robusta)');
                return this._detectedFlow;
            } else if (positionDiff < -50) {
                this._cacheFlow('right_to_left', 'posicionamento das seções (análise robusta)');
                return this._detectedFlow;
            }
        }
        
        // MÉTODO 4: Análise de atributos data-section-id para determinar ordem
        const sectionIds = Array.from(sections).map(s => s.getAttribute('data-section-id'));
        if (sectionIds.length > 1) {
            // Se os IDs seguem um padrão sequencial, assume left_to_right
            const hasSequentialPattern = sectionIds.every((id, index) => {
                if (index === 0) return true;
                // Verifica se há algum padrão nos IDs
                return id && sectionIds[index - 1];
            });
            
            if (hasSequentialPattern) {
                this._cacheFlow('left_to_right', 'padrão sequencial dos IDs das seções');
                return this._detectedFlow;
            }
        }
        
        // Padrão: left_to_right
        this._cacheFlow('left_to_right', 'padrão padrão (fallback)');
        return this._detectedFlow;
    }
    
    /**
     * Armazena o fluxo detectado no cache
     * @param {string} flow - Fluxo detectado
     * @param {string} method - Método usado para detecção
     */
    _cacheFlow(flow, method) {
        this._detectedFlow = flow;
        this._flowDetectionTimestamp = Date.now();
        console.log(`✅ Fluxo detectado: ${flow} (${method})`);
        console.log(`💾 Fluxo armazenado em cache por ${this._flowCacheTimeout}ms`);
    }
    
    /**
     * Limpa o cache de fluxo (útil para forçar nova detecção)
     */
    clearFlowCache() {
        this._detectedFlow = null;
        this._flowDetectionTimestamp = null;
        console.log('🗑️ Cache de fluxo limpo');
    }
    
    /**
     * Valida a consistência da detecção de módulos
     * @param {Array} modules - Array de módulos detectados
     * @returns {Object} Resultado da validação
     */
    validateModuleDetection(modules) {
        const validation = {
            isValid: true,
            issues: [],
            warnings: []
        };
        
        if (!modules || modules.length === 0) {
            validation.isValid = false;
            validation.issues.push('Nenhum módulo detectado');
            return validation;
        }
        
        // Verifica se todos os módulos têm elementos válidos
        const invalidModules = modules.filter(module => !module.element || !this.isElementValid(module.element));
        if (invalidModules.length > 0) {
            validation.warnings.push(`${invalidModules.length} módulos com elementos inválidos`);
        }
        
        // Verifica se há módulos duplicados
        const moduleIds = modules.map(m => m.id);
        const uniqueIds = [...new Set(moduleIds)];
        if (moduleIds.length !== uniqueIds.length) {
            validation.isValid = false;
            validation.issues.push('Módulos duplicados detectados');
        }
        
        // Verifica se a numeração dos módulos está correta
        const moduleNumbers = modules.map(m => m.moduleNumber).sort((a, b) => a - b);
        const expectedNumbers = Array.from({length: modules.length}, (_, i) => i + 1);
        const hasCorrectNumbering = moduleNumbers.every((num, index) => num === expectedNumbers[index]);
        
        if (!hasCorrectNumbering) {
            validation.warnings.push('Numeração dos módulos pode estar incorreta');
        }
        
        // Verifica se todos os módulos têm o mesmo fluxo
        const flows = modules.map(m => m.flow);
        const uniqueFlows = [...new Set(flows)];
        if (uniqueFlows.length > 1) {
            validation.isValid = false;
            validation.issues.push('Fluxos inconsistentes detectados entre módulos');
        }
        
        console.log('🔍 Validação de módulos:', validation);
        return validation;
    }

    /**
     * Calcula o número do módulo baseado no fluxo da gôndola
     * @param {number} sectionIndex - Índice da seção (0-8)
     * @param {number} totalSections - Total de seções
     * @param {string} flow - Fluxo da gôndola
     * @returns {number} Número do módulo (1-9)
     */
    calculateModuleNumber(sectionIndex, totalSections, flow) {
        if (flow === 'right_to_left') {
            // Fluxo da direita para esquerda: INVERTE a numeração
            // Seção 0 (direita) vira Módulo 6, Seção 1 vira Módulo 5, etc.
            const moduleNumber = totalSections - sectionIndex;
            console.log(`🔄 Fluxo right_to_left: Seção ${sectionIndex} (direita) -> Módulo ${moduleNumber} (INVERTIDO)`);
            return moduleNumber;
        } else {
            // Fluxo da esquerda para direita: NÃO inverte (numeração normal)
            // Seção 0 vira Módulo 1, seção 1 vira Módulo 2, etc.
            const moduleNumber = sectionIndex + 1;
            console.log(`➡️  Fluxo left_to_right: Seção ${sectionIndex} (esquerda) -> Módulo ${moduleNumber} (NORMAL)`);
            return moduleNumber;
        }
    }


    /**
     * Cria um container virtual otimizado para FlowIndicator + Planograma completo
     * @param {HTMLElement} flowIndicator - Elemento FlowIndicator
     * @param {HTMLElement} planogramContainer - Container do planograma
     * @returns {HTMLElement} Container virtual otimizado
     */
    createOptimizedPlanogramContainer(flowIndicator, planogramContainer) {
        console.log('🏗️  Criando container otimizado para FlowIndicator + Planograma...');
        
        // Cria container virtual otimizado
        const container = document.createElement('div');
        container.setAttribute('data-virtual-planogram', 'true');
        container.className = 'virtual-planogram-container flex flex-col';
        
        // Clona os elementos
        const clonedFlowIndicator = flowIndicator.cloneNode(true);
        const clonedPlanogram = planogramContainer.cloneNode(true);
        
        // Ajusta estilos do FlowIndicator clonado para não ter posicionamento absoluto
        const flowText = clonedFlowIndicator.querySelector('p');
        if (flowText) {
            flowText.style.position = 'relative';
            flowText.style.top = 'auto';
            flowText.style.left = 'auto';
            flowText.style.right = 'auto';
            flowText.style.marginBottom = '10px';
        }
        
        // Adiciona elementos na ordem correta
        container.appendChild(clonedFlowIndicator);
        container.appendChild(clonedPlanogram);
        
        // Calcula largura otimizada baseada no planograma
        const planogramWidth = planogramContainer.scrollWidth || planogramContainer.offsetWidth;
        const optimizedWidth = planogramWidth + 20; // +20px margem pequena
        
        // Aplica estilos para garantir dimensões corretas
        container.style.width = optimizedWidth + 'px';
        container.style.minWidth = optimizedWidth + 'px';
        container.style.maxWidth = optimizedWidth + 'px';
        container.style.height = 'auto';
        container.style.minHeight = '200px'; // Altura mínima garantida
        
        // Posiciona temporariamente no DOM
        container.style.position = 'fixed';
        container.style.top = '0px';
        container.style.left = '0px';
        container.style.opacity = '0';
        container.style.visibility = 'visible';
        container.style.zIndex = '-1000';
        container.style.pointerEvents = 'none';
        container.style.transform = 'translateZ(0)';
        container.style.display = 'flex';
        container.style.flexDirection = 'column';
        
        document.body.appendChild(container);
        
        // Aguarda um momento para o DOM se ajustar
        setTimeout(() => {
            console.log(`✅ Container otimizado criado: ${container.offsetWidth}x${container.offsetHeight}px`);
            console.log(`📏 Planograma original: ${planogramWidth}px, Otimizado: ${optimizedWidth}px`);
        }, 10);
        
        return container;
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
        const virtualModules = document.querySelectorAll('[data-virtual-module="true"]');
        const virtualPlanograms = document.querySelectorAll('[data-virtual-planogram="true"]');
        
        [...virtualModules, ...virtualPlanograms].forEach(container => {
            if (container.parentNode) {
                container.parentNode.removeChild(container);
            }
        });
        
        const totalRemoved = virtualModules.length + virtualPlanograms.length;
        console.log(`🧹 Removidos ${totalRemoved} containers virtuais (${virtualModules.length} módulos + ${virtualPlanograms.length} planogramas)`);
    }

    /**
     * Detecta container principal para planograma completo
     * @returns {HTMLElement|null} Container principal ou null
     */
    detectPlanogramContainer() {
        console.log('=== DETECTANDO CONTAINER PRINCIPAL PARA PLANOGRAMA COMPLETO ===');
        
        // Seletores específicos baseados na estrutura Vue real
        // PRIORIDADE MÁXIMA: Container que inclui FlowIndicator + Planograma
        const containerSelectors = [
            '.flex.flex-col.overflow-auto.relative.w-full', // ⭐ Container principal (Gondola.vue linha 13) - INCLUI FlowIndicator + Sections
            '#planogram-container-full', // Container específico no Sections.vue (apenas módulos)
            '[ref="sectionsContainer"]', // Container específico com ref do Sections.vue
            '.mt-28.flex.md\\\\:flex-row', // Container interno dos módulos (Sections.vue linha 5)
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
                
                // 🏆 PRIORIDADE ABSOLUTA: Container que inclui FlowIndicator + Planograma
                // Busca pelo FlowIndicator usando seletores específicos do FlowIndicator.vue
                const hasFlowIndicator = element.querySelector('p.flex.items-center.gap-1') || 
                                       element.querySelector('p:has(span:contains("Fluxo da gôndola"))') ||
                                       element.querySelector('.flex.relative') ||
                                       // Busca por texto específico
                                       (element.textContent && element.textContent.includes('Fluxo da gôndola'));
                                       
                if (hasFlowIndicator && sectionCount >= 1) { // Relaxa requisito para testar
                    score += 1500; // Maior prioridade que container apenas com módulos
                    console.log(`🏆 CONTAINER COMPLETO ENCONTRADO! FlowIndicator + ${sectionCount} seções`);
                }
                
                // PRIORIDADE ALTA: ID específico criado para captura (apenas módulos)
                else if (element.id === 'planogram-container-full') {
                    score += 1000;
                    console.log(`🎯 CONTAINER MÓDULOS ENCONTRADO! ID: ${element.id}`);
                }
                
                // PRIORIDADE ALTA: Container com largura específica de 3618px
                else if (element.offsetWidth >= 3600 && element.offsetWidth <= 3650) {
                    score += 200;
                    console.log(`📐 CONTAINER LARGURA IDEAL: ${element.offsetWidth}px`);
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
            const hasFlowIndicator = bestContainer.querySelector('p.flex.items-center.gap-1') || 
                                   (bestContainer.textContent && bestContainer.textContent.includes('Fluxo da gôndola'));
            
            console.log(`📈 Container final contém: ${finalSectionCount} seções, ${finalCremalheiraCount} cremalheiras, ${finalLastRackCount} LastRacks`);
            
            // 🎯 OTIMIZAÇÃO: Se detectou FlowIndicator + Planograma, cria container otimizado
            if (hasFlowIndicator && finalSectionCount >= 1) {
                console.log(`🎯 Detectado FlowIndicator + Planograma - Criando container otimizado...`);
                
                // Busca FlowIndicator e container do planograma separadamente
                let flowIndicator = bestContainer.querySelector('.flex.relative');
                
                // Se não encontrou, busca por texto específico
                if (!flowIndicator) {
                    const allDivs = bestContainer.querySelectorAll('div');
                    for (const div of allDivs) {
                        if (div.textContent && div.textContent.includes('Fluxo da gôndola')) {
                            flowIndicator = div;
                            break;
                        }
                    }
                }
                
                const planogramContainer = bestContainer.querySelector('#planogram-container-full') || 
                                         bestContainer.querySelector('.mt-28.flex.md\\:flex-row');
                
                if (flowIndicator && planogramContainer) {
                    const optimizedContainer = this.createOptimizedPlanogramContainer(flowIndicator, planogramContainer);
                    console.log(`✅ Container otimizado criado: ${optimizedContainer.offsetWidth}x${optimizedContainer.offsetHeight}px`);
                    return optimizedContainer;
                } else {
                    console.log(`⚠️  Não foi possível criar container otimizado - usando container original`);
                }
            }
            
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
        const isVirtualPlanogram = element.hasAttribute('data-virtual-planogram');
        
        if (isVirtualModule || isVirtualPlanogram) {
            // Containers virtuais sempre são válidos se têm dimensões
            const isValid = element.offsetWidth > 0 && element.offsetHeight > 0;
            console.log(`🔍 Validando container virtual: ${isValid ? 'VÁLIDO' : 'INVÁLIDO'} (${element.offsetWidth}x${element.offsetHeight})`);
            return isValid;
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
     * Calcula as dimensões reais do elemento considerando margens, padding e scroll
     * @param {HTMLElement} element - Elemento a ser analisado
     * @returns {Object} Dimensões reais { width, height, hasMargins, hasScroll }
     */
    calculateRealElementDimensions(element) {
        console.log('🔍 Calculando dimensões reais do elemento...');
        
        const computedStyle = window.getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        
        // Dimensões básicas
        const offsetWidth = element.offsetWidth;
        const offsetHeight = element.offsetHeight;
        const scrollWidth = element.scrollWidth;
        const scrollHeight = element.scrollHeight;
        
        // Margens (especialmente importante para #planogram-container-full com mt-28)
        const marginTop = parseFloat(computedStyle.marginTop) || 0;
        const marginBottom = parseFloat(computedStyle.marginBottom) || 0;
        const marginLeft = parseFloat(computedStyle.marginLeft) || 0;
        const marginRight = parseFloat(computedStyle.marginRight) || 0;
        
        // Padding
        const paddingTop = parseFloat(computedStyle.paddingTop) || 0;
        const paddingBottom = parseFloat(computedStyle.paddingBottom) || 0;
        const paddingLeft = parseFloat(computedStyle.paddingLeft) || 0;
        const paddingRight = parseFloat(computedStyle.paddingRight) || 0;
        
        // 🎯 CORREÇÃO ESPECIAL para containers de planograma
        const isMainPlanogramContainer = element.id === 'planogram-container-full';
        const hasFlowIndicator = element.querySelector('p.flex.items-center.gap-1') || 
                                (element.textContent && element.textContent.includes('Fluxo da gôndola'));
        const isCompleteContainer = hasFlowIndicator && element.querySelectorAll('[data-section-id]').length >= 1;
        
        let finalWidth = offsetWidth;
        let finalHeight = offsetHeight;
        
        // Se o elemento tem scroll content maior que o visível, usa scrollHeight/scrollWidth
        if (scrollWidth > offsetWidth) {
            finalWidth = scrollWidth;
            console.log(`📏 Usando scrollWidth: ${scrollWidth}px (era ${offsetWidth}px)`);
        }
        
        if (scrollHeight > offsetHeight) {
            finalHeight = scrollHeight;
            console.log(`📏 Usando scrollHeight: ${scrollHeight}px (era ${offsetHeight}px)`);
        }
        
        // Para containers de planograma, inclui as margens no cálculo
        if (isMainPlanogramContainer || isCompleteContainer) {
            // Adiciona margens às dimensões finais para garantir captura completa
            finalHeight += marginTop + marginBottom;
            
            // 🎯 CORREÇÃO: Para containers completos, ajusta largura baseada no conteúdo real
            if (isCompleteContainer) {
                // Busca o container interno com os módulos para obter largura real
                const planogramContainer = element.querySelector('#planogram-container-full') || 
                                         element.querySelector('.mt-28.flex.md\\:flex-row');
                
                if (planogramContainer) {
                    const realPlanogramWidth = planogramContainer.scrollWidth || planogramContainer.offsetWidth;
                    // Adiciona margem lateral pequena para o FlowIndicator
                    finalWidth = Math.min(finalWidth, realPlanogramWidth + 100); // +100px para FlowIndicator
                    console.log(`🎯 Ajustando largura: ${finalWidth}px (planograma: ${realPlanogramWidth}px + 100px margem)`);
                } else {
                    finalWidth += marginLeft + marginRight;
                }
            } else {
                finalWidth += marginLeft + marginRight;
            }
            
            const containerType = isCompleteContainer ? 'COMPLETO (FlowIndicator + Módulos)' : 'MÓDULOS (#planogram-container-full)';
            console.log(`🎯 Container ${containerType} detectado:`);
            console.log(`   - Margem superior: ${marginTop}px`);
            console.log(`   - Margem inferior: ${marginBottom}px`);
            console.log(`   - Altura original: ${offsetHeight}px`);
            console.log(`   - Altura com margens: ${finalHeight}px`);
            console.log(`   - Largura ajustada: ${finalWidth}px`);
            if (isCompleteContainer) {
                console.log(`   - ✅ Inclui FlowIndicator na captura`);
                console.log(`   - 🎯 Largura otimizada para evitar espaço vazio`);
            }
        }
        
        // Inclui padding se necessário para containers com conteúdo interno
        const hasPadding = paddingTop > 0 || paddingBottom > 0 || paddingLeft > 0 || paddingRight > 0;
        if (hasPadding && !isMainPlanogramContainer && !isCompleteContainer) {
            finalHeight += paddingTop + paddingBottom;
            finalWidth += paddingLeft + paddingRight;
            console.log(`📦 Incluindo padding: +${paddingTop + paddingBottom}px altura, +${paddingLeft + paddingRight}px largura`);
        }
        
        const result = {
            width: Math.max(finalWidth, offsetWidth), // Nunca menor que offset
            height: Math.max(finalHeight, offsetHeight), // Nunca menor que offset
            originalWidth: offsetWidth,
            originalHeight: offsetHeight,
            scrollWidth: scrollWidth,
            scrollHeight: scrollHeight,
            margins: { top: marginTop, bottom: marginBottom, left: marginLeft, right: marginRight },
            padding: { top: paddingTop, bottom: paddingBottom, left: paddingLeft, right: paddingRight },
            hasMargins: marginTop > 0 || marginBottom > 0 || marginLeft > 0 || marginRight > 0,
            hasScroll: scrollWidth > offsetWidth || scrollHeight > offsetHeight,
            isPlanogramContainer: isMainPlanogramContainer,
            isCompleteContainer: isCompleteContainer,
            hasFlowIndicator: hasFlowIndicator
        };
        
        console.log('📊 Dimensões calculadas:', result);
        return result;
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
        
        // 🎯 CORREÇÃO: Calcula dimensões reais considerando margens e scroll
        const realDimensions = this.calculateRealElementDimensions(element);
        console.log('Dimensões reais do elemento:', realDimensions);
        
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
                width: realDimensions.width * finalConfig.scale,
                height: realDimensions.height * finalConfig.scale,
                style: {
                    transform: `scale(${finalConfig.scale})`,
                    transformOrigin: 'top left',
                    width: realDimensions.width + 'px',
                    height: realDimensions.height + 'px',
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
                            // 'digitaloceanspaces.com',
                            // 'amazonaws.com',
                            // 'cloudflare.com'
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
                    width: realDimensions.width * (finalConfig.scale * 0.8),
                    height: realDimensions.height * (finalConfig.scale * 0.8),
                    style: {
                        transform: `scale(${finalConfig.scale * 0.8})`,
                        transformOrigin: 'top left',
                        width: realDimensions.width + 'px',
                        height: realDimensions.height + 'px',
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
        
        // Limpa cache de fluxo antes da captura para garantir detecção atualizada
        this.clearFlowCache();
        
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
                if (!module.element) {
                    throw new Error('Elemento não encontrado');
                }
                
                // Para containers virtuais, aguarda um momento para renderização
                if (module.element.hasAttribute('data-virtual-planogram')) {
                    console.log('🔄 Aguardando renderização do container virtual...');
                    await this.wait(200); // Aguarda 200ms para renderização
                }
                
                if (!this.isElementValid(module.element)) {
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
            console.log(`📄 Adicionando página ${i + 1}: "${capture.name}" (ID: ${capture.id})`);
            
            if (!isFirstPage) {
                pdf.addPage();
            }
            isFirstPage = false;

            // Adiciona título do módulo
            pdf.setFontSize(16);
            pdf.setFont(undefined, 'bold');
            console.log(`📝 Escrevendo título no PDF: "${capture.name}"`);
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
        },
        // Novos métodos de debug para fluxo
        detectFlow: () => printService.detectGondolaFlow(),
        clearFlowCache: () => printService.clearFlowCache(),
        getFlowCache: () => ({
            flow: printService._detectedFlow,
            timestamp: printService._flowDetectionTimestamp,
            timeout: printService._flowCacheTimeout
        }),
        // Métodos de validação
        validateModules: () => {
            const modules = printService.detectModules();
            return printService.validateModuleDetection(modules);
        },
        // Método para debug completo
        debugComplete: () => {
            console.log('=== DEBUG COMPLETO DO PRINTSERVICE ===');
            const flow = printService.detectGondolaFlow();
            const modules = printService.detectModules();
            const validation = printService.validateModuleDetection(modules);
            
            console.log('Fluxo detectado:', flow);
            console.log('Módulos detectados:', modules.length);
            console.log('Validação:', validation);
            
            return {
                flow,
                modules,
                validation,
                cache: {
                    flow: printService._detectedFlow,
                    timestamp: printService._flowDetectionTimestamp
                }
            };
        }
    };
}