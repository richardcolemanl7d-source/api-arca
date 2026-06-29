<?php

namespace ApiArca\App\Helpers;

/**
 * Validador de datos de entrada
 * Implementa validaciones comunes para la API
 */
class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];

    /**
     * Constructor
     * 
     * @param array $data Datos a validar
     * @param array $rules Reglas de validación
     */
    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    /**
     * Ejecuta la validación
     * 
     * @return bool True si pasa todas las validaciones
     */
    public function validate(): bool
    {
        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            
            foreach ($rules as $rule) {
                $this->applyRule($field, $rule);
                
                if (!empty($this->errors[$field])) {
                    break; // No continuar con más reglas si ya falló
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Obtiene los errores de validación
     * 
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Obtiene el primer error encontrado
     * 
     * @return string|null
     */
    public function getFirstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return $fieldErrors[0];
            }
        }
        
        return null;
    }

    /**
     * Aplica una regla específica a un campo
     * 
     * @param string $field Campo a validar
     * @param string $rule Regla a aplicar
     * @return void
     */
    private function applyRule(string $field, string $rule): void
    {
        $value = $this->data[$field] ?? null;

        // Parsear regla con parámetros (ej: min:3, max:255)
        [$ruleName, $params] = $this->parseRule($rule);

        switch ($ruleName) {
            case 'required':
                $this->validateRequired($field, $value);
                break;
            case 'string':
                $this->validateString($field, $value);
                break;
            case 'integer':
                $this->validateInteger($field, $value);
                break;
            case 'numeric':
                $this->validateNumeric($field, $value);
                break;
            case 'email':
                $this->validateEmail($field, $value);
                break;
            case 'min':
                $this->validateMin($field, $value, (int)$params[0]);
                break;
            case 'max':
                $this->validateMax($field, $value, (int)$params[0]);
                break;
            case 'length':
                $this->validateLength($field, $value, (int)$params[0]);
                break;
            case 'in':
                $this->validateIn($field, $value, $params);
                break;
            case 'regex':
                $this->validateRegex($field, $value, $params[0]);
                break;
            case 'cuit':
                $this->validateCuit($field, $value);
                break;
            case 'date':
                $this->validateDate($field, $value);
                break;
            case 'array':
                $this->validateArray($field, $value);
                break;
            case 'nullable':
                // Solo permite null, no valida si es null
                break;
        }
    }

    /**
     * Parsea una regla separando nombre y parámetros
     * 
     * @param string $rule
     * @return array [nombre, parametros]
     */
    private function parseRule(string $rule): array
    {
        if (strpos($rule, ':') !== false) {
            [$name, $paramString] = explode(':', $rule, 2);
            return [$name, explode(',', $paramString)];
        }
        
        return [$rule, []];
    }

    /**
     * Agrega un error al campo especificado
     * 
     * @param string $field
     * @param string $message
     * @return void
     */
    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        
        $this->errors[$field][] = $message;
    }

    // ==================== VALIDADORES ====================

    private function validateRequired(string $field, mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->addError($field, "El campo {$field} es requerido.");
        }
    }

    private function validateString(string $field, mixed $value): void
    {
        if ($value !== null && !is_string($value)) {
            $this->addError($field, "El campo {$field} debe ser una cadena de texto.");
        }
    }

    private function validateInteger(string $field, mixed $value): void
    {
        if ($value !== null && !is_int($value)) {
            $this->addError($field, "El campo {$field} debe ser un número entero.");
        }
    }

    private function validateNumeric(string $field, mixed $value): void
    {
        if ($value !== null && !is_numeric($value)) {
            $this->addError($field, "El campo {$field} debe ser numérico.");
        }
    }

    private function validateEmail(string $field, mixed $value): void
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "El campo {$field} debe ser una dirección de email válida.");
        }
    }

    private function validateMin(string $field, mixed $value, int $min): void
    {
        if ($value !== null && is_numeric($value) && $value < $min) {
            $this->addError($field, "El campo {$field} debe ser mayor o igual a {$min}.");
        }
    }

    private function validateMax(string $field, mixed $value, int $max): void
    {
        if ($value !== null && is_numeric($value) && $value > $max) {
            $this->addError($field, "El campo {$field} debe ser menor o igual a {$max}.");
        }
    }

    private function validateLength(string $field, mixed $value, int $length): void
    {
        if ($value !== null && is_string($value) && strlen($value) !== $length) {
            $this->addError($field, "El campo {$field} debe tener exactamente {$length} caracteres.");
        }
    }

    private function validateIn(string $field, mixed $value, array $allowed): void
    {
        if ($value !== null && !in_array($value, $allowed, true)) {
            $allowedStr = implode(', ', $allowed);
            $this->addError($field, "El campo {$field} debe ser uno de: {$allowedStr}.");
        }
    }

    private function validateRegex(string $field, mixed $value, string $pattern): void
    {
        if ($value !== null && !preg_match($pattern, $value)) {
            $this->addError($field, "El campo {$field} tiene un formato inválido.");
        }
    }

    /**
     * Valida un CUIT argentino
     * Algoritmo de validación del módulo 11
     */
    private function validateCuit(string $field, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        // Limpiar valor (solo dígitos)
        $cuit = preg_replace('/[^0-9]/', '', (string)$value);

        if (strlen($cuit) !== 11) {
            $this->addError($field, "El campo {$field} debe tener 11 dígitos.");
            return;
        }

        // Algoritmo módulo 11
        $base = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $total = 0;

        for ($i = 0; $i < 10; $i++) {
            $total += (int)$cuit[$i] * $base[$i];
        }

        $resto = $total % 11;
        $digitoVerificador = 11 - $resto;

        if ($digitoVerificador === 11) {
            $digitoVerificador = 0;
        } elseif ($digitoVerificador === 10) {
            $digitoVerificador = 9;
        }

        if ((int)$cuit[10] !== $digitoVerificador) {
            $this->addError($field, "El campo {$field} no es un CUIT válido.");
        }
    }

    private function validateDate(string $field, mixed $value): void
    {
        if ($value !== null) {
            $timestamp = strtotime($value);
            if ($timestamp === false) {
                $this->addError($field, "El campo {$field} debe ser una fecha válida.");
            }
        }
    }

    private function validateArray(string $field, mixed $value): void
    {
        if ($value !== null && !is_array($value)) {
            $this->addError($field, "El campo {$field} debe ser un arreglo.");
        }
    }

    /**
     * Método estático convenience para validación rápida
     * 
     * @param array $data
     * @param array $rules
     * @return array [bool isValid, array errors]
     */
    public static function make(array $data, array $rules): array
    {
        $validator = new self($data, $rules);
        $isValid = $validator->validate();
        
        return [$isValid, $validator->getErrors()];
    }
}
