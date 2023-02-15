<?php

namespace Shop;

use Phpr\ApplicationException;
use Phpr\DateTime as PhprDateTime;

class ShippingTrackerDetail
{

    protected string $trackingCode;
    protected string $message;
    protected string $statusCode;
    protected PhprDateTime $datetime;

    private array $statusCodes = array(
        "unknown" => 'Unknown',
        "pre_transit" => 'Pre transit',
        "in_transit" => 'In transit',
        "out_for_delivery" => 'Out for delivery',
        "delivered" => 'Delivered',
        "available_for_pickup" => 'Available for pickup',
        "return_to_sender" => 'Return to sender',
        "failure" => 'Failure',
        "cancelled" => 'Cancelled',
        "error" => 'Error',
    );

    /**
     * @param string $trackingCode
     * @param string $message
     * @param string $statusCode
     * @param PhprDateTime $datetime
     * @throws ApplicationException
     */
    public function __construct(string $trackingCode, string $message, string $statusCode, PhprDateTime $datetime)
    {
        if (!in_array($statusCode, $this->statusCodes)) {
            throw new ApplicationException('Invalid status code');
        }
        $this->trackingCode = (string)$trackingCode;
        $this->message = (string)$message;
        $this->statusCode = (string)$statusCode;
        $this->datetime = $datetime;
    }

    /**
     * @return string Tracking Code
     */
    public function getTrackingCode(): string
    {
        return $this->trackingCode;
    }

    /**
     * @return string Status Name
     */
    public function getStatusName(): string
    {
        return $this->statusCodes[$this->statusCode];
    }

    /**
     * @return string Status Code
     */
    public function getStatusCode(): string
    {
        return $this->statusCode;
    }

    /**
     * @return string Description of Tracking Event
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return PhprDateTime  A datetime obj representing time tracking event was recorded
     */
    public function getDateTime(): PhprDateTime
    {
        return $this->datetime;
    }

}