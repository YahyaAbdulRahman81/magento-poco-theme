<?php
namespace Clearsale\Integration\Model\Auth\Entity;

/**
 * Credentials entity for Clearsale integration.
 * Declared properties prevent PHP 8.2 "creation of dynamic property" deprecations.
 */
class Credentials
{
    /**
     * API Key for authentication
     *
     * @var string|null
     */
    public $ApiKey;

    /**
     * Client ID for authentication
     *
     * @var string|null
     */
    public $ClientID;

    /**
     * Client Secret for authentication
     *
     * @var string|null
     */
    public $ClientSecret;
}
