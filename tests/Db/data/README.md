Pokud změníš ROCNIK, musíš exportovat i novou holou testovací databázi.

V `tests/_zavadec.php` dej exit před `register_shutdown_function` (aby se nesmazala testovací databáze na konci skriptu), a spusť testy.

Tím se vytvoří nová testovací databáze (něco jako `gamecon_test_67dd25114527a8.19200383`), kterou můžeš exportovat a použít jako novou holou testovací databázi.
