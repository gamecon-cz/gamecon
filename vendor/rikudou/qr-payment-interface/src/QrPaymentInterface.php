<?php

namespace Rikudou\QrPayment;

use DateTimeInterface;

interface QrPaymentInterface
{
    /**
     * Sets specified options in one go.
     * Format:
     *  optionName => optionValue
     *
     * @param array $options
     *
     * @return $this
     */
    public function setOptions(array $options);

    /**
     * Sets the currency used for the transaction. Use three-letter currency code.
     *
     * @param string $currency
     *
     * @return $this
     */
    public function setCurrency(string $currency);

    /**
     * @param DateTimeInterface $dueDate
     *
     * @return $this
     */
    public function setDueDate(DateTimeInterface $dueDate);

    /**
     * @param float $amount
     *
     * @return $this
     */
    public function setAmount(float $amount);

    /**
     * Returns the generated string that can be put into QR code image
     *
     * @return string
     */
    public function getQrString(): string;

    /**
     * Returns the three-letter currency code
     *
     * @return string
     */
    public function getCurrency(): string;

    /**
     * Returns the amount. If no amount was set, returns 0.
     *
     * @return float
     */
    public function getAmount(): float;

    /**
     * Returns the due date. If no due date was set returns current date.
     *
     * @return DateTimeInterface
     */
    public function getDueDate(): DateTimeInterface;
}
