<?php

namespace ApiArca\App\Core;

/**
 * Clase Response para manejar la respuesta HTTP saliente
 */
class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private mixed $body = null;
    private string $content = '';

    /**
     * Constructor
     * 
     * @param mixed $data Datos de la respuesta
     * @param int $statusCode Código de estado HTTP
     * @param array $headers Headers adicionales
     */
    public function __construct(mixed $data = null, int $statusCode = 200, array $headers = [])
    {
        $this->body = $data;
        $this->statusCode = $statusCode;
        $this->headers = array_merge($this->headers, $headers);
    }

    /**
     * Crea una respuesta JSON estándar
     * 
     * @param bool $success Indica si la operación fue exitosa
     * @param mixed $data Datos de la respuesta
     * @param string $message Mensaje opcional
     * @param array $errors Errores encontrados
     * @param int $statusCode Código de estado HTTP
     * @return self
     */
    public static function json(
        bool $success = true,
        mixed $data = null,
        string $message = '',
        array $errors = [],
        int $statusCode = 200
    ): self {
        $response = new self(null, $statusCode, ['Content-Type' => 'application/json']);
        
        $responseData = [
            'success' => $success,
            'data' => $data ?? null,
            'message' => $message,
            'errors' => $errors,
        ];

        $response->body = $responseData;
        
        return $response;
    }

    /**
     * Crea una respuesta de éxito
     * 
     * @param mixed $data Datos de la respuesta
     * @param string $message Mensaje opcional
     * @param int $statusCode Código de estado HTTP
     * @return self
     */
    public static function success(mixed $data = null, string $message = '', int $statusCode = 200): self
    {
        return self::json(true, $data, $message, [], $statusCode);
    }

    /**
     * Crea una respuesta de error
     * 
     * @param string $message Mensaje de error
     * @param array $errors Errores detallados
     * @param int $statusCode Código de estado HTTP
     * @return self
     */
    public static function error(string $message = '', array $errors = [], int $statusCode = 400): self
    {
        return self::json(false, null, $message, $errors, $statusCode);
    }

    /**
     * Establece el código de estado HTTP
     * 
     * @param int $code
     * @return self
     */
    public function withStatus(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Agrega un header a la respuesta
     * 
     * @param string $name Nombre del header
     * @param string $value Valor del header
     * @return self
     */
    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Agrega múltiples headers a la respuesta
     * 
     * @param array $headers
     * @return self
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Envía la respuesta al cliente
     * 
     * @return void
     */
    public function send(): void
    {
        // Enviar headers
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Generar contenido
        if (is_array($this->body) || is_object($this->body)) {
            $this->content = json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $this->content = (string)$this->body;
        }

        echo $this->content;
    }

    /**
     * Obtiene el código de estado
     * 
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Obtiene los headers
     * 
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Obtiene el cuerpo de la respuesta
     * 
     * @return mixed
     */
    public function getBody(): mixed
    {
        return $this->body;
    }

    /**
     * Obtiene el contenido serializado
     * 
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }
}
