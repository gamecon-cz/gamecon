<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

/**
 * Jedna notifikace k odeslání: co se na zařízení ukáže, kam vede a jak ji má push služba doručit.
 * Vzniká jen přes {@see self::vytvor()}, takže každá instance je zkontrolovaná.
 */
final readonly class ZpravaNotifikace
{
    public const int MAX_DELKA_NADPISU = 100;

    public const int MAX_DELKA_TEXTU = 500;

    /**
     * Čtyři týdny, víc push služby nedrží.
     */
    public const int MAX_TTL = 28 * 24 * 60 * 60;

    /**
     * Zašifrovaná zpráva smí mít nejvýš 4 kB a některé služby berou méně. Délky nadpisu, textu a odkazu se do
     * toho vejdou i ve znacích po čtyřech bajtech; tohle je pojistka, kdyby se některá z nich zvedla.
     */
    private const int MAX_DELKA_PAYLOADU = 3000;

    /**
     * Hlavička Topic push služby (RFC 8030) bere nejvýš 32 znaků abecedy base64url.
     */
    private const string VZOR_TAGU = '/^[A-Za-z0-9_-]{1,32}$/D';

    private function __construct(
        public TypNotifikace $typ,
        public string $nadpis,
        public string $text,
        public string $odkaz,
        public int $ttl,
        public Urgence $urgence,
        public ?string $tag,
    ) {
    }

    /**
     * @param string|null $odkaz cesta na webu GameConu, viz {@see OdkazNotifikace}; bez ní vede na úvodní stránku
     * @param int|null    $ttl   sekundy, po které push služba zprávu drží pro nedostupné zařízení; výchozí podle typu
     * @param string|null $tag   notifikace se stejným tagem se na zařízení nahradí; výchozí podle typu
     *
     * @throws ChybaPushNotifikace
     */
    public static function vytvor(
        TypNotifikace $typ,
        string $nadpis,
        string $text,
        ?string $odkaz = null,
        ?int $ttl = null,
        ?Urgence $urgence = null,
        ?string $tag = null,
    ): self {
        if (! mb_check_encoding($nadpis, 'UTF-8') || ! mb_check_encoding($text, 'UTF-8')) {
            throw new ChybaPushNotifikace('Nadpis nebo text obsahuje neplatné znaky.');
        }
        $nadpis = trim($nadpis);
        $text = trim($text);
        if ($nadpis === '') {
            throw new ChybaPushNotifikace('Notifikace potřebuje nadpis.');
        }
        if (mb_strlen($nadpis) > self::MAX_DELKA_NADPISU) {
            throw new ChybaPushNotifikace(sprintf('Nadpis smí mít nejvýš %d znaků.', self::MAX_DELKA_NADPISU));
        }
        if (mb_strlen($text) > self::MAX_DELKA_TEXTU) {
            throw new ChybaPushNotifikace(sprintf('Text smí mít nejvýš %d znaků.', self::MAX_DELKA_TEXTU));
        }
        $ttl ??= $typ->getTtl();
        if ($ttl < 0 || $ttl > self::MAX_TTL) {
            throw new ChybaPushNotifikace('Doba, po kterou se má notifikace doručovat, je mimo povolený rozsah.');
        }
        $tag ??= $typ->getTag();
        if ($tag !== null && preg_match(self::VZOR_TAGU, $tag) !== 1) {
            throw new ChybaPushNotifikace('Tag notifikace smí mít nejvýš 32 znaků: písmena bez diakritiky, číslice, pomlčku a podtržítko.');
        }

        $zprava = new self(
            typ: $typ,
            nadpis: $nadpis,
            text: $text,
            odkaz: OdkazNotifikace::normalizuj($odkaz),
            ttl: $ttl,
            urgence: $urgence ?? $typ->getUrgence(),
            tag: $tag,
        );
        if (strlen($zprava->getPayload('https://gamecon.cz')) > self::MAX_DELKA_PAYLOADU) {
            throw new ChybaPushNotifikace('Notifikace je moc dlouhá, zkrať text.');
        }

        return $zprava;
    }

    /**
     * Obsah, který dostane service worker (web/sw.js). Push služba ho vidí jen zašifrovaný.
     */
    public function getPayload(string $urlWebu): string
    {
        return json_encode(
            [
                'title' => $this->nadpis,
                'body'  => $this->text,
                'url'   => OdkazNotifikace::getUrl($urlWebu, $this->odkaz),
                'tag'   => $this->tag,
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
