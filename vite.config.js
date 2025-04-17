import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
  plugins: [
    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
  ],
  resolve: {
    alias: { 
      '@plannerate/': resolve(__dirname, 'resources/js/'),
    },
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        plannerate: resolve(__dirname, 'resources/js/index.ts'),
      },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: 'js/[name]-[hash].js',
        assetFileNames: ({ name }) => {
          if (/\.(css)$/.test(name ?? '')) {
            return 'css/[name][extname]';
          }
          return 'assets/[name]-[hash][extname]';
        },
      },
    },
    // Configuração de biblioteca para permitir importação como módulo ES
    lib: {
      entry: resolve(__dirname, 'resources/js/index.ts'),
      name: 'Plannerate',
      fileName: (format) => `plannerate.${format}.js`,
      formats: ['es', 'umd'],
    },
  },
  // Externalizando dependências que devem ser fornecidas pelo projeto host
  optimizeDeps: {
    exclude: ['vue', 'vue-router', 'pinia'],
  },
}); 