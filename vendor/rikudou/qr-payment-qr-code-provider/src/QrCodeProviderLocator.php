<?php

namespace Rikudou\QrPaymentQrCodeProvider;

use Rikudou\QrPaymentQrCodeProvider\Exception\NoProviderFoundException;

final class QrCodeProviderLocator
{
    /**
     * @var iterable<QrCodeProvider>
     */
    private $providers;

    /**
     * @param iterable<QrCodeProvider> $providers
     */
    public function __construct(iterable $providers = [])
    {
        if (!is_countable($providers)) {
            $providers = iterator_to_array($providers);
        }
        if (!count($providers)) {
            $providers = [
                new EndroidQrCode3Provider(),
                new EndroidQrCode4Provider(),
                new BaconQrCodeProvider(),
                new ChillerlanQrCodeProvider(),
            ];
        }
        $this->providers = $providers;
    }

    public function getProvider(): QrCodeProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider::isInstalled()) {
                return $provider;
            }
        }

        throw new NoProviderFoundException('No QR code provider found.');
    }
}
