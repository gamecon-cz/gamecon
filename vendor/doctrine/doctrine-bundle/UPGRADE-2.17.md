UPGRADE FROM 2.16 to 2.17
=========================

DoctrineExtension
=================

Minor breaking change:
`Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension` no
longer extends
`Symfony\Bridge\Doctrine\DependencyInjection\AbstractDoctrineExtension`.

Configuration
-------------

### The `doctrine.orm.entity_managers.some_em.report_fields_where_declared` configuration option is deprecated

This option is a no-op when using `doctrine/orm` 3 and has been conditionally
deprecated. You should stop using it as soon as you upgrade to Doctrine ORM 3.

### The `doctrine.dbal.connections.some_connection.disable_type_comments` configuration option is deprecated

This option is a no-op when using `doctrine/dbal` 4 and has been conditionally
deprecated. You should stop using it as soon as you upgrade to Doctrine DBAL 4.

### The `doctrine.dbal.connections.some_connection.use_savepoints` configuration option is deprecated

This option is a no-op when using `doctrine/dbal` 4 and has been conditionally
deprecated. You should stop using it as soon as you upgrade to Doctrine DBAL 4.
