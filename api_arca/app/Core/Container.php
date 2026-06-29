<?php

namespace ApiArca\App\Core;

/**
 * Contenedor de Inyección de Dependencias
 * Implementa un contenedor simple para resolución de dependencias
 */
class Container
{
    private static ?Container $instance = null;
    private array $bindings = [];
    private array $singletons = [];
    private array $resolved = [];

    /**
     * Constructor privado para patrón Singleton
     */
    private function __construct() {}

    /**
     * Obtiene la instancia única del contenedor
     */
    public static function getInstance(): Container
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    /**
     * Registra una binding en el contenedor
     * 
     * @param string $abstract Nombre o interfaz
     * @param callable|string $concrete Implementación o factory
     * @param bool $shared Si es singleton
     * @return void
     */
    public function bind(string $abstract, callable|string $concrete, bool $shared = false): void
    {
        if ($shared) {
            $this->singletons[$abstract] = null;
        }
        
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Registra un singleton en el contenedor
     * 
     * @param string $abstract Nombre o interfaz
     * @param callable|string $concrete Implementación o factory
     * @return void
     */
    public function singleton(string $abstract, callable|string $concrete): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Resuelve una dependencia del contenedor
     * 
     * @param string $abstract Nombre o interfaz
     * @param array $parameters Parámetros adicionales
     * @return mixed
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        // Verificar si es singleton ya resuelto
        if (isset($this->singletons[$abstract]) && $this->singletons[$abstract] !== null) {
            return $this->singletons[$abstract];
        }

        // Verificar si hay un binding registrado
        if (isset($this->bindings[$abstract])) {
            $concrete = $this->bindings[$abstract];
            
            if (is_callable($concrete)) {
                $instance = $concrete($this, $parameters);
            } else {
                $instance = $this->resolveClass($concrete, $parameters);
            }
        } else {
            // Intentar resolver automáticamente
            $instance = $this->resolveClass($abstract, $parameters);
        }

        // Guardar singleton si corresponde
        if (isset($this->singletons[$abstract])) {
            $this->singletons[$abstract] = $instance;
        }

        $this->resolved[$abstract] = true;

        return $instance;
    }

    /**
     * Resuelve una clase automáticamente usando reflexión
     * 
     * @param string $class Nombre de la clase
     * @param array $parameters Parámetros adicionales
     * @return object
     */
    private function resolveClass(string $class, array $parameters = []): object
    {
        if (!class_exists($class)) {
            throw new \RuntimeException("Clase {$class} no encontrada");
        }

        $reflector = new \ReflectionClass($class);

        // Si no tiene constructor, instanciar directamente
        if (!$reflector->hasConstructor()) {
            return new $class();
        }

        $constructor = $reflector->getConstructor();
        $dependencies = $constructor->getParameters();
        $args = [];

        foreach ($dependencies as $dependency) {
            $type = $dependency->getType();
            $name = $dependency->getName();

            // Si el parámetro tiene un tipo de clase, resolverlo del contenedor
            if ($type instanceof \ReflectionClass) {
                $args[] = $this->make($type->getName());
            } elseif (array_key_exists($name, $parameters)) {
                // Usar parámetro pasado explícitamente
                $args[] = $parameters[$name];
            } elseif ($dependency->isDefaultValueAvailable()) {
                // Usar valor por defecto
                $args[] = $dependency->getDefaultValue();
            } else {
                throw new \RuntimeException(
                    "No se puede resolver el parámetro '{$name}' en {$class}"
                );
            }
        }

        return $reflector->newInstanceArgs($args);
    }

    /**
     * Verifica si una binding está registrada
     * 
     * @param string $abstract
     * @return bool
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]);
    }

    /**
     * Verifica si una instancia ya fue resuelta
     * 
     * @param string $abstract
     * @return bool
     */
    public function resolved(string $abstract): bool
    {
        return isset($this->resolved[$abstract]);
    }

    /**
     * Obtiene todas las bindings registradas
     * 
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Previene clonación de la instancia
     */
    private function __clone() {}

    /**
     * Previene deserialización de la instancia
     */
    public function __wakeup()
    {
        throw new \Exception("No se puede deserializar esta clase");
    }
}
