<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\MissingGoogleApiCredentials;

class GoogleApiCredentials
{
    /**
     * @return static
     * @throws MissingGoogleApiCredentials
     */
    public static function createFromGlobals(): self {
        if (!defined('GOOGLE_API_CREDENTIALS') || !GOOGLE_API_CREDENTIALS || !is_array(GOOGLE_API_CREDENTIALS)) {
            throw new MissingGoogleApiCredentials(
                "Missing google API credentials. Expected them as an array in globally accessed constant 'GOOGLE_API_CREDENTIALS'"
            );
        }
        return new static(GOOGLE_API_CREDENTIALS);
    }

    /** @var array */
    private $values;

    public function __construct(array $values) {
        $this->values = $values;
    }

    public function getValues(): array {
        return $this->values;
    }

    public function getClientId(): string {
        return $this->getValues()['web']['client_id'];
    }
}
