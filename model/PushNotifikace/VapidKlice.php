<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

/**
 * VAPID klíče prostředí: pár, kterým se server prokazuje push službám prohlížečů. Veřejný klíč dostane prohlížeč
 * při zapnutí notifikací a push služba pak přijme zprávu jen podepsanou soukromým klíčem téhož páru.
 *
 * Každé prostředí (ostrá, beta, preview) má vlastní pár, viz docs/push-notifikace.md.
 */
final readonly class VapidKlice
{
    /**
     * Nekomprimovaný bod křivky P-256: 0x04 a dvě souřadnice po 32 bajtech.
     */
    private const int DELKA_VEREJNEHO_KLICE = 65;

    private const int DELKA_SOUKROMEHO_KLICE = 32;

    public function __construct(
        public string $verejny,
        #[\SensitiveParameter]
        private string $soukromy,
        public string $subject,
    ) {
    }

    /**
     * Klíče z nastavení prostředí, null když chybí nebo nemají tvar klíče. Bez nich je funkce vypnutá.
     */
    public static function zKonstant(): ?self
    {
        $verejny = defined('VAPID_PUBLIC_KEY')
            ? (string) VAPID_PUBLIC_KEY
            : '';
        $soukromy = defined('VAPID_PRIVATE_KEY')
            ? (string) VAPID_PRIVATE_KEY
            : '';

        return self::zHodnot($verejny, $soukromy, URL_WEBU);
    }

    public static function zHodnot(
        string $verejny,
        #[\SensitiveParameter]
        string $soukromy,
        string $urlWebu,
    ): ?self {
        if (Base64Url::getDelkaPoDekodovani($verejny) !== self::DELKA_VEREJNEHO_KLICE
            || Base64Url::getDelkaPoDekodovani($soukromy) !== self::DELKA_SOUKROMEHO_KLICE
        ) {
            return null;
        }

        return new self($verejny, $soukromy, self::getSubjectZUrlWebu($urlWebu));
    }

    /**
     * Kontakt na provozovatele, který push služba dostane s každou zprávou. Musí to být adresa https nebo mailto,
     * jinak ji některé služby odmítnou; lokální web běží na http, a tak dostane e-mail.
     */
    private static function getSubjectZUrlWebu(string $urlWebu): string
    {
        return parse_url($urlWebu, PHP_URL_SCHEME) === 'https'
            ? $urlWebu
            : 'mailto:info@gamecon.cz';
    }

    /**
     * Tvar, který čeká knihovna minishlink/web-push.
     *
     * @return array{subject: string, publicKey: string, privateKey: string}
     */
    public function getKonfiguraceKnihovny(): array
    {
        return [
            'subject'    => $this->subject,
            'publicKey'  => $this->verejny,
            'privateKey' => $this->soukromy,
        ];
    }
}
