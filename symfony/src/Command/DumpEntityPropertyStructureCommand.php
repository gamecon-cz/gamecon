<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'app:dump-entity-property-structure',
    description: 'Create PHP classes with constants representing entity property names',
)]
class DumpEntityPropertyStructureCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Filesystem $filesystem,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('output-dir', 'o', InputOption::VALUE_OPTIONAL, 'Output directory for generated structure classes', $this->projectDir . '/src/Structure/Entity')
            ->addOption('namespace', 'ns', InputOption::VALUE_OPTIONAL, 'Namespace for generated classes', 'App\\Structure\\Entity')
            ->addOption('suffix', 's', InputOption::VALUE_OPTIONAL, 'Suffix for generated class names', 'EntityStructure')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Check if generated files are up-to-date instead of writing them')
            ->setHelp('This command generates PHP classes with constants for entity property names.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $outputDir = $input->getOption('output-dir');
        $namespace = $input->getOption('namespace');
        $suffix = $input->getOption('suffix');
        $checkMode = $input->getOption('check');

        if ($checkMode) {
            return $this->executeCheck($io, $outputDir, $namespace, $suffix);
        }

        return $this->executeWrite($io, $outputDir, $namespace, $suffix);
    }

    private function executeWrite(SymfonyStyle $io, string $outputDir, string $namespace, string $suffix): int
    {
        // Create output directory if it doesn't exist
        if (! $this->filesystem->exists($outputDir)) {
            $this->filesystem->mkdir($outputDir);
            $io->info("Created output directory: {$outputDir}");
        }

        $metadataFactory = $this->entityManager->getMetadataFactory();
        $allMetadata = $metadataFactory->getAllMetadata();

        $newFiles = [];
        $updatedFiles = [];
        $unchangedCount = 0;
        $errors = [];

        foreach ($allMetadata as $classMetadata) {
            /** @var ClassMetadata<object> $classMetadata */
            $entityClass = $classMetadata->getName();

            if ($this->shouldSkipEntity($classMetadata)) {
                continue;
            }

            $newContent = $this->generateStructureClass($classMetadata, $namespace, $suffix);
            $fileName = $this->getClassNameFromEntity($entityClass) . $suffix . '.php';
            $filePath = $outputDir . '/' . $fileName;

            $isNew = ! file_exists($filePath);
            $existingContent = $isNew ? null : file_get_contents($filePath);
            $hasChanged = $isNew || $existingContent !== $newContent;

            if (! $hasChanged) {
                ++$unchangedCount;
                continue;
            }

            $bytesWritten = file_put_contents($filePath, $newContent);

            if ($bytesWritten === false) {
                $errors[] = $fileName;
                $io->error("Failed to write file: {$fileName}");
            } elseif ($isNew) {
                $newFiles[] = $fileName;
            } else {
                $updatedFiles[] = $fileName;
            }
        }

        if (! empty($newFiles)) {
            $io->section(sprintf('New files (%d):', count($newFiles)));
            foreach ($newFiles as $file) {
                $io->text(" - {$file}");
            }
        }

        if (! empty($updatedFiles)) {
            $io->section(sprintf('Updated files (%d):', count($updatedFiles)));
            foreach ($updatedFiles as $file) {
                $io->text(" - {$file}");
            }
        }

        if (! empty($errors)) {
            $io->error(sprintf('Failed to write %d file(s)', count($errors)));

            return Command::FAILURE;
        }

        $totalFiles = count($newFiles) + count($updatedFiles) + $unchangedCount;
        $io->success(sprintf(
            '%d new, %d updated, %d unchanged (total %d files)',
            count($newFiles),
            count($updatedFiles),
            $unchangedCount,
            $totalFiles
        ));

        return Command::SUCCESS;
    }

    private function executeCheck(SymfonyStyle $io, string $outputDir, string $namespace, string $suffix): int
    {
        $io->title('Checking Entity Property Structure Classes');

        $metadataFactory = $this->entityManager->getMetadataFactory();
        $allMetadata = $metadataFactory->getAllMetadata();

        $missing = [];
        $outdated = [];
        $checked = 0;

        foreach ($allMetadata as $classMetadata) {
            /** @var ClassMetadata<object> $classMetadata */
            $entityClass = $classMetadata->getName();

            if ($this->shouldSkipEntity($classMetadata)) {
                continue;
            }

            $expectedContent = $this->generateStructureClass($classMetadata, $namespace, $suffix);
            $fileName = $this->getClassNameFromEntity($entityClass) . $suffix . '.php';
            $filePath = $outputDir . '/' . $fileName;

            if (! file_exists($filePath)) {
                $missing[] = [
                    'file'   => $fileName,
                    'entity' => $entityClass,
                ];
                continue;
            }

            $actualContent = file_get_contents($filePath);
            if ($expectedContent !== $actualContent) {
                $outdated[] = [
                    'file'   => $fileName,
                    'entity' => $entityClass,
                ];
            }

            ++$checked;
        }

        if (empty($missing) && empty($outdated)) {
            $io->success(sprintf('All %d structure files are up-to-date', $checked));

            return Command::SUCCESS;
        }

        if (! empty($missing)) {
            $io->section(sprintf('Missing structure files (%d):', count($missing)));
            foreach ($missing as $item) {
                $io->text(sprintf(' - %s (for %s)', $item['file'], $item['entity']));
            }
        }

        if (! empty($outdated)) {
            $io->section(sprintf('Outdated structure files (%d):', count($outdated)));
            foreach ($outdated as $item) {
                $io->text(sprintf(' - %s (for %s)', $item['file'], $item['entity']));
            }
        }

        $totalProblems = count($missing) + count($outdated);
        $io->error(sprintf('%d structure file(s) need to be regenerated. Run without --check to update.', $totalProblems));

        return Command::FAILURE;
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function generateStructureClass(ClassMetadata $metadata, string $namespace, string $suffix): string
    {
        $entityClass = $metadata->getName();
        $className = $this->getClassNameFromEntity($entityClass) . $suffix;
        $shortEntityName = $this->getClassNameFromEntity($entityClass);

        $constants = [];

        // Get all field mappings (regular fields)
        $fieldMappings = $metadata->fieldMappings;
        foreach ($fieldMappings as $fieldName => $mapping) {
            $constants[] = <<<PHPDOC
    /**
     * @see {$shortEntityName}::\${$fieldName}
     */
PHPDOC;
            $constants[] = "    public const {$fieldName} = '{$fieldName}';";
            $constants[] = '';
        }

        // Get all association mappings (relationships)
        $associationMappings = $metadata->associationMappings;
        foreach ($associationMappings as $fieldName => $mapping) {
            $constants[] = <<<PHPDOC
    /**
     * @see {$shortEntityName}::\${$fieldName}
     */
PHPDOC;
            $constants[] = "    public const {$fieldName} = '{$fieldName}';";
            $constants[] = '';
        }

        // Remove trailing empty line
        if (end($constants) === '') {
            array_pop($constants);
        }

        $constantsCode = implode("\n", $constants);

        $absoluteEntityClass = '\\' . ltrim($entityClass, '\\');

        $namespace = trim($namespace, '\\');

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

/**
 * Property structure for @see {$absoluteEntityClass}
 */
class {$className}
{
{$constantsCode}
}

PHP;
    }

    private function getClassNameFromEntity(string $entityClass): string
    {
        $parts = explode('\\', $entityClass);

        return end($parts);
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     */
    private function shouldSkipEntity(ClassMetadata $classMetadata): bool
    {
        $entityClass = $classMetadata->getName();

        // Skip non-App entities (like Doctrine migrations, etc.)
        if (! str_starts_with($entityClass, 'App\\')) {
            return true;
        }

        // Skip mapped superclasses (abstract parent entities)
        if ($classMetadata->isMappedSuperclass) {
            return true;
        }

        return false;
    }
}
