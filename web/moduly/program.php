<?php

$this->bezStranky(true);

$program = new Program;

?>
<!DOCTYPE html>
<html>
<head>
  <title>My First React Example</title>
  <script>
    function mojeNotifikace() {
      alert('něco se stalo');
    }
  </script>
  <?php $program->zaregistrujJsObserver('mojeNotifikace') ?>
  <?=$program->htmlHlavicky()?>
</head>
<body>

  <?=$program->htmlObsah()?>

</body>
</html>
