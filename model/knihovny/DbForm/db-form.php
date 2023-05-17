<?php

use Gamecon\XTemplate\XTemplate;

class DbForm
{

    private $table;
    /** @var null|DbFormField[] */
    private $fields;
    private $postName             = 'cDbForm';
    private $lastSaveChangesCount = 0;

    /**
     * Creates default (full) form for given table
     * @todo check if table exists?
     */
    public function __construct($table)
    {
        $this->table = $table;
    }

    /**
     * Creates new field, field type (class) based on given description.
     * This should be overriden in inherited per-project implementations to add
     * custom field classes fitting project needs.
     * @todo thus, make this abstract and force custom implementation?
     */
    protected function fieldFromDescription($d): DbFormField
    {
        if ($d['Key'] == 'PRI') {
            return new DbffPkey($d);
        }
        if (in_array($d['Type'], ['text', 'shorttext', 'mediumtext', 'longtext'])) {
            return new DbffText($d);
        }
        if ($d['Type'] === 'tinyint(1)') {
            return new DbffCheckbox($d);
        }
        // fallback to string
        return new DbffString($d);
    }

    /**
     * Returns assoc. array of table / form fields. Array is structured like
     * 'column_name' => (obj)Field
     * @return DbFormField[]
     */
    protected function fields(): array
    {
        if (!isset($this->fields)) {
            $this->fieldsInit();
        }
        return $this->fields;
    }

    /** Responsibility obvious */
    protected function fieldsInit()
    {
        $this->fields = [];
        foreach (dbDescribe($this->table()) as $d) {
            $f                        = $this->fieldFromDescription($d);
            $this->fields[$f->name()] = $f;
        }
    }

    /**
     * Returns full editor / form html code
     * @todo inline() alternative for inline editor (excel style)
     * @todo don't rely on xtpl or only bundle compiled result
     */
    public function full()
    {
        $t = new XTemplate(__DIR__ . '/db-form.xtpl');
        foreach ($this->fields() as $f) {
            $t->assign('field', $f);
            if ($f->display() == DbFormField::RAW) {
                $t->parse('form.raw');
            } else if ($f->display() == DbFormField::CUSTOM) {
                $t->parse('form.custom');
            } else {
                $t->parse('form.row');
            }
        }
        $t->assign('submitted', $this->postName() . '[submitted]');
        $t->parse('form');
        return $t->text('form');
        //TODO add one field to $this->postName to check in processPost
    }

    public function loadDefaults()
    {
        foreach ($this->fields() as $f) {
            $f->value($f->default());
        }
    }

    /**
     * Fills form with given database row $r (as associative array)
     */
    public function loadRow($r, bool $strictlyAllColumns = true)
    {
        foreach ($this->fields() as $f) {
            if (!array_key_exists($f->name(), $r)) {
                if ($strictlyAllColumns) {
                    throw new LogicException("Chybí hodnota pro sloupec '{$f->name()}'");
                } else {
                    continue;
                }
            }
            $f->value($r[$f->name()]);
        }
    }

    /**
     * Fills form with post data
     * @todo prefix might or might not be set here, individual post variables are handled by fields themselves
     */
    protected function loadPost()
    {
        foreach ($this->fields() as $f) {
            $f->loadPost();
        }
    }

    /**
     * Gets/sets POST namespace (/prefix) where DbForm variable(s) will be stored
     * @todo fix this to accordance with inherited classes
     */
    public function postName($val = null)
    {
        if (isset($val)) {
            $this->postName = $val;
        }
        return $this->postName;
    }

    /**
     * Processes post data for this form (if any) and redirects to caller,
     * otherwise does nothing.
     * @param callable $validationCallback = null
     * @param bool $redirect = true
     * @return int|bool
     * @todo remove is_ajax and die in favor of methods on request object or
     *  something like that, which should be passed as parameter or injected.
     * @todo what if fields list (in post) is incomplete? Throw error? Allow this
     *  (for case of restricted privileges) but only for update? ...?
     */
    public function processPost(
        callable $validationCallback = null,
        bool     $redirect = true,
    )
    {
        if (empty($_POST[$this->postName()])) {
            return false;
        }
        try {
            $this->loadPost();
            $newId = $this->save($validationCallback);
        } catch (Exception $e) {
            if (is_ajax()) {
                die(json_encode(['error' => $e->getMessage()]));
            } else {
                throw $e;
            }
        }
        if (!$redirect) {
            return $newId ?: true;
        }
        // redirect
        if (is_ajax()) {
            die(json_encode(['id' => $newId]));
        }
        header('Location: ' . $_SERVER['HTTP_REFERER'], true, 303);
        return null;
    }

    public function save(
        callable $validationCallback = null,
    )
    {
        // preparations when needed
        foreach ($this->fields() as $f) {
            $f->preInsert();
        }
        // master record update
        $r     = [];
        $pkey  = null;
        $newId = null;
        foreach ($this->fields() as $f) {
            $r[$f->name()] = $f->value();
            if ($f instanceof DbffPkey) {
                $pkey = $f;
            }
        }
        if ($validationCallback) {
            $validationCallback($r);
        }
        if ($pkey->value()) {
            $result                     = dbUpdate($this->table(), $r, [$pkey->name() => $pkey->value()]);
            $this->lastSaveChangesCount = dbAffectedOrNumRows($result);
        } else {
            dbInsert($this->table(), $r);
            $newId                      = dbInsertId();
            $this->lastSaveChangesCount = 1;
        }
        // final cleanup
        foreach ($this->fields() as $f) {
            $f->postInsert();
        }
        return $newId;
    }

    public function lastSaveChangesCount(): int
    {
        return $this->lastSaveChangesCount;
    }

    /**
     * Returns table name. Form is created based on this table
     */
    protected function table()
    {
        return $this->table;
    }

}
