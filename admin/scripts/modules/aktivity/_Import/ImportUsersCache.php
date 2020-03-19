<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Admin\Modules\Aktivity\Import\Exceptions\DuplicatedUnifiedKeyException;

class ImportUsersCache
{
  private $storage = ['id' => [], 'keyFromEmail' => [], 'keyFromName' => [], 'keyFromNick' => [], 'users' => []];
  private $fromNameKeyUnifyDepth = ImportKeyUnifier::UNIFY_UP_TO_LETTERS;
  private $fromNickKeyUnifyDepth = ImportKeyUnifier::UNIFY_UP_TO_LETTERS;


  public function getUserById(int $id): ?\Uzivatel {
    return $this->getStorage()['id'][$id] ?? null;
  }

  public function getUserByEmail(string $email): ?\Uzivatel {
    if (strpos($email, '@') === false) {
      return null;
    }
    $key = ImportKeyUnifier::toUnifiedKey($email, [], ImportKeyUnifier::UNIFY_UP_TO_DIACRITIC);
    return $this->getStorage()['keyFromEmail'][$key] ?? null;
  }

  public function getUserByName(string $name): ?\Uzivatel {
    $key = ImportKeyUnifier::toUnifiedKey($name, [], $this->fromNameKeyUnifyDepth);
    return $this->getStorage()['keyFromName'][$key] ?? null;
  }

  public function getUserByNick(string $nick): ?\Uzivatel {
    $key = ImportKeyUnifier::toUnifiedKey($nick, [], $this->fromNickKeyUnifyDepth);
    return $this->getStorage()['keyFromNick'][$key] ?? null;
  }

  private function getStorage(): array {
    if (!$this->storage) {
      $this->storage = ['id' => [], 'keyFromEmail' => [], 'keyFromName' => [], 'keyFromNick' => [], 'users' => []];

      $users = \Uzivatel::vsichni();

      foreach ($users as $user) {
        $this->storage['id'][$user->id()] = $user;
        $keyFromEmail = ImportKeyUnifier::toUnifiedKey(
          $user->mail(),
          array_keys($this->storage['keyFromEmail']),
          ImportKeyUnifier::UNIFY_UP_TO_DIACRITIC
        );
        $this->storage['keyFromEmail'][$keyFromEmail] = $user;
      }

      for ($nameKeyUnifyDepth = $this->fromNameKeyUnifyDepth; $nameKeyUnifyDepth >= 0; $nameKeyUnifyDepth--) {
        $keyFromNameCache = [];
        foreach ($users as $user) {
          $name = $user->jmeno();
          if ($name === '') {
            continue;
          }
          try {
            $keyFromCivilName = ImportKeyUnifier::toUnifiedKey($name, array_keys($this->storage['keyFromName']), $nameKeyUnifyDepth);
            $keyFromNameCache[$keyFromCivilName] = $user;
            // if unification was too aggressive and we had to lower level of depth / lossy compression, we have to store the lowest level for later picking-up values from cache
          } catch (DuplicatedUnifiedKeyException $unifiedKeyException) {
            continue 2; // lower key depth
          }
        }
        $this->storage['keyFromName'] = $keyFromNameCache;
        $this->fromNameKeyUnifyDepth = min($this->fromNameKeyUnifyDepth, $nameKeyUnifyDepth);
        break; // all names converted to unified and unique keys
      }

      for ($nickKeyUnifyDepth = $this->fromNickKeyUnifyDepth; $nickKeyUnifyDepth >= 0; $nickKeyUnifyDepth--) {
        $keyFromNickCache = [];
        foreach ($users as $user) {
          $nick = $user->nick();
          if ($nick === '') {
            continue;
          }
          try {
            $keyFromNick = ImportKeyUnifier::toUnifiedKey($nick, array_keys($this->storage['keyFromNick']), $nickKeyUnifyDepth);
            $keyFromNickCache[$keyFromNick] = $user;
            // if unification was too aggressive and we had to lower level of depth / lossy compression, we have to store the lowest level for later picking-up values from cache
          } catch (DuplicatedUnifiedKeyException $unifiedKeyException) {
            continue 2; // lower key depth
          }
        }
        $this->storage['keyFromNick'] = $keyFromNickCache;
        $this->fromNickKeyUnifyDepth = min($this->fromNickKeyUnifyDepth, $nickKeyUnifyDepth);
        break; // all nicks converted to unified and unique keys
      }
    }
    return $this->storage;
  }
}
