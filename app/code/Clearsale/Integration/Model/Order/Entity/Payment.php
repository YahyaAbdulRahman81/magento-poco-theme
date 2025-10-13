<?php
namespace Clearsale\Integration\Model\Order\Entity;

/**
 * Payment entity for Clearsale integration.
 * Declared properties include CardType to avoid dynamic property creation.
 */
class Payment
{
    public $Date;
    public $Type;
    public $Gateway;
    public $CardNumber;
    public $CardHolderName;
    public $Amount;
    public $PaymentTypeID;
    public $CardBin;
    public $CardExpirationDate;

    /**
     * Card type (e.g. VISA, MASTERCARD) - declared to prevent PHP 8.2+ deprecation.
     *
     * @var string|null
     */
    public $CardType;
}
