<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import\Activities;

use Gamecon\Admin\Modules\Aktivity\Import\Activities\Exceptions\DuplicatedUnifiedKeyException;

class ImportUsersCache
{
    private $users;
    private $usersPerId;
    private $usersPerEmail;
    private $usersPerName;
    private $usersPerNick;
    private $usersPerNameWithNick;
    private $fromNameKeyUnifyDepth = ImportKeyUnifier::UNIFY_UP_TO_LETTERS;
    private $fromNickKeyUnifyDepth = ImportKeyUnifier::UNIFY_UP_TO_LETTERS;
    private $fromNameWithNickKeyUnifyDepth = ImportKeyUnifier::UNIFY_UP_TO_LETTERS;
    private $fromEmailKeyUnifyDepth = ImportKeyUnifier::UNIFY_UP_TO_DIACRITIC;

    public function getUserById(int $id): ?\Uzivatel {
        return $this->getUsersPerId()[$id] ?? null;
    }

    public function getUserByEmail(string $email): ?\Uzivatel {
        if (strpos($email, '@') === false) {
            return null;
        }
        $key = ImportKeyUnifier::toUnifiedKey($email, [], ImportKeyUnifier::UNIFY_UP_TO_DIACRITIC);
        return $this->getUsersPerEmail()[$key] ?? null;
    }

    public function getUserByName(string $name): ?\Uzivatel {
        $key = ImportKeyUnifier::toUnifiedKey($name, [], $this->fromNameKeyUnifyDepth);
        return $this->getUsersPerName()[$key] ?? null;
    }

    public function getUserByNick(string $nick): ?\Uzivatel {
        $key = ImportKeyUnifier::toUnifiedKey($nick, [], $this->fromNickKeyUnifyDepth);
        return $this->getUsersPerNick()[$key] ?? null;
    }

    public function getUserByNameWithNick(string $nameWithNick): ?\Uzivatel {
        $key = ImportKeyUnifier::toUnifiedKey($nameWithNick, [], $this->fromNameWithNickKeyUnifyDepth);
        return $this->getUsersPerNameWithNick()[$key] ?? null;
    }

    /**
     * @return \Uzivatel[]
     */
    private function getUsersPerId(): array {
        if ($this->usersPerId === null) {
            $this->usersPerId = [];
            foreach ($this->getUsers() as $user) {
                $this->usersPerId[$user->id()] = $user;
            }
        }
        return $this->usersPerId;
    }

    /**
     * @return \Uzivatel[]
     */
    private function getUsersPerEmail(): array {
        if ($this->usersPerEmail === null) {
            $this->usersPerEmail = [];
            $conflictingKeys = [];
            for ($emailKeyUnifyDepth = $this->fromEmailKeyUnifyDepth; $emailKeyUnifyDepth >= 0; $emailKeyUnifyDepth--) {
                $usersPerEmail = [];
                foreach ($this->getUsers() as $user) {
                    $mail = $user->mail();
                    if ($mail === '') {
                        continue;
                    }
                    try {
                        $keyFromEmail = ImportKeyUnifier::toUnifiedKey($mail, array_keys($usersPerEmail), $emailKeyUnifyDepth);
                        if (in_array($keyFromEmail, $conflictingKeys, true)) {
                            continue; // can not use this user as his mail is in conflict with another one
                        }
                        $usersPerEmail[$keyFromEmail] = $user;
                        // if unification was too aggressive and we had to lower level of depth / lossy compression, we have to store the lowest level for later picking-up values from cache
                    } catch (DuplicatedUnifiedKeyException $unifiedKeyException) {
                        if ($emailKeyUnifyDepth > 0) {
                            continue 2; // lower key depth
                        }
                        $conflictingKeys[] = $unifiedKeyException->getDuplicatedKey();
                        unset($usersPerEmail[$unifiedKeyException->getDuplicatedKey()]); // better to remove conflicting emails than throw them all
                    }
                }
                $this->usersPerEmail = $usersPerEmail;
                $this->fromEmailKeyUnifyDepth = min($this->fromEmailKeyUnifyDepth, $emailKeyUnifyDepth);
                break; // all emails converted to unified and unique keys
            }
        }
        return $this->usersPerEmail;
    }

    /**
     * @return \Uzivatel[]
     */
    private function getUsersPerName(): array {
        if ($this->usersPerName === null) {
            $this->usersPerName = [];
            $conflictingKeys = [];
            for ($nameKeyUnifyDepth = $this->fromNameKeyUnifyDepth; $nameKeyUnifyDepth >= 0; $nameKeyUnifyDepth--) {
                $usersPerUnifiedKey = [];
                foreach ($this->getUsers() as $user) {
                    $name = $user->jmeno();
                    $addUserResult = $this->addUserWithUnifiedKey(
                        $user,
                        $name,
                        $usersPerUnifiedKey,
                        $nameKeyUnifyDepth,
                        $conflictingKeys
                    );
                    if ($addUserResult === self::USELESS_FOR_KEY) {
                        continue;
                    }
                    if ($addUserResult === self::USELESS_FOR_DEPTH) {
                        continue 2; // lower key depth
                    }
                }
                $this->usersPerName = $usersPerUnifiedKey;
                $this->fromNameKeyUnifyDepth = min($this->fromNameKeyUnifyDepth, $nameKeyUnifyDepth);
                break; // all names converted to unified and unique keys
            }
        }
        return $this->usersPerName;
    }

    /**
     * @return \Uzivatel[]
     */
    private function getUsersPerNick(): array {
        if ($this->usersPerNick === null) {
            $this->usersPerNick = [];
            $conflictingKeys = [];
            for ($nickKeyUnifyDepth = $this->fromNickKeyUnifyDepth; $nickKeyUnifyDepth >= 0; $nickKeyUnifyDepth--) {
                $usersPerUnifiedKey = [];
                foreach ($this->getUsers() as $user) {
                    $nick = $user->nick();
                    $addUserResult = $this->addUserWithUnifiedKey(
                        $user,
                        $nick,
                        $usersPerUnifiedKey,
                        $nickKeyUnifyDepth,
                        $conflictingKeys
                    );
                    if ($addUserResult === self::USELESS_FOR_KEY) {
                        continue;
                    }
                    if ($addUserResult === self::USELESS_FOR_DEPTH) {
                        continue 2; // lower key depth
                    }
                }
                $this->usersPerNick = $usersPerUnifiedKey;
                $this->fromNickKeyUnifyDepth = min($this->fromNickKeyUnifyDepth, $nickKeyUnifyDepth);
                break; // all nicks converted to unified and unique keys
            }
        }
        return $this->usersPerNick;
    }

    /**
     * @return \Uzivatel[]
     */
    private function getUsersPerNameWithNick(): array {
        if ($this->usersPerNameWithNick === null) {
            $this->usersPerNameWithNick = [];
            $conflictingKeys = [];
            for ($nameWithNickKeyUnifyDepth = $this->fromNameWithNickKeyUnifyDepth; $nameWithNickKeyUnifyDepth >= 0; $nameWithNickKeyUnifyDepth--) {
                $usersPerUnifiedKey = [];
                foreach ($this->getUsers() as $user) {
                    $nameWithNick = $user->jmenoNick();
                    $addUserResult = $this->addUserWithUnifiedKey(
                        $user,
                        $nameWithNick,
                        $usersPerUnifiedKey,
                        $nameWithNickKeyUnifyDepth,
                        $conflictingKeys
                    );
                    if ($addUserResult === self::USELESS_FOR_KEY) {
                        continue;
                    }
                    if ($addUserResult === self::USELESS_FOR_DEPTH) {
                        continue 2; // lower key depth
                    }
                }
                $this->usersPerNameWithNick = $usersPerUnifiedKey;
                $this->fromNameWithNickKeyUnifyDepth = min($this->fromNameWithNickKeyUnifyDepth, $nameWithNickKeyUnifyDepth);
                break; // all nicks converted to unified and unique keys
            }
        }
        return $this->usersPerNameWithNick;
    }

    private const USER_ADDED_BY_KEY = 'user_added_by_key';
    private const USELESS_FOR_KEY = 'useless_for_key';
    private const USELESS_FOR_DEPTH = 'useless_for_key';

    private function addUserWithUnifiedKey(
        \Uzivatel $user,
        string $value,
        array &$usersPerUnifiedKey,
        int $keyUnifyDepth,
        array &$conflictingKeys
    ): string {
        if ($value === '') {
            return self::USELESS_FOR_KEY;
        }
        try {
            $unifiedKey = ImportKeyUnifier::toUnifiedKey($value, array_keys($usersPerUnifiedKey), $keyUnifyDepth);
            if (in_array($unifiedKey, $conflictingKeys, true)) {
                return self::USELESS_FOR_KEY; // can not use this unified key as is in conflict with another one
            }
            $usersPerUnifiedKey[$unifiedKey] = $user;
            // if unification was too aggressive and we had to lower level of depth / lossy compression, we have to store the lowest level for later picking-up values from cache
        } catch (DuplicatedUnifiedKeyException $unifiedKeyException) {
            if ($keyUnifyDepth > 0) {
                return self::USELESS_FOR_DEPTH; // lower key depth
            }
            // depth is already on zero, can not lower it
            $conflictingKeys[] = $unifiedKeyException->getDuplicatedKey();
            unset($usersPerUnifiedKey[$unifiedKeyException->getDuplicatedKey()]); // better to remove conflicting nicks than throw them all
        }
        return self::USER_ADDED_BY_KEY;
    }

    /**
     * @return \Uzivatel[]
     */
    private function getUsers(): array {
        if ($this->users === null) {
            $this->users = \Uzivatel::vsichni();
        }
        return $this->users;
    }
}
