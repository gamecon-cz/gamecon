<?php

namespace Gamecon\XTemplate;

use Gamecon\XTemplate\Exceptions\XTemplateRecompilationException;

/**
 * XTemplate (partial) implementation using compiled php scripts for speedup.
 * Interface is same as original implementation but does not support all xtpl
 * features and methods.
 */
class XTemplate
{

    protected        $tc;     // compiled template class instance
    protected string $root;     // compiled template class instance
    protected static $cacheDir = null;  // where to store compiled templates (null = same as template)

    /**
     * Prepares template from given file
     */
    function __construct(string $file)
    {
        $this->tc   = $this->compiledTemplate($file);
        $this->root = basename($file, '.xtpl');
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * Assigns values to variables which can be then used in template
     * Possible uses:
     *  assign(array('key1' => 'value1', 'key2' => 'value2'))
     *    - sets key-value pairs as is
     *  assign('name', 'value')
     *    - sets single key-value pair as is
     *  assign('name', Templatable $object)
     *    - prepares object to be used - object must implement Templatable
     *      interface.
     *    - through this interface concrete keys are requested at runtime
     *      (TODO maybe do not request at runtime but allow direct compilation
     *      of simple getters)
     *    - temptable object may be used also as value in array mode (see above)
     */
    function assign($keyOrArray, $value = null)
    {
        if (is_array($keyOrArray) && $value === null) {
            $this->tc->context = array_merge($this->tc->context, $keyOrArray);
        } else {
            $this->tc->context[$keyOrArray] = $value;
        }

        return $this;
    }

    /**
     * Prints some block's parsed contents to output.
     * See parse for block naming conventions.
     * @todo add punch-trough mechanism to avoid caching of values
     */
    function out($block)
    {
        echo $this->text($block);
    }

    /**
     * Parses/prepares given block for output.
     * Assigned variables' values are "burned" into parsed text. Blockname is full
     * path from root, ie "myRootElement.someBlock.anotherBlock"
     */
    function parse($block)
    {
        $m = 'parse_' . strtr($block, '.', '_');
        $this->tc->$m();
    }

    /**
     * Shorthand for looping through array, assigning and parsing block.
     * Mimics php's foreach style: foreach $elements as $name { parse $block }
     */
    function parseEach($elements, $name, $block)
    {
        $m = 'parse_' . strtr($block, '.', '_');
        foreach ($elements as $e) {
            $this->tc->context[$name] = $e; // assign
            $this->tc->$m(); // parse
        }
    }

    /**
     * Get/set caching directory for templates (null = same as template)
     */
    static function cache(/* variadic set/get */): ?string
    {
        if (func_num_args() == 1) {
            self::$cacheDir = func_get_arg(0);

            return null;
        }

        return self::$cacheDir;
    }

    /**
     * Returns block's parsed contents
     */
    function text(string $block)
    {
        $m = strtr($block, '.', '_');

        return $this->tc->$m();
    }

    ////////////////////////
    // Protected contents //
    ////////////////////////

    protected        $outline      = [];     // internal representation of source document
    protected        $dependencies = [];// dependencies (included files) for outline
    protected static $class        =         // template for whole compiled class
        '<?php
  class <name> {
    public $context = array();
    public $dependencies = array(<dependencies>);
    protected $buffer = array();
    protected $current = "";        // currently buffered block
    <methods>
  }
  ';
    protected static $blockMethod  =   // template for compiled block (=> method)
        '
    function <name>() {
      if($this->current == "<name>") {
        if(!empty($this->buffer["<name>"])) {
          $out = $this->buffer["<name>"] . ob_get_clean();
          $this->buffer["<name>"] = "";
        } else {
          $out = ob_get_clean();
        }
        $this->current = "";
        return $out;
      } else {
        $out = $this->buffer["<name>"] ?? "";
        $this->buffer["<name>"] = "";
        return $out;
      }
    }

    function parse_<name>() {
      if($this->current != "<name>") {
        if($this->current) {
          $this->buffer[$this->current] = ($this->buffer[$this->current] ?? "") . ob_get_clean();
        }
        ob_start();
        $this->current = "<name>";
      }
      ?><html><?php
    }
  ';

    /**
     * Converts xtemplate variable literals to php tags, returns converted text
     */
    protected function convertVariables(string $text)
    {
        $text = preg_replace('@{([a-zA-Z][a-zA-Z0-9_]*)}@', '<?=isset($this->context["$1"])?$this->context["$1"]:\'\'?>', $text);
        $text = preg_replace('@{([a-zA-Z]+)\.([a-zA-Z_]+)}@', '<?=$this->context["$1"]->$2()?>', $text);
        $text = preg_replace('@{([a-zA-Z]+)\.([a-zA-Z]+)\.([a-zA-Z]+)}@', '<?=$this->context["$1"]->$2()->$3()?>', $text);

        return $text;
    }

    /**
     * Returns instance of compiled template class
     * @todo compiled class may not be required multiple times because of
     *  redeclaration issues. Some reset, debugs, ...?
     * @todo dependency injection of cache location
     */
    protected function compiledTemplate(string $file)
    {
        $cFile = self::$cacheDir
            ? self::$cacheDir . '/' . md5($file) . '.php'
            : dirname($file) . '/' . basename($file, '.xtpl') . '.xtpc';
        $cName = $this->generateClassName($file);

        // main template modification check & load
        $compiledModified = file_exists($cFile)
            ? filemtime($cFile)
            : 0;
        $templateModified = filemtime($file);
        if ($compiledModified < $templateModified || $compiledModified < $this->libraryModified()) {
            $this->outlineRead($file);
            file_put_contents($cFile, $this->outlineCompiled($cName));
        }
        require_once $cFile;
        $t = new $cName();

        // dependecies modification check
        $modified = false;
        foreach ($t->dependencies as $d) {
            if (filemtime($cFile) < filemtime($d)) { // dependency enforced recompilation
                $this->outlineRead($file);
                file_put_contents($cFile, $this->outlineCompiled($cName));
                $modified = true;
            }
        }
        if ($modified) {
            throw new XTemplateRecompilationException();
        }

        // return
        return $t;
    }

    /**
     * @return string cached modification time of this file
     */
    private function libraryModified()
    {
        static $modified = null;
        if ($modified === null) {
            $modified = filemtime(__FILE__);
        }

        return $modified;
    }

    /**
     * Function for generation of unique but semi-readable classnames for compiled
     * templates.
     */
    private function generateClassName($path)
    {
        $random = md5($path);
        $random = hexdec(substr($random, 0, 8)); // 4 bytes
        $random = substr((string)$random, -6);

        $safeName = str_replace('-', '', ucfirst(basename($path, '.xtpl')));

        return $safeName . $random . 'Tpl';
    }

    /**
     * Adds referenced block with given name to outline. Target node in outline
     * is $node.
     */
    protected function nodePutblock(array $node, string $blockname)
    {
        if (!$node) {
            // skip root nodes
            return;
        }
        $parent                 = $this->toIdent($node);
        $child                  = $this->toIdent(array_merge($node, [$blockname]));
        $this->outline[$parent] = ($this->outline[$parent] ?? '') . '<?=$this->' . $child . '()?>';
    }

    /**
     * Adds text to specified node in outline.
     */
    protected function nodePuttext(array $node, string $text)
    {
        if (!$node) return; // skip root nodes
        $nodeId                 = $this->toIdent($node);
        $text                   = $this->convertVariables($text);
        $this->outline[$nodeId] = ($this->outline[$nodeId] ?? '') . $text;
    }

    /**
     * Compiles outline into (cached) php class used for output
     * @todo quotes
     * @todo class naming
     */
    protected function outlineCompiled(string $cName)
    {
        $methods = '';
        foreach ($this->outline as $nodeId => $block) {
            $methods .= strtr(self::$blockMethod, [
                '<name>' => $nodeId,
                '<html>' => $this->reformat($block),
            ]);
        }
        $class = strtr(self::$class, [
            '<name>'         => $cName,
            '<methods>'      => $methods,
            '<dependencies>' => '"' . implode('","', $this->dependencies) . '"',
        ]);

        return $class;
    }

    /**
     * Reads given file into internal representation - outline
     */
    protected function outlineRead(string $file)
    {
        // split source file by block delimiters
        $delim = '<!--\s*(begin|end):\s*([a-zA-Z][a-zA-Z0-9]*)\s*-->';
        $f     = file_get_contents($file);
        $f     = preg_replace_callback('@{FILE\s+"([^"]+)"}@', function ($m) use ($file) {
            $m[1]                 = $this->convertIncludeFilePath((string)$m[1], $file);
            $this->dependencies[] = $m[1];

            return file_get_contents($m[1]);
        }, $f);
        $bloky = preg_split('@' . $delim . '@', $f, -1, PREG_SPLIT_DELIM_CAPTURE);
        // inits
        $len  = count($bloky);
        $path = [];
        // walk through loaded blocks and delimiters
        for ($i = 0; $i < $len; $i++) {
            $blok = $bloky[$i];
            if ($blok == 'begin') {
                // beginning of block
                $i++; // skip to next token (which is block's name)
                array_push($path, $bloky[$i]);
            } elseif ($blok == 'end') {
                // end of block
                $i++;
                $last = array_pop($path);
                $this->nodePutblock($path, $last);
            } else {
                // contents of block
                $this->nodePuttext($path, $blok);
            }
        }
    }

    private function convertIncludeFilePath(string $includePath, string $parentTemplatePath): string
    {
        if (!str_starts_with($includePath, './')) {
            return $includePath;
        }
        /** '/foo/bar/baz.xpl' -> '/foo/bar' */
        $parentTemplateDir = dirname($parentTemplatePath);

        /** './qux.xpl' -> '/foo/bar/qux.xpl' */
        return $parentTemplateDir . '/' . str_replace('./', '', $includePath);
    }

    /**
     * Reformats html code to make it readable in compiled class
     */
    protected function reformat($str)
    {
        $o = $str;
        //$o = trim($str); //TODO this also fails for example when multiple items should be separated by space
        //$o = preg_replace('@[ \t\n]+@', ' ', $o); //TODO this is not possible because of javascript comments to end of line
        return $o;
    }

    protected function toIdent(array $node): string
    {
        return implode('_', $node);
    }

}
