# CHANGELOG

## [v2.7.8](https://github.com/zenstruck/foundry/releases/tag/v2.7.8)

November 5th, 2025 - [v2.7.7...v2.7.8](https://github.com/zenstruck/foundry/compare/v2.7.7...v2.7.8)

* 728c8f8 minor: Add default value to Factory::attributes (#1026) by @VincentLanglet
* 0fe1017 tests: ensure Doctrine lifecycle works (#1020) by @nikophil
* 1247b0b chore: remove paratest from dev dependencies (#1023) by @nikophil
* 11355a9 chore: add concurrency for all workflows (#1019) by @nikophil
* fed6e4a chore: fix rector with bamarni (#1018) by @nikophil
* 545cf18 chore: split CIs and add concurrency (#1017) by @nikophil
* 17796f2 chore: add bc-check to CI (#1016) by @nikophil
* b8ced9b chore: disable sync template for cs config (#1015) by @nikophil
* b802463 chore: some housekeeping (#1014) by @nikophil

## [v2.7.7](https://github.com/zenstruck/foundry/releases/tag/v2.7.7)

October 23rd, 2025 - [v2.7.6...v2.7.7](https://github.com/zenstruck/foundry/compare/v2.7.6...v2.7.7)

* 7766a85 fix: only use PersistedObjectsTracker when auto-refresh is enabled (#1013) by @nikophil

## [v2.7.6](https://github.com/zenstruck/foundry/releases/tag/v2.7.6)

October 20th, 2025 - [v2.7.5...v2.7.6](https://github.com/zenstruck/foundry/compare/v2.7.5...v2.7.6)

* 95d2a96 fix: autorefresh should work after kernel shutdown (#1011) by @nikophil
* 0ea8430 tests: ensure OneToMany relationships are refreshed (#1010) by @nikophil
* 3c6faff fix: RepositoryAssertion::exist() $criteria should allow mixed (#1007) by @nikophil
* 81cc97d minor: accept as story any child of Story (#1006) by @alsciende
* e99f3b0 chore: run rector CI with PHPUnit 12 (#1002) by @nikophil

## [v2.7.5](https://github.com/zenstruck/foundry/releases/tag/v2.7.5)

October 10th, 2025 - [v2.7.4...v2.7.5](https://github.com/zenstruck/foundry/compare/v2.7.4...v2.7.5)

* 81eacf5 docs: add a note about using `make:factory --test` (#1000) by @ttskch
* 660942d [Rector] Add rector to require-dev and use single autoload vendor for run PHPUnit (#1001) by @samsonasik
* f06d58d fix: using `refresh_all()` with `flush_after()` (#999) by @HypeMC

## [v2.7.4](https://github.com/zenstruck/foundry/releases/tag/v2.7.4)

October 8th, 2025 - [v2.7.3...v2.7.4](https://github.com/zenstruck/foundry/compare/v2.7.3...v2.7.4)

* 9489e83 fix: 🐛 use isser instead of constructor to apply autorefresh setting to Factory (#998) by @ttskch

## [v2.7.3](https://github.com/zenstruck/foundry/releases/tag/v2.7.3)

October 5th, 2025 - [v2.7.2...v2.7.3](https://github.com/zenstruck/foundry/compare/v2.7.2...v2.7.3)

* 45214f7 fix: revert adding PersistManager::findBy() (#996) by @nikophil
* b36b9b3 chore: upgrade PHPStan (#997) by @nikophil
* 55f2689 fix: edge case with Doctrine Middleware & early kernel boot (#993) by @HypeMC
* 9fa21b3 fix(repository): use IN() when an array is passed (#995) by @nikophil
* cad1466 fix: handle readonly when refreshing from repository decorator (#989) by @nikophil
* b4b2ffe fix: Enhanced random method with additional safety check. (#991) by @sofwar

## [v2.7.2](https://github.com/zenstruck/foundry/releases/tag/v2.7.2)

September 25th, 2025 - [v2.7.1...v2.7.2](https://github.com/zenstruck/foundry/compare/v2.7.1...v2.7.2)

* 97b60b6 fix: applyStateMethod should not be internal (#988) by @nikophil

## [v2.7.1](https://github.com/zenstruck/foundry/releases/tag/v2.7.1)

September 24th, 2025 - [v2.7.0...v2.7.1](https://github.com/zenstruck/foundry/compare/v2.7.0...v2.7.1)

* 90866d2 fix(autorefresh): return fresh data from RepositoryDecorator methods (#983) by @nikophil
* 485746e fix(autorefresh): don't use clone to get the id values (#980) by @nikophil
* 21b659b chore: fix issue template (#982) by @nikophil
* 5d02ac6 Fix link to UPGRADE-2.7.md file (#978) by @Kocal

## [v2.7.0](https://github.com/zenstruck/foundry/releases/tag/v2.7.0)

September 17th, 2025 - [v2.6.3...v2.7.0](https://github.com/zenstruck/foundry/compare/v2.6.3...v2.7.0)

* cd1b31a docs: add "Troubleshooting" section in upgrade guide (#943) by @nikophil
* a83c249 feat: enable auto-refresh at factory level (#970) by @nikophil
* 32e9868 fix: few fixes after #972 (#943) by @nikophil
* 47b0d79 feat: use ghost objects for auto refresh mechanism (#967) (#943) by @nikophil
* 3a131ef minor: improve deprecation message (#943) by @nikophil
* 30df79d feat: auto-refresh objects from RepositoryDecorator (#943) by @nikophil
* f675c37 minor: use ProxyGenerator::unwrap() instead of unproxy() and prevent deprec (#943) by @nikophil
* 9d04094 minor: remove PersistedObjectsTracker::reset() call in tear down (#943) by @nikophil
* 3e23fda refactor(maker): deprecate --with-phpdocs for PHP >=8.4 (#952) (#943) by @nikophil
* 61cabac docs: create upgrade guide to 2.7 and document auto-refresh (#951) (#943) by @nikophil
* fe6374b feat: auto refresh with lazy object php84 enabled by config (#950) (#943) by @nikophil
* 9717676 feat: Rector rules to help migrating away from proxy (#941) (#943) by @nikophil
* 35e8da6 feat: use native proxies for object creation in data providers (#943) by @nikophil
* 02f85f2 feat: create proxy system with PHP 8.4 lazy proxies (#943) by @nikophil

## [v2.6.3](https://github.com/zenstruck/foundry/releases/tag/v2.6.3)

August 28th, 2025 - [v2.6.2...v2.6.3](https://github.com/zenstruck/foundry/compare/v2.6.2...v2.6.3)

* 67a7731 fix: misc fixes when creating objects in data provider (#972) by @nikophil
* 5e068c4 fix: ignore PHPUnit warnings when dataprovider returns more data than test method accepts (#958) by @nikophil
* 0a65872 Fix proxying of classes that have tenative return types (#962) by @BackEndTea
* f9e95cb fix: doctrine deprecation (#961) by @nikophil
* c8256e3 Add support for Symfony 8 (#960) by @Kocal

## [v2.6.2](https://github.com/zenstruck/foundry/releases/tag/v2.6.2)

August 5th, 2025 - [v2.6.1...v2.6.2](https://github.com/zenstruck/foundry/compare/v2.6.1...v2.6.2)

* 6f4e920 fix(proxy): add autorefresh call for union and intersection return types (#959) by @BackEndTea
* 58fd89a feat: introduce method FactoryCollection::applyStateMethod() (#956) by @nikophil

## [v2.6.1](https://github.com/zenstruck/foundry/releases/tag/v2.6.1)

July 29th, 2025 - [v2.6.0...v2.6.1](https://github.com/zenstruck/foundry/compare/v2.6.0...v2.6.1)

* 932c63a feat: rename `foundry:load-stories` to `foundry:load-fixtures` (#954) by @kbond
* 1d31275 Update index.rst (#945) by @treztreiz
* e5e5162 fix: doctrine deprecation (#949) by @nikophil
* dc54221 Update index.rst (#946) by @treztreiz
* 5ae21ec docs: fix forceSet() to _set() in example (#948) by @mariecharles, Marie CHARLES
* 56161cc feat: add `randomRangeOrCreate()` method (#932) by @elliotbruneel, Elliot Bruneel
* 2d28e67 test: ensure no deprecation when Factories used in parent class (#922) by @nikophil
* b25eb60 minor: fix running `phpunit` w/o arguments (#933) by @kbond
* f9b8132 minor: add UID types to factory maker (#936) by @HypeMC
* 030f7aa chore: temporarily disable cascade relationship combinations (#938) by @nikophil

## [v2.6.0](https://github.com/zenstruck/foundry/releases/tag/v2.6.0)

June 5th, 2025 - [v2.5.4...v2.6.0](https://github.com/zenstruck/foundry/compare/v2.5.4...v2.6.0)

* 7e434ff feat: minor improvements to foundry:load-stories (#930) by @nikophil
* bd50a86 merge 2.5.x into 2.x (#931) by @nikophil
* 159d700 doc: adjust flow (#923) by @kbond
* ca95279 feat: Introduce `#[AsFixture]` attribute and `foundry:load-fixture` command (#903) by @nikophil

## [v2.5.5](https://github.com/zenstruck/foundry/releases/tag/v2.5.5)

June 4th, 2025 - [v2.5.4...v2.5.5](https://github.com/zenstruck/foundry/compare/v2.5.4...v2.5.5)

* 8238e0f fix: remove useless Configuration::boted() check (#929) by @nikophil

## [v2.5.4](https://github.com/zenstruck/foundry/releases/tag/v2.5.4)

May 31st, 2025 - [v2.5.3...v2.5.4](https://github.com/zenstruck/foundry/compare/v2.5.3...v2.5.4)

* 8e202b4 fix: TypeError `FactoryCollection::create()` when calling many with 0 (#925) by @jdecool

## [v2.5.3](https://github.com/zenstruck/foundry/releases/tag/v2.5.3)

May 30th, 2025 - [v2.5.2...v2.5.3](https://github.com/zenstruck/foundry/compare/v2.5.2...v2.5.3)

* 01c5ce3 fix: should not use flush_after() in FactoryCollection::create() (#908) by @nikophil
* 7545b2f docs: Fix LazyValue namespace (#919) by @odolbeau
* ccc309b docs: fix quote (#918) by @nikophil
* f0ae498 docs: Fix `save()` -> `_save()` in documentation (#917) by @smnandre

## [v2.5.2](https://github.com/zenstruck/foundry/releases/tag/v2.5.2)

May 26th, 2025 - [v2.5.1...v2.5.2](https://github.com/zenstruck/foundry/compare/v2.5.1...v2.5.2)

* 40ce8a2 fix: reuse should work with all kind of relationships (#915) by @nikophil
* f6c81a0 fix: can use reuse with inheritance (#914) by @nikophil

## [v2.5.1](https://github.com/zenstruck/foundry/releases/tag/v2.5.1)

May 22nd, 2025 - [v2.5.0...v2.5.1](https://github.com/zenstruck/foundry/compare/v2.5.0...v2.5.1)

* fe12d09 fix: add missing flush_once feature flag (#912) by @phasdev
* f991999 docs: Fix data providers phpunit link (#906) by @alexander-schranz
* 19ddd55 docs: in memory behavior needs PhpUnit extension (#905) by @nikophil
* 6a0b4ac docs: improve docs for in-memory repositories (#904) by @nikophil

## [v2.5.0](https://github.com/zenstruck/foundry/releases/tag/v2.5.0)

May 13th, 2025 - [v2.4.3...v2.5.0](https://github.com/zenstruck/foundry/compare/v2.4.3...v2.5.0)

* cdbacdd minor: ignore deprecations related to ProxyHelper::generateLazyProxy() (#901) by @nikophil
* a54d97e minor: ignore deprecations related to ProxyHelper::generateLazyProxy() (#901) by @nikophil
* ae662a3 minor: make "in-memory" classes experimental (#895) (#901) by @nikophil
* 87acf7a feat: add generic doctrine-like repository for in-memory (#887) (#901) by @nikophil
* 7b6f70c feat: enable flush once with config (#885) (#901) by @nikophil
* 30270ec feat: introduce "in-memory" behavior (#590) (#901) by @nikophil
* 0b09c20 chore: decouple from framework bundle (#882) (#901) by @nikophil
* df4d355 chore: misc DX and testsuite improvements (#881) (#901) by @nikophil
* a19ce4c tests: add `ZenstruckFoundryBundleTest` (#878) (#901) by @silasjoisten, @nikophil
* 5b027c0 feat: flush once (#873) (#901) by @nikophil

## [v2.4.3](https://github.com/zenstruck/foundry/releases/tag/v2.4.3)

May 5th, 2025 - [v2.4.2...v2.4.3](https://github.com/zenstruck/foundry/compare/v2.4.2...v2.4.3)

* 2b31429 chore: test with SF7.3 (#891) by @nikophil
* 50350cb minor: allow 10% gap in benchmark workflow (#880) by @nikophil

## [v2.4.2](https://github.com/zenstruck/foundry/releases/tag/v2.4.2)

April 17th, 2025 - [v2.4.1...v2.4.2](https://github.com/zenstruck/foundry/compare/v2.4.1...v2.4.2)

* 25e9125 fix: Prevent random value collisions when kernel is rebooted (#879) by @HypeMC

## [v2.4.1](https://github.com/zenstruck/foundry/releases/tag/v2.4.1)

April 15th, 2025 - [v2.4.0...v2.4.1](https://github.com/zenstruck/foundry/compare/v2.4.0...v2.4.1)

* 6b4fea8 fix(faker): missing parameter when using custom `faker` service (#877) by @silasjoisten

## [v2.4.0](https://github.com/zenstruck/foundry/releases/tag/v2.4.0)

April 14th, 2025 - [v2.3.2...v2.4.0](https://github.com/zenstruck/foundry/compare/v2.3.2...v2.4.0)

* 659a7bc minor: use `mt_rand` instead of `random_int` (#869) by @kbond
* 12b4419 perf: revert validation / `#[AsFoundryHook]` / global event system (#871) by @nikophil
* 348b28d docs: fix default_namespace (#872) by @ebedy
* 92d9f28 chore(phpbench): actually run phpbench with a baseline (#868) by @nikophil
* 1a829e5 feat: optimize performance of repository::random() (#867) by @mdeboer
* 5ccbe51 feat: add support for benchmarks using phpbench (#866) by @mdeboer, @nikophil
* 2df354c fix: performance problem with reuse (#865) by @nikophil
* 0747e04 docs: document Faker reproducibility (#860) by @nikophil
* f8cc3a0 fix: handle empty constructors (#859) by @nikophil
* cb63756 chore: merge 2.3.x (#858) by @nikophil, @mdeboer, @Chris53897
* b1e7aec feat(maker): allow no hints (#857) by @nikophil
* 59d617c fixes typo (#850) by @mvhirsch
* 5cc8575 feat: introduce "reuse()" method (#804) by @nikophil, @kbond
* 21f32b8 docs: fix wrong class name (#846) by @nikophil
* 48d9249 docs: minor fixes (#837) by @nikophil
* bdda45c doc: fixes linking to object-proxy (#825) by @mvhirsch
* 719710a test: ensure Proxy::_real() always return same object (#809) by @nikophil
* d15de0e feat: introduce `distribute()` method (#826) by @nikophil
* 5647b5c fix: prevent infinite loop when ->create() is called in after persist callback (#832) by @nikophil
* c0361e6 feat: validate objects (#801) by @nikophil
* 6e1d726 fix: fix failing faker test due to csfix (#829) by @nikophil
* 7b33216 minor: deprecate auto-persist (#818) by @nikophil
* eb6e983 feat(faker): Improve reproducibility with faker (#807) by @nikophil
* ae96d19 chore: use PHPUnit 12 (#810) by @nikophil
* 413bb10 chore: upgrade phpstan (#828) by @nikophil
* fbf0981 fix: actually disable persistence cascade (#817) by @nikophil
* 2426f3e fix: trigger after persist callbacks for entities scheduled for insert (#822) by @nikophil
* dea6246 fix(doc): update yml config file for reset keys (#819) by @asalisaf
* da1e9db docs: Make sure we add links on separate lines(#823) by @Nyholm
* ad8d72c fix: can index one to many relationships based on "indexBy" (#815) by @nikophil
* 1c3f73a feat: introduce  attribute (#802) by @nikophil
* f76cba2 fix: fix deprecation message for Factories trait (#806) by @nikophil
* 207562f fix: remove APP_ENV from .env (#803) by @nikophil
* 34101a7 feat: dispatch events (#790) by @nikophil
* 9032c38 feat: skip readonly properties on entities when generating factories (#798) by @KDederichs, @nikophil

## [v2.3.10](https://github.com/zenstruck/foundry/releases/tag/v2.3.10)

March 31st, 2025 - [v2.3.9...v2.3.10](https://github.com/zenstruck/foundry/compare/v2.3.9...v2.3.10)

* e5c6973 fix: handle "inverse one to one" without "placeholder" solution (#855) by @nikophil

## [v2.3.6](https://github.com/zenstruck/foundry/releases/tag/v2.3.6)

February 25th, 2025 - [v2.3.5...v2.3.6](https://github.com/zenstruck/foundry/compare/v2.3.5...v2.3.6)

* 300645b fix: can call ->create() in after persist callback (#833) by @nikophil

## [v2.3.5](https://github.com/zenstruck/foundry/releases/tag/v2.3.5)

February 24th, 2025 - [v2.3.4...v2.3.5](https://github.com/zenstruck/foundry/compare/v2.3.4...v2.3.5)

* fbf0981 fix: actually disable persistence cascade (#817) by @nikophil
* 2426f3e fix: trigger after persist callbacks for entities scheduled for insert (#822) by @nikophil

## [v2.3.4](https://github.com/zenstruck/foundry/releases/tag/v2.3.4)

February 14th, 2025 - [v2.3.3...v2.3.4](https://github.com/zenstruck/foundry/compare/v2.3.3...v2.3.4)

* ad8d72c fix: can index one to many relationships based on "indexBy" (#815) by @nikophil

## [v2.3.2](https://github.com/zenstruck/foundry/releases/tag/v2.3.2)

February 1st, 2025 - [v2.3.1...v2.3.2](https://github.com/zenstruck/foundry/compare/v2.3.1...v2.3.2)

* 46464cc chore(ci): misc improvments in CI permutations (#797) by @nikophil
* 86c5aab test: assert updates are implicitly persisted (#781) by @nikophil
* 54c7424 feat: deprecate when Factories trait is not used in a KernelTestCase (#766) by @nikophil
* 9937b11 chore: add issue template (#795) by @nikophil
* 884113f fix: simplify reset database extension (#779) by @nikophil
* bd50f41 fix: add unpersisted object to relation (#780) by @nikophil
* 17388bc tests: transform "migrate" testsuite into "reset database" testsuite (#763) by @nikophil
* e45913e fix: propagate "schedule for insert" to factory collection (#775) by @nikophil
* d9262cc fix: fix .gitattributes and `#[RequiresPhpUnit]` versions (#792) by @nikophil
* 57c42bc tests: fix a test after a bug was resolved in doctrine migrations (#791) by @nikophil
* 200cfdd [Doc] Fix misc issues (#789) by @javiereguiluz
* 553807b minor: add platform config to mysql docker container (#788) by @kbond
* 316d3c7 doc: fix typo (#782) by @norival
* 0d66c02 minor: use refresh for detached entities (#778) by @nikophil
* 29b48a1 test: add orphan removal premutation (#777) by @nikophil
* c00b3f1 fix: isPersisted must work when id is known in advance (#774) by @nikophil
* f303f3f fix: remove _refresh call from create object process (#773) by @nikophil
* 65cedbf fix: use a "placeholder" for inversed one-to-one (#755) by @nikophil
* 5f99506 minor: introduce PerssitenceManager::isPersisted() (#754) by @nikophil
* 9948d6a fix(ci): change PHP version used by PHP CS-Fixer  (#768) by @nikophil
* cf3cc8b docs: Minor syntax fix (#767) by @javiereguiluz
* e8f9a92 docs: clarify default attributes and fixed some syntax issues (#765) by @nikophil, @javiereguiluz
* 1db5ced tests: validate PSR-4 in CI (#762) by @nikophil
* cafc693 [Docs fix] Just spelling in docs (#761) by @GrinWay
* d192c4a [Docs fix] Proxy::_save() instead of Proxy::save() (#760) by @GrinWay
* ff7210a [Docs fix] Factory::_real() instead Factory::object() (#759) by @GrinWay
* d1240b1 fix: RequiresPhpunit should use semver constraint by @nikophil
* fd2e38c chore: upgrade to phpstan 2 (#748) by @nikophil
* 23b4ec4 tests: automatically create cascade persist permutations (#666) by @nikophil
* f4ba5d8 tests: add CI permutation with windows (#747) by @nikophil
* c17ef91 fix: define FactoryCollection type more precisely (#744) by @nikophil
* 98f018c feat: schedule objects for insert right after instantiation (#742) by @nikophil
* 2dcad10 feat: provide current factory to hook (#738) by @nikophil
* ea89504 fix: pass to `afterPersist` hook the attributes from `beforeInstantiate` (#745) by @nikophil, @kbond

## [v2.3.1](https://github.com/zenstruck/foundry/releases/tag/v2.3.1)

December 12th, 2024 - [v2.3.0...v2.3.1](https://github.com/zenstruck/foundry/compare/v2.3.0...v2.3.1)

* 138801d chore: remove error handler hack (#729) by @nikophil
* cd9dbf5 refactor: extract reset:migration tests in another testsuite (#692) by @nikophil

## [v2.3.0](https://github.com/zenstruck/foundry/releases/tag/v2.3.0)

December 11th, 2024 - [v2.2.2...v2.3.0](https://github.com/zenstruck/foundry/compare/v2.2.2...v2.3.0)

* b16b227 Update index.rst (#740) by @OskarStark, @nikophil
* 854220f Figo highlighting and use CPP (#740) by @OskarStark
* dfe6bab tests: add paratest permutation (#736) by @nikophil
* af64c35 fix: detect if relation is oneToOne (#732) by @nikophil
* 59867c3 minor: change versions requirements (#737) by @nikophil
* c8f5046 Fix PHPUnit constraint requirement in FoundryExtension (#735) by @HypeMC
* 4cb7447 Typo in Immutable section (#731) by @franckranaivo
* 403d9e9 fix: Fix the parameter name of the first and last methods (#730) by @marien-probesys
* 0867ad6 feat: add `#[WithStory]` attribute (#728) by @nikophil
* c5d0bdd fix: can create inversed one to one with non nullable (#726) by @nikophil
* 0e7ac6f docs: Fix Story phpdocs (#727) by @simondaigre, @nikophil
* f48ffd1 fix: can create inversed one to one (#659) by @nikophil
* 6d08784 fix: bug with one to many (#722) by @nikophil
* efadea8 docs:fix code blocks not showing up (#723) by @AndreasA
* edf287e minor: Add templated types to flush_after (#719) by @BackEndTea

## [v2.2.2](https://github.com/zenstruck/foundry/releases/tag/v2.2.2)

November 5th, 2024 - [v2.2.1...v2.2.2](https://github.com/zenstruck/foundry/compare/v2.2.1...v2.2.2)

* 3282f24 Remove @internal from db resetter interfaces (#715) by @HypeMC
* 870cb42 docs: fix missing comma in upgrade doc (#718) by @justpilot

## [v2.2.1](https://github.com/zenstruck/foundry/releases/tag/v2.2.1)

October 31st, 2024 - [v2.2.0...v2.2.1](https://github.com/zenstruck/foundry/compare/v2.2.0...v2.2.1)

* 496a7a8 fix: Change `RepositoryDecorator::inner()` visibility to public (#714) by @marienfressinaud
* dfeb247 chore: test Foundry on PHP 8.4 & sf 7.2 (#709) by @nikophil
* 2b12ef0 chore: simplify CI matrix (#708) by @nikophil

## [v2.2.0](https://github.com/zenstruck/foundry/releases/tag/v2.2.0)

October 24th, 2024 - [v2.1.0...v2.2.0](https://github.com/zenstruck/foundry/compare/v2.1.0...v2.2.0)

* a549c10 docs: using factories in data providers (#707) by @nikophil
* 470d927 docs: how to extend database reset mechanism (#706) by @nikophil
* 2014ed9 feature: allow to use `Factory::create()` and factory service in data providers (#648) by @nikophil
* df568da refactor: make "database reset" mechanism extendable (#690) by @nikophil
* 4fb0b25 docs: add missing docs (#703) by @nikophil
* fa1d527 minor: misc fixes for sca (#705) by @nikophil
* 0d570cc refactor: fix proxy system and introduce psalm extension (#704) by @nikophil

## [v2.1.0](https://github.com/zenstruck/foundry/releases/tag/v2.1.0)

October 3rd, 2024 - [v2.0.9...v2.1.0](https://github.com/zenstruck/foundry/compare/v2.0.9...v2.1.0)

* 0f72ea5 fix: allow non object state in stories (#699) by @Brewal
* 6482357 feat: allow to configure migrations configuration files (#686) by @MatTheCat

## [v2.0.9](https://github.com/zenstruck/foundry/releases/tag/v2.0.9)

September 2nd, 2024 - [v2.0.8...v2.0.9](https://github.com/zenstruck/foundry/compare/v2.0.8...v2.0.9)

* b0a5d3d Fix Psalm TooManyTemplateParams (#693) by @ddeboer

## [v2.0.8](https://github.com/zenstruck/foundry/releases/tag/v2.0.8)

August 29th, 2024 - [v2.0.7...v2.0.8](https://github.com/zenstruck/foundry/compare/v2.0.7...v2.0.8)

* 3eebbf9 Have `flush_after()` return the callback's return (#691) by @HypeMC
* 33d5870 doc: Fix range call instead of many (#688) by @ternel
* 33595b9 chore: add a wrapper for PHPUnit binary (#683) by @nikophil
* 8bf8c4c docs: Fix CategoryStory codeblock (#681) by @smnandre
* f89d43e doc: Minor fixes (#679) by @smnandre
* 65c1cc2 fix: add phpdoc to improve proxy factories autocompletion (#675) by @nikophil

## [v2.0.7](https://github.com/zenstruck/foundry/releases/tag/v2.0.7)

July 12th, 2024 - [v2.0.6...v2.0.7](https://github.com/zenstruck/foundry/compare/v2.0.6...v2.0.7)

* 5c44991 fix: handle proxies when refreshing entity in Proxy::getState() (#672) by @nikophil
* 49f5e1d Fix faker php urls (#671) by @BackEndTea
* 7719b0d chore(CI): Enable documentation linter (#657) by @cezarpopa

## [v2.0.6](https://github.com/zenstruck/foundry/releases/tag/v2.0.6)

July 4th, 2024 - [v2.0.5...v2.0.6](https://github.com/zenstruck/foundry/compare/v2.0.5...v2.0.6)

* 52ca7b7 fix: only restore error handler for PHPUnit 10 or superior (#668) by @nikophil
* b5090aa docs: Fix broken link to Without Persisting (#660) by @simoheinonen
* 35b0404 feat: re-add Proxy assertions (#663) by @nikophil

## [v2.0.5](https://github.com/zenstruck/foundry/releases/tag/v2.0.5)

July 3rd, 2024 - [v2.0.4...v2.0.5](https://github.com/zenstruck/foundry/compare/v2.0.4...v2.0.5)

* 6105a36 fix: make proxy work with last symfony/var-exporter version (#664) by @nikophil
* e8623a3 [DOC] Fix Upgrade Guide URL Rendering (#654) by @cezarpopa
* f7f133a fix: create ArrayCollection if needed (#645) by @nikophil
* 779bee4 fix: after_flush() can use objects created in global state (#653) by @nikophil
* 72e48bf tests(ci): add test permutation for PHPUnit >= 10 (#647) by @nikophil
* 1edf948 docs: fix incoherence (#652) by @nikophil
* 1c66e39 minor: improve repository assertion messages (#651) by @nikophil

## [v2.0.4](https://github.com/zenstruck/foundry/releases/tag/v2.0.4)

June 20th, 2024 - [v2.0.3...v2.0.4](https://github.com/zenstruck/foundry/compare/v2.0.3...v2.0.4)

* 0989c5d fix: don't try to proxify objects that are not persistable (#646) by @nikophil
* 50ae3dc fix: handle contravariance problem when proxifying class with unserialize method (#644) by @nikophil

## [v2.0.3](https://github.com/zenstruck/foundry/releases/tag/v2.0.3)

June 19th, 2024 - [v2.0.2...v2.0.3](https://github.com/zenstruck/foundry/compare/v2.0.2...v2.0.3)

* 6f0835f fix(2.x): only reset error handler in before class hook (#643) by @nikophil
* 3c31193 test: add test with multiple ORM schemas (#629) by @vincentchalamon
* 303211a fix: unproxy args in proxy objects (#635) by @nikophil

## [v2.0.2](https://github.com/zenstruck/foundry/releases/tag/v2.0.2)

June 14th, 2024 - [v2.0.1...v2.0.2](https://github.com/zenstruck/foundry/compare/v2.0.1...v2.0.2)

* b76c294 fix(2.x): support Symfony 7.1 (#622) by @nikophil
* 9cd97b7 docs: Improve DX for tests (#636) by @matthieumota
* 17b0228 fix(2.x): add back second parameter for after persist callbacks (#631) by @nikophil
* 0c7b3af docs: Fix typo in the upgrade guide (#624) by @stof
* 933ebbd docs: upgrade readme with a link to upgrade guide (#620) by @nikophil

## [v2.0.1](https://github.com/zenstruck/foundry/releases/tag/v2.0.1)

June 10th, 2024 - [v2.0.0...v2.0.1](https://github.com/zenstruck/foundry/compare/v2.0.0...v2.0.1)

* 5f0ce76 Fix `Instantiator::allowExtra` example (#616) by @norkunas
* c2cbcbc fix(orm): reset database instead of dropping the schema when using migrations (#615) by @vincentchalamon

## [v2.0.0](https://github.com/zenstruck/foundry/releases/tag/v2.0.0)

June 7th, 2024 - _[Initial Release](https://github.com/zenstruck/foundry/commits/v2.0.0)_
