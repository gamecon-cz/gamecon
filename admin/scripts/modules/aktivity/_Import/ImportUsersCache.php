<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Admin\Modules\Aktivity\Import\Exceptions\DuplicatedUnifiedKeyException;

class ImportUsersCache
{
  private $users;
  private $usersPerId;
  private $usersPerEmail;
  private $usersPerName;
  private $usersPerNick;
  private $fromNameKeyUnifyDepth = ImportKeyUnifier::UNIFY_UP_TO_LETTERS;
  private $fromNickKeyUnifyDepth = ImportKeyUnifier::UNIFY_UP_TO_LETTERS;


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
      foreach ($this->getUsers() as $user) {
        $keyFromEmail = ImportKeyUnifier::toUnifiedKey(
          $user->mail(),
          array_keys($this->usersPerEmail),
          ImportKeyUnifier::UNIFY_UP_TO_DIACRITIC
        );
        $this->usersPerEmail[$keyFromEmail] = $user;
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
      for ($nameKeyUnifyDepth = $this->fromNameKeyUnifyDepth; $nameKeyUnifyDepth >= 0; $nameKeyUnifyDepth--) {
        $usersPerNameKey = [];
        foreach ($this->getUsers() as $user) {
          $name = $user->jmeno();
          if ($name === '') {
            continue;
          }
          try {
            $keyFromCivilName = ImportKeyUnifier::toUnifiedKey($name, array_keys($this->usersPerName), $nameKeyUnifyDepth);
            $usersPerNameKey[$keyFromCivilName] = $user;
            // if unification was too aggressive and we had to lower level of depth / lossy compression, we have to store the lowest level for later picking-up values from cache
          } catch (DuplicatedUnifiedKeyException $unifiedKeyException) {
            if ($nameKeyUnifyDepth > 0) {
              continue 2; // lower key depth
            }
            throw $unifiedKeyException;
          }
        }
        $this->usersPerName = $usersPerNameKey;
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
      for ($nickKeyUnifyDepth = $this->fromNickKeyUnifyDepth; $nickKeyUnifyDepth >= 0; $nickKeyUnifyDepth--) {
        $usersPerNickKey = [];
        foreach ($this->getUsers() as $user) {
          $nick = $user->nick();
          if ($nick === '') {
            continue;
          }
          try {
            $keyFromNick = ImportKeyUnifier::toUnifiedKey($nick, array_keys($this->usersPerNick), $nickKeyUnifyDepth);
            $usersPerNickKey[$keyFromNick] = $user;
            // if unification was too aggressive and we had to lower level of depth / lossy compression, we have to store the lowest level for later picking-up values from cache
          } catch (DuplicatedUnifiedKeyException $unifiedKeyException) {
            if ($nickKeyUnifyDepth > 0) {
              continue 2; // lower key depth
            }
            throw $unifiedKeyException;
          }
        }
        $this->usersPerNick = $usersPerNickKey;
        $this->fromNickKeyUnifyDepth = min($this->fromNickKeyUnifyDepth, $nickKeyUnifyDepth);
        break; // all nicks converted to unified and unique keys
      }
    }
    return $this->usersPerNick;
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
