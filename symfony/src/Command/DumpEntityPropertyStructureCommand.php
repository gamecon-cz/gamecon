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
            ->setHelp('This command generates PHP classes with constants for entity property names.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $outputDir = $input->getOption('output-dir');
        $namespace = $input->getOption('namespace');
        $suffix = $input->getOption('suffix');

        $io->title('Generating Entity Property Structure Classes');

        // Create output directory if it doesn't exist
        if (! $this->filesystem->exists($outputDir)) {
            $this->filesystem->mkdir($outputDir);
            $io->info("Created output directory: {$outputDir}");
        }

        $metadataFactory = $this->entityManager->getMetadataFactory();
        $allMetadata = $metadataFactory->getAllMetadata();

        $generatedFiles = [];
        $errors = [];

        foreach ($allMetadata as $classMetadata) {
            /** @var ClassMetadata<object> $classMetadata */
            $entityClass = $classMetadata->getName();

            // Skip non-App entities (like Doctrine migrations, etc.)
            if (! str_starts_with($entityClass, 'App\\')) {
                continue;
            }

            $structureClass = $this->generateStructureClass($classMetadata, $namespace, $suffix);
            $fileName = $this->getClassNameFromEntity($entityClass) . $suffix . '.php';
            $filePath = $outputDir . '/' . $fileName;

            $bytesWritten = file_put_contents($filePath, $structureClass);

            if ($bytesWritten === false) {
                $errors[] = "Failed to write file: {$fileName}";
                $io->error("Failed to write file: {$fileName}");
            } else {
                $generatedFiles[] = $fileName;
                $io->text("Generated: {$fileName} for entity {$entityClass} - {$bytesWritten} bytes");
            }
        }

        if (! empty($errors)) {
            $io->warning(sprintf('Generated %d files with %d errors', count($generatedFiles), count($errors)));

            return Command::FAILURE;
        }

        $io->success(sprintf('Generated %d structure classes in %s', count($generatedFiles), $outputDir));

        return Command::SUCCESS;
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
}
