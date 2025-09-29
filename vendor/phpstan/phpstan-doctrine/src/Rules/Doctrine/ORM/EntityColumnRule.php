<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\ClassPropertyNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Doctrine\DescriptorNotRegisteredException;
use PHPStan\Type\Doctrine\DescriptorRegistry;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\ErrorType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypehintHelper;
use PHPStan\Type\TypeTraverser;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\UnionType;
use PHPStan\Type\VerbosityLevel;
use Throwable;
use function count;
use function get_class;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;

/**
 * @implements Rule<ClassPropertyNode>
 */
class EntityColumnRule implements Rule
{

	private ObjectMetadataResolver $objectMetadataResolver;

	private DescriptorRegistry $descriptorRegistry;

	private ReflectionProvider $reflectionProvider;

	private bool $reportUnknownTypes;

	private bool $allowNullablePropertyForRequiredField;

	public function __construct(
		ObjectMetadataResolver $objectMetadataResolver,
		DescriptorRegistry $descriptorRegistry,
		ReflectionProvider $reflectionProvider,
		bool $reportUnknownTypes,
		bool $allowNullablePropertyForRequiredField
	)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
		$this->descriptorRegistry = $descriptorRegistry;
		$this->reflectionProvider = $reflectionProvider;
		$this->reportUnknownTypes = $reportUnknownTypes;
		$this->allowNullablePropertyForRequiredField = $allowNullablePropertyForRequiredField;
	}

	public function getNodeType(): string
	{
		return ClassPropertyNode::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$class = $scope->getClassReflection();
		if ($class === null) {
			return [];
		}

		$className = $class->getName();
		$metadata = $this->objectMetadataResolver->getClassMetadata($className);
		if ($metadata === null) {
			return [];
		}

		$propertyName = $node->getName();
		if (!isset($metadata->fieldMappings[$propertyName])) {
			return [];
		}

		$fieldMapping = $metadata->fieldMappings[$propertyName];

		$errors = [];
		try {
			$descriptor = $this->descriptorRegistry->get($fieldMapping['type']);
		} catch (DescriptorNotRegisteredException $e) {
			return $this->reportUnknownTypes ? [
				RuleErrorBuilder::message(sprintf(
					'Property %s::$%s: Doctrine type "%s" does not have any registered descriptor.',
					$className,
					$propertyName,
					$fieldMapping['type'],
				))->identifier('doctrine.descriptorNotFound')->build(),
			] : [];
		}

		$writableToPropertyType = $descriptor->getWritableToPropertyType();
		$writableToDatabaseType = $descriptor->getWritableToDatabaseType();

		$enumTypeString = $fieldMapping['enumType'] ?? null;
		if ($enumTypeString !== null) {
			if ($writableToDatabaseType->isArray()->no() && $writableToPropertyType->isArray()->no()) {
				if ($this->reflectionProvider->hasClass($enumTypeString)) {
					$enumReflection = $this->reflectionProvider->getClass($enumTypeString);
					$backedEnumType = $enumReflection->getBackedEnumType();
					if ($backedEnumType !== null) {
						if (!$backedEnumType->equals($writableToDatabaseType) || !$backedEnumType->equals($writableToPropertyType)) {
							$errors[] = RuleErrorBuilder::message(sprintf(
								'Property %s::$%s type mapping mismatch: backing type %s of enum %s does not match database type %s.',
								$className,
								$propertyName,
								$backedEnumType->describe(VerbosityLevel::typeOnly()),
								$enumReflection->getDisplayName(),
								$writableToDatabaseType->describe(VerbosityLevel::typeOnly()),
							))->identifier('doctrine.enumType')->build();
						}
					}
				}
				$enumType = new ObjectType($enumTypeString);
				$writableToPropertyType = $enumType;
				$writableToDatabaseType = $enumType;
			} else {
				$enumType = new ObjectType($enumTypeString);
				if ($this->reflectionProvider->hasClass($enumTypeString)) {
					$enumReflection = $this->reflectionProvider->getClass($enumTypeString);
					$backedEnumType = $enumReflection->getBackedEnumType();
					if ($backedEnumType !== null) {
						if (!$backedEnumType->equals($writableToDatabaseType->getIterableValueType()) || !$backedEnumType->equals($writableToPropertyType->getIterableValueType())) {
							$errors[] = RuleErrorBuilder::message(
								sprintf(
									'Property %s::$%s type mapping mismatch: backing type %s of enum %s does not match value type %s of the database type %s.',
									$className,
									$propertyName,
									$backedEnumType->describe(VerbosityLevel::typeOnly()),
									$enumReflection->getDisplayName(),
									$writableToDatabaseType->getIterableValueType()->describe(VerbosityLevel::typeOnly()),
									$writableToDatabaseType->describe(VerbosityLevel::typeOnly()),
								),
							)->identifier('doctrine.enumType')->build();
						}
					}
				}

				$writableToPropertyType = TypeCombinator::intersect(new ArrayType(
					$writableToPropertyType->getIterableKeyType(),
					$enumType,
				), ...TypeUtils::getAccessoryTypes($writableToPropertyType));
				$writableToDatabaseType = TypeCombinator::intersect(new ArrayType(
					$writableToDatabaseType->getIterableKeyType(),
					$enumType,
				), ...TypeUtils::getAccessoryTypes($writableToDatabaseType));

			}
		} elseif ($fieldMapping['type'] === 'enum') {
			$values = $fieldMapping['options']['values'] ?? null;
			if (is_array($values)) {
				$enumTypes = [];
				foreach ($values as $value) {
					if (!is_string($value)) {
						$enumTypes = [];
						break;
					}

					$enumTypes[] = new ConstantStringType($value);
				}

				if (count($enumTypes) > 0) {
					$writableToPropertyType = new UnionType($enumTypes);
					$writableToDatabaseType = new UnionType($enumTypes);
				}
			}
		}

		$identifiers = [];
		if ($metadata->generatorType !== 5) { // ClassMetadata::GENERATOR_TYPE_NONE
			try {
				$identifiers = $metadata->getIdentifierFieldNames();
			} catch (Throwable $e) {
				$mappingException = 'Doctrine\ORM\Mapping\MappingException';
				if (!$e instanceof $mappingException) {
					throw $e;
				}
			}
		}

		$nullable = isset($fieldMapping['nullable']) ? $fieldMapping['nullable'] === true : false;
		if ($nullable) {
			$writableToPropertyType = TypeCombinator::addNull($writableToPropertyType);
			$writableToDatabaseType = TypeCombinator::addNull($writableToDatabaseType);
		}

		$phpDocType = $node->getPhpDocType();
		$nativeType = $node->getNativeType() ?? new MixedType();
		$propertyType = TypehintHelper::decideType($nativeType, $phpDocType);

		if (get_class($propertyType) === MixedType::class || $propertyType instanceof ErrorType || $propertyType instanceof NeverType) {
			return [];
		}

		// If the type descriptor does not precise the types inside the array, don't report errors if the field has a more precise type
		$propertyTransformedType = $writableToPropertyType->equals(new ArrayType(new MixedType(), new MixedType())) ? TypeTraverser::map($propertyType, static function (Type $type, callable $traverse): Type {
			if ($type instanceof ArrayType) {
				return new ArrayType(new MixedType(), new MixedType());
			}

			return $traverse($type);
		}) : $propertyType;

		if (!$propertyTransformedType->isSuperTypeOf($writableToPropertyType)->yes()) {
			$errors[] = RuleErrorBuilder::message(sprintf(
				'Property %s::$%s type mapping mismatch: database can contain %s but property expects %s.',
				$className,
				$propertyName,
				$writableToPropertyType->describe(VerbosityLevel::getRecommendedLevelByType($propertyTransformedType, $writableToPropertyType)),
				$propertyType->describe(VerbosityLevel::getRecommendedLevelByType($propertyTransformedType, $writableToPropertyType)),
			))->identifier('doctrine.columnType')->build();
		}

		if (
			!$writableToDatabaseType->isSuperTypeOf(
				$this->allowNullablePropertyForRequiredField || (in_array($propertyName, $identifiers, true) && !$nullable)
					? TypeCombinator::removeNull($propertyType)
					: $propertyType,
			)->yes()
		) {
			$errors[] = RuleErrorBuilder::message(sprintf(
				'Property %s::$%s type mapping mismatch: property can contain %s but database expects %s.',
				$className,
				$propertyName,
				$propertyTransformedType->describe(VerbosityLevel::getRecommendedLevelByType($writableToDatabaseType, $propertyType)),
				$writableToDatabaseType->describe(VerbosityLevel::getRecommendedLevelByType($writableToDatabaseType, $propertyType)),
			))->identifier('doctrine.columnType')->build();
		}
		return $errors;
	}

}
