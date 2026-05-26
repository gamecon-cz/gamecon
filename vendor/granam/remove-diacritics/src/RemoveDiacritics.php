<?php declare(strict_types=1);

namespace Granam\RemoveDiacritics;

use Granam\Scalar\Tools\ToString;
use Granam\Strict\Object\StrictObject;
use Granam\String\StringInterface;

class RemoveDiacritics extends StrictObject
{

    /**
     * @param string|StringInterface|\Stringable $value
     * @return string $withoutDiacritics
     * @throws \Granam\Scalar\Tools\Exceptions\WrongParameterType
     */
    public static function removeDiacritics($value): string
    {
        $value = ToString::toString($value);
        $withoutDiacritics = '';
        $specialsReplaced = static::replaceSpecials($value);
        \preg_match_all('~(?<words>\w*)(?<nonWords>\W*)~u', $specialsReplaced, $matches);
        foreach ($matches['words'] as $index => $word) {
            $wordWithoutDiacritics = \transliterator_transliterate('Any-Latin; Latin-ASCII', $word);
            $withoutDiacritics .= $wordWithoutDiacritics . $matches['nonWords'][$index];
        }

        return $withoutDiacritics;
    }

    protected static function replaceSpecials(string $string): string
    {
        return \str_replace(
            ['̱', '̤', '̩', 'Ə', 'ə', 'ʿ', 'ʾ', 'ʼ',],
            ['', '', '', 'E', 'e', "'", "'", "'",],
            $string
        );
    }

    /**
     * Creates from 'Fóő, Bâr ảnd Bäz' constant-like value 'foo_bar_and_baz'.
     * This will NOT place_underscore before upper-cased character, 'ÜbberID' = 'ubberid', NOT 'ubber_i_d',
     * @param string|StringInterface $value
     * @return string
     * @throws \Granam\Scalar\Tools\Exceptions\WrongParameterType
     */
    public static function toConstantLikeValue($value): string
    {
        $withoutDiacritics = self::removeDiacritics($value);
        // also collapses multiple underscores to a single one (see the plus on end of char groups and no underscore inside)
        $underscored = \preg_replace('~[^a-zA-Z0-9]+~', '_', \trim($withoutDiacritics));
        $trimmed = \strtolower(\trim($underscored, '_'));
        if ($trimmed !== '') {
            return $trimmed;
        }

        return $value !== ''
            ? '_'
            : '';
    }

    /**
     * Creates from 'Fóő, Bâr ảnd Bäz' constant-like value 'foo_bar_and_baz'
     * @param string|StringInterface $value
     * @return string
     * @throws \Granam\Scalar\Tools\Exceptions\WrongParameterType
     * @deprecated Use toConstantLikeValue instead
     */
    public static function toConstant($value): string
    {
        return static::toConstantLikeValue($value);
    }

    /**
     * Creates from 'Fóő, Bâr ảnd Bäz' constant-like name 'FOO_BAR_AND_BAZ'
     * @param string|StringInterface $value
     * @return string
     * @throws \Granam\Scalar\Tools\Exceptions\WrongParameterType
     */
    public static function toConstantLikeName($value): string
    {
        return \strtoupper(static::toConstantLikeValue($value));
    }

    /**
     * Converts 'OnceUponATime' to 'once_upon_a_time'
     * @param string|StringInterface $value
     * @return string
     * @throws \Granam\Scalar\Tools\Exceptions\WrongParameterType
     */
    public static function camelCaseToSnakeCase($value): string
    {
        $value = ToString::toString($value);
        $parts = \preg_split('~([[:upper:]][[:lower:]]+|[^[:upper:]\d]*[[:lower:]]+|\d+)~u', $value, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $underscored = \preg_replace('~_{2,}~', '_', \implode('_', $parts));

        return \strtolower($underscored);
    }

    /**
     * Turns 'při__zdi' into PřiZdi
     * @param string|StringInterface $value
     * @return string
     * @throws \Granam\Scalar\Tools\Exceptions\WrongParameterType
     */
    public static function snakeCaseToCamelCase($value): string
    {
        $value = ToString::toString($value);
        $parts = explode('_', $value);
        $camelCased = '';
        foreach ($parts as $part) {
            $camelCased .= mb_convert_case($part, MB_CASE_TITLE);
        }
        return $camelCased;
    }

    /**
     * Converts '\Granam\String\StringTools' to 'StringTools' (removes namespace)
     * @param string|StringInterface $className
     * @return string
     * @throws \Granam\Scalar\Tools\Exceptions\WrongParameterType
     */
    public static function getClassBaseName($className): string
    {
        $className = ToString::toString($className);
        if (\preg_match('~\\\(?<basename>[^\\\]+)$~u', $className, $matches) === 0) {
            return $className; // no namespace at all
        }

        return $matches['basename']; // without namespace
    }

    /**
     * Converts '\Granam\String\StringTools' to 'string_tools' (removes namespace and converts to snake_case)
     * @param string|StringInterface $className
     * @return string
     * @throws \Granam\Scalar\Tools\Exceptions\WrongParameterType
     */
    public static function camelCaseToSnakeCasedBasename($className): string
    {
        return static::camelCaseToSnakeCase(static::getClassBaseName($className));
    }

    /**
     * @param string|StringInterface $valueName
     * @param string|StringInterface $prefix
     * @return string
     * @throws \Granam\Scalar\Tools\Exceptions\WrongParameterType
     */
    public static function assembleGetterForName($valueName, $prefix = 'get'): string
    {
        return self::assembleMethodName($valueName, $prefix);
    }

    /**
     * @param string|StringInterface $valueName
     * @param string|StringInterface $prefix
     * @return string
     * @throws \Granam\Scalar\Tools\Exceptions\WrongParameterType
     */
    public static function assembleIsForName($valueName, $prefix = 'is'): string
    {
        return self::assembleMethodName($valueName, $prefix);
    }

    /**
     * @param string|StringInterface $valueName
     * @param string|StringInterface $prefix
     * @return string
     * @throws \Granam\Scalar\Tools\Exceptions\WrongParameterType
     */
    public static function assembleSetterForName($valueName, $prefix = 'set'): string
    {
        return self::assembleMethodName($valueName, $prefix);
    }

    /**
     * Converts '\Granam\String\StringObject α' with prefix 'define' into 'defineStringObjectA'
     * @param string|StringInterface $fromValue
     * @param string|StringInterface $prefix
     * @return string
     * @throws \Granam\Scalar\Tools\Exceptions\WrongParameterType
     */
    public static function assembleMethodName($fromValue, $prefix = ''): string
    {
        $methodName = static::toVariableName($fromValue);
        if ($prefix === '') {
            return $methodName;
        }
        $prefix = \trim(ToString::toString($prefix));
        if ($prefix === '') {
            return $methodName;
        }

        return $prefix . \ucfirst($methodName);
    }

    /**
     * Converts '\Granam\String\StringObject α' into 'stringObjectA'
     * @param string|StringInterface $value
     * @return string
     * @throws \Granam\Scalar\Tools\Exceptions\WrongParameterType
     */
    public static function toVariableName($value): string
    {
        $snakeCased = self::camelCaseToSnakeCasedBasename($value); // removes namespace and replaces non-low-ascii by underscores
        $contantLike = self::toConstantLikeValue($snakeCased); // removes diacritics
        $parts = \explode(
            '_', // to get parts
            $contantLike
        );
        $variableName = \implode(
            \array_map(
                'ucfirst', // every part has upper-cased first letter
                $parts
            )
        );

        return \lcfirst($variableName); // thisIsYourFancyVariableName
    }

    /**
     * This method originates from Dropbox,
     *
     * @link http://dropbox.github.io/dropbox-sdk-php/api-docs/v1.1.x/source-class-Dropbox.Util.html#14-32
     *
     * If the given string begins with the UTF-8 BOM (byte order mark), remove it and
     * return whatever is left. Otherwise, return the original string untouched.
     *
     * Though it's not recommended for UTF-8 to have a BOM, the standard allows it to
     * support software that isn't Unicode-aware.
     *
     * @param string|StringInterface $string an UTF-8 encoded string
     * @return string
     * @throws \Granam\String\Exceptions\CanNotRemoveBom
     * @throws \Granam\Scalar\Tools\Exceptions\WrongParameterType
     */
    public static function stripUtf8Bom($string): string
    {
        $string = ToString::toString($string);
        if (\substr_compare($string, "\xEF\xBB\xBF", 0, 3) !== 0) {
            return $string;
        }
        $withoutBom = \substr($string, 3);
        if ($withoutBom === false) {
            throw new Exceptions\CanNotRemoveBom('Can not remove BOM from given string ' . $string);
        }

        return $withoutBom;
    }

    public static function toUtf8(string $string, string $sourceEncoding): string
    {
        /** @link https://stackoverflow.com/questions/8233517/what-is-the-difference-between-iconv-and-mb-convert-encoding-in-php# */
        // iconv is just a wrapper of C iconv function, therefore it is platform-related
        // mb_convert_encoding works same regardless of platform
        return \mb_convert_encoding($string, $sourceEncoding, 'UTF-8'); //
    }

    /**
     * Useful to convert GIT status output for example: 'O \305\276ivot\304\233.html' => 'O životě.html'
     * see @link https://stackoverflow.com/questions/22827239/how-to-make-git-properly-display-utf-8-encoded-pathnames-in-the-console-window
     * and @link https://stackoverflow.com/questions/34934653/iso-8859-1-octal-back-to-normal-characters
     *
     * @param string|StringInterface $string $string
     * @return string
     */
    public static function octalToUtf8($string): string
    {
        $string = ToString::toString($string);
        /** @var array|string[][] $matches */
        if (!\preg_match_all('~(?<octal>[\\\]\d{3})~', $string, $matches)) {
            return $string;
        }
        foreach ($matches['octal'] as $octal) {
            $octalChar = \ltrim($octal, '\\');
            $packed = \pack('H*', \base_convert($octalChar, 8, 16)); // UTF-8 is de facto base 16
            $string = \str_replace('\\' . $octalChar, $packed, $string);
        }

        return $string;
    }

    /**
     * 'Cześć, proszę pana' = 'czescProszcePana'
     * @param string|StringInterface $value
     * @return string
     */
    public static function toCamelCaseId($value): string
    {
        return static::toVariableName($value);
    }

    /**
     * 'Cześć, proszę pana' = 'czesc_proszce_pana'
     * @param string|StringInterface $value
     * @return string
     */
    public static function toSnakeCaseId($value): string
    {
        $asVariableName = static::toVariableName($value);
        return static::camelCaseToSnakeCase($asVariableName);
    }
}
