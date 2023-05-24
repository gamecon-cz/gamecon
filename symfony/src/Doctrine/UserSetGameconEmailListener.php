<?php

declare(strict_types=1);

namespace Gamecon\Symfony\Doctrine;

use Doctrine\ORM\Event\PostLoadEventArgs;
use Gamecon\Symfony\Entity\User;

class UserSetGameconEmailListener
{
    public function postLoad(User $user, PostLoadEventArgs $postLoadEventArgs)
    {
        if (!$user->getId()) {
            return;
        }

        $result = $postLoadEventArgs->getObjectManager()->getConnection()->executeQuery(<<<SQL
SELECT email1_uzivatele
FROM uzivatele_hodnoty
WHERE id_uzivatele = :id
SQL,
            ['id' => $user->getId()],
        );
        $email  = $result->fetchOne();
        $user->setEmail((string)$email);
    }
}
