<?php

declare(strict_types=1);

namespace Rector\PHPStanStaticTypeMapper\TypeMapper;

use PhpParser\Node;
use PhpParser\Node\Name;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Type\Generic\TemplateObjectWithoutClassType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\Type;
use Rector\BetterPhpDocParser\ValueObject\Type\EmptyGenericTypeNode;
use Rector\Core\Php\PhpVersionProvider;
use Rector\Core\ValueObject\PhpVersionFeature;
use Rector\PHPStanStaticTypeMapper\Contract\TypeMapperInterface;
use Rector\PHPStanStaticTypeMapper\PHPStanStaticTypeMapper;
use Symfony\Contracts\Service\Attribute\Required;

final class ObjectWithoutClassTypeMapper implements TypeMapperInterface
{
    private PHPStanStaticTypeMapper $phpStanStaticTypeMapper;

    public function __construct(
        private PhpVersionProvider $phpVersionProvider
    ) {
    }

    /**
     * @return class-string<Type>
     */
    public function getNodeClass(): string
    {
        return ObjectWithoutClassType::class;
    }

    /**
     * @param ObjectWithoutClassType $type
     */
    public function mapToPHPStanPhpDocTypeNode(Type $type): TypeNode
    {
        if ($type instanceof TemplateObjectWithoutClassType) {
            $attributeAwareIdentifierTypeNode = new IdentifierTypeNode($type->getName());
            return new EmptyGenericTypeNode($attributeAwareIdentifierTypeNode);
        }

        return new IdentifierTypeNode('object');
    }

    /**
     * @param ObjectWithoutClassType $type
     */
    public function mapToPhpParserNode(Type $type, ?string $kind = null): ?Node
    {
        $subtractedType = $type->getSubtractedType();
        if ($subtractedType !== null) {
            return $this->phpStanStaticTypeMapper->mapToPhpParserNode($subtractedType);
        }

        if (! $this->phpVersionProvider->isAtLeastPhpVersion(PhpVersionFeature::OBJECT_TYPE)) {
            return null;
        }

        return new Name('object');
    }

    #[Required]
    public function autowireObjectWithoutClassTypeMapper(PHPStanStaticTypeMapper $phpStanStaticTypeMapper): void
    {
        $this->phpStanStaticTypeMapper = $phpStanStaticTypeMapper;
    }
}
