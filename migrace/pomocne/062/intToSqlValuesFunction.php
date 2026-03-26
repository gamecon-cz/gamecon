<?php
return static function(array $values, \PDO $db): string {
  return implode(
    ",\n", // ('foo','bar'),('baz','quz')
    array_map(
      function(array $row) use($db): string {
        return sprintf(
          '(%s)', // ('foo','bar')
          implode(
            ',', // 'foo','bar'
            array_map(
              function(?string $value) use($db): string {
                if ($value === null) {
                  return 'NULL';
                }
                if (preg_match('~^[(].+[)]$~', $value)) {
                  return $value; // some sub-select
                }
                return sprintf("'%s'", substr($db->quote($value), 1, -1));
              },
              $row
            )
          )
        );
      },
      $values
    )
  );
};
