<?php

/**
 * SmartyPants-like smart punctation optimized for Czech
 *
 * It takes html string and replaces punctation in non-html tag areas according
 * to $defaultRules (or rules you set). Rules are of two types:
 *
 * - 'regular expression' => 'replacement'
 * - 'regular expression' => ['odd replacement', 'even replacement']
 *
 * Currently replacements are just pure plaintext.
 */
class Smartyp
{

    private
        $flip, // cache for flipflop rules
        $rules,
        $rulesInternal;

    private static
        $defaultClean = [
        '@“([^”]+)”@s' => '„$1“',   // replace english quotes by czech
    ],
        $defaultRules = [
        '@ - @'    => ' – ',
        '@\.\.\.@' => '…',
        '@"@'      => ['„', '“'],
    ];

    static function defaultTransform($text)
    {
        static $defaultInstance;
        if (!$defaultInstance) $defaultInstance = new self;
        return $defaultInstance->transform($text);
    }

    function setRules($rules)
    {
        if ($this->rules === $rules) return;
        $this->rules         = $rules;
        $this->rulesInternal = null; // invalidate compiled rules
    }

    function transform($text)
    {
        $text          = $this->clean($text);
        $this->flip    = []; // reset cache for flipflop rules
        $rulesInternal = $this->getRulesInternal();
        return preg_replace_callback('@(^|(?:p|h\d|strong|em|/)>)([^<]+)(<|$)@', function ($m) use ($rulesInternal) {
            $r = preg_replace_callback_array($rulesInternal, $m[2]);
            return $m[1] . $r . $m[3];
        }, $text);
    }

    //////////////////
    // Hidden stuff //
    //////////////////

    /**
     * Preprocess malformed parts
     */
    private function clean($text)
    {
        return preg_replace(
            array_keys(self::$defaultClean),
            array_values(self::$defaultClean),
            $text,
        );
    }

    private function compileRules()
    {
        $this->rulesInternal = [];
        $flip                = &$this->flip;
        foreach ($this->getRules() as $key => $rule) {
            if (is_array($rule)) {
                $this->rulesInternal[$key] = function ($m) use ($rule, &$flip, $key) {
                    if (!isset($flip[$key])) {
                        $flip[$key] = true;
                        return $rule[0];
                    } else {
                        $flip[$key] = null;
                        return $rule[1];
                    }
                };
            } else {
                $this->rulesInternal[$key] = function ($m) use ($rule) {
                    return $rule;
                };
            }
        }
    }

    private function getRules()
    {
        if (!isset($this->rules)) {
            $this->rules = self::$defaultRules;
        }
        return $this->rules;
    }

    private function getRulesInternal()
    {
        if (!isset($this->rulesInternal)) $this->compileRules();
        return $this->rulesInternal;
    }

}

// Required polyfill for php < 7.0 (by Symfony)
if (!function_exists('preg_replace_callback_array')) {
    function preg_replace_callback_array(array $patterns, $subject, $limit = -1, &$count = 0)
    {
        $count  = 0;
        $result = '' . $subject;
        if (0 === $limit) {
            return $result;
        }
        foreach ($patterns as $pattern => $callback) {
            $result = preg_replace_callback($pattern, $callback, $result, $limit, $c);
            $count  += $c;
        }
        return $result;
    }
}
