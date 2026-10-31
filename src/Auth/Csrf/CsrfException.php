<?php

namespace Dr2g\ErpLibs\Auth\Csrf;

/**
 * El token CSRF enviado no es válido.
 */
class CsrfException extends \RuntimeException
{
    public function __construct(string $message = 'Token CSRF inválido o ausente.')
    {
        parent::__construct($message);
    }
}
