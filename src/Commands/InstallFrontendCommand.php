<?php

namespace Callcocam\Plannerate\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class InstallFrontendCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plannerate:install-frontend
                            {--force : Sobrescrever arquivos existentes}
                            {--vite : Configurar para uso com Vite}
                            {--mix : Configurar para uso com Laravel Mix}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Instala e configura o frontend Vue do Plannerate';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Instalando o frontend do Plannerate...');

        $this->publishAssets();
        $this->installDependencies();
        $this->configureViteOrMix();
        $this->createVueEntryPoint();
        
        $this->info('Frontend do Plannerate instalado com sucesso!');
        $this->info('Execute "npm install && npm run dev" para compilar os assets.');

        return Command::SUCCESS;
    }

    /**
     * Publica os assets do Plannerate
     */
    protected function publishAssets()
    {
        $this->info('Publicando assets...');
        
        $this->callSilent('vendor:publish', [
            '--tag' => 'plannerate-assets',
            '--force' => $this->option('force'),
        ]);
        
        $this->callSilent('vendor:publish', [
            '--tag' => 'plannerate-config',
            '--force' => $this->option('force'),
        ]);
    }

    /**
     * Instala as dependências do frontend
     */
    protected function installDependencies()
    {
        $this->info('Verificando package.json...');
        
        $packageJson = $this->getPackageJson();
        
        // Adicionar dependências do Vue
        $packageJson['dependencies']['vue'] = '^3.3.0';
        $packageJson['dependencies']['vue-router'] = '^4.2.0';
        $packageJson['dependencies']['pinia'] = '^2.1.0';
        
        // Salvar package.json atualizado
        File::put(
            base_path('package.json'),
            json_encode($packageJson, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        
        $this->info('Dependências adicionadas ao package.json');
    }

    /**
     * Configura Vite ou Mix baseado na opção selecionada
     */
    protected function configureViteOrMix()
    {
        if ($this->option('mix')) {
            $this->configureMix();
        } else {
            $this->configureVite();
        }
    }

    /**
     * Configura Laravel Mix
     */
    protected function configureMix()
    {
        $this->info('Configurando Laravel Mix...');
        
        if (!File::exists(base_path('webpack.mix.js'))) {
            $this->error('webpack.mix.js não encontrado. Use --vite em vez de --mix.');
            return;
        }
        
        $mixContent = File::get(base_path('webpack.mix.js'));
        
        if (strpos($mixContent, 'plannerate') === false) {
            $mixAppend = "\n// Plannerate Vue Frontend\n";
            $mixAppend .= "mix.js('resources/js/plannerate.js', 'public/js')\n";
            $mixAppend .= "   .vue()\n";
            $mixAppend .= "   .postCss('resources/css/plannerate.css', 'public/css');";
            
            File::append(base_path('webpack.mix.js'), $mixAppend);
            
            $this->info('Laravel Mix configurado para compilar o frontend do Plannerate.');
        }
    }

    /**
     * Configura Vite
     */
    protected function configureVite()
    {
        $this->info('Configurando Vite...');
        
        if (!File::exists(base_path('vite.config.js'))) {
            $this->error('vite.config.js não encontrado. Certifique-se de que o Vite está instalado.');
            return;
        }
        
        $viteContent = File::get(base_path('vite.config.js'));
        
        if (strpos($viteContent, 'plannerate') === false) {
            // Adicionar plannerate.js como um input
            $viteContent = preg_replace(
                '/input: (\{.*?\})/s',
                "input: {\$1,\n            plannerate: 'resources/js/plannerate.js',\n        }",
                $viteContent
            );
            
            File::put(base_path('vite.config.js'), $viteContent);
            
            $this->info('Vite configurado para compilar o frontend do Plannerate.');
        }
    }

    /**
     * Cria o ponto de entrada Vue
     */
    protected function createVueEntryPoint()
    {
        $this->info('Criando ponto de entrada Vue...');
        
        // Criar diretório se não existir
        if (!File::exists(resource_path('js'))) {
            File::makeDirectory(resource_path('js'), 0755, true);
        }
        
        // Criar arquivo plannerate.js
        $content = <<<'EOT'
import { createApp } from 'vue';
import Plannerate from 'plannerate-vue';
import 'plannerate-vue/style.css';

// Importe o CSS personalizado (opcional)
import '../css/plannerate.css';

// Crie um elemento div para montar a aplicação Vue se não existir
let plannerateElement = document.getElementById('plannerate-app');
if (!plannerateElement) {
    plannerateElement = document.createElement('div');
    plannerateElement.id = 'plannerate-app';
    document.body.appendChild(plannerateElement);
}

// Crie e monte a aplicação Vue
const app = createApp({});

// Configure o Plannerate
app.use(Plannerate, {
    baseUrl: '/api',
    tenant: window.tenantId || null,
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
});

app.mount('#plannerate-app');

// Exportar componentes individuais para uso em outras partes da aplicação
export { PlannerateApp, ConfirmModal } from 'plannerate-vue';
EOT;
        
        File::put(resource_path('js/plannerate.js'), $content);
        
        // Criar arquivo CSS
        if (!File::exists(resource_path('css'))) {
            File::makeDirectory(resource_path('css'), 0755, true);
        }
        
        $cssContent = <<<'EOT'
/* Customizações para o Plannerate */
.plannerate-custom {
    /* Personalize aqui */
}
EOT;
        
        File::put(resource_path('css/plannerate.css'), $cssContent);
        
        $this->info('Ponto de entrada Vue criado em resources/js/plannerate.js');
    }

    /**
     * Obtém o conteúdo do package.json
     */
    protected function getPackageJson()
    {
        if (!File::exists(base_path('package.json'))) {
            return [
                'private' => true,
                'scripts' => [
                    'dev' => 'vite',
                    'build' => 'vite build',
                ],
                'dependencies' => [],
                'devDependencies' => [],
            ];
        }
        
        return json_decode(File::get(base_path('package.json')), true);
    }
} 