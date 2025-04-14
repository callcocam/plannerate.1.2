<?php
/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */
namespace Callcocam\Plannerate;

class Plannerate {

        /**
        * @var string
        */
        protected string $name = 'plannerate';



        

        /**
         * @var string
         */
        protected string $version = '1.0.0';
        /**
         * @var string
         */
        protected string $description = 'Plannerate - Planejamento de Tarifas';
        /**
         * @var string
         */
        protected string $author = 'Claudio Campos';
        /**
         * @var string
         */
        protected string $path = 'plannerate';
        /**
         * @var string
         */
        protected string $route = 'plannerate';
    
        public function __construct()
        {
            //
        }

        public static function make()
        {
            return new static();
        }
        public function getName(): string
        {
            return $this->name;
        }
        public function getVersion(): string
        {
            return $this->version;
        }
        public function getDescription(): string
        {
            return $this->description;
        }
        public function getAuthor(): string
        {
            return $this->author;
        }
        public function getPath(): string
        {
            return $this->path;
        }
        public function getRoute(): string
        {
            return $this->route;
        }
        public function name(string $name): static
        {
            $this->name = $name;
            return $this;
        }
        public function version(string $version): static
        {
            $this->version = $version;
            return $this;
        }
        public function description(string $description): static
        {
            $this->description = $description;
            return $this;
        }
        public function author(string $author): static
        {
            $this->author = $author;
            return $this;
        }
        public function path(string $path): static
        {
            $this->path = $path;
            return $this;
        }
        public function route(string $route): static
        {
            $this->route = $route;
            return $this;
        }
}
