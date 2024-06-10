<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database migration</title>
    <style>
        body {
            background-color: #fff;
            color: #222;
            font-family: Helvetica, sans-serif;
            font-size: 20px;
            margin: 2em;
        }

        p {
            max-width: 25em;
        }

        body p:first-child {
            max-width: initial;
        }

        input[type=submit] {
            min-width: 150px;
            min-height: 40px;
            font-family: inherit;
            font-size: 14px;
            margin-top: 1em;
        }

        pre {
            font-size: 14px;
            color: #ccc;
            background-color: #222;
            border-radius: 4px;
            padding: 1em;
            display: inline-block;
            min-width: 40em;
        }
    </style>
</head>
<body>
<?php
/**
 * @var string $postName
 * @var boolean $confirmed
 */
?>

<?php if (!$confirmed): ?>

    <p>There is a database migration pending. Press <i>Confirm</i> to apply it (might take some time).</p>

    <form method="POST">
        <input type="hidden" name="<?= $postName ?>" value="1">
        <input type="submit" value="Confirm" onclick="this.disabled = true; this.form.submit()">
    </form>

<?php else: ?>

<p>Database migration in progress, output:</p>

<!-- pre must be last tag - after that, content is generated dynamically -->
<pre><?php endif ?>
