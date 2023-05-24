<?php

declare(strict_types=1);

namespace Gamecon\Symfony\Generator;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AbstractIdGenerator;
use Gamecon\Symfony\Entity\User;

class GameconUserIdPseudoGenerator extends AbstractIdGenerator
{
    public function generateId(EntityManagerInterface $em, $entity)
    {
        if (!($entity instanceof User)) {
            throw new InvalidArgumentException(
                'User ID generator can be used only for ' . User::class . ' entity'
            );
        }
        if (!$entity->getEmail()) {
            throw new InvalidArgumentException(
                'User ID generator can be used only for user with already set email'
            );
        }
        $result        = $em->getConnection()->executeQuery(<<<SQL
SELECT id_uzivatele
FROM uzivatele_hodnoty
WHERE email1_uzivatele = :email
SQL,
            ['email' => $entity->getEmail()],
        );
        $gameconUserId = $result->fetchOne();
        if (!$gameconUserId) {
            throw new InvalidArgumentException("Gamecon user not found by email '{$entity->getEmail()}'");
        }
        return (int)$gameconUserId;
    }

    public function isPostInsertGenerator(): bool
    {
        return false;
    }

}
