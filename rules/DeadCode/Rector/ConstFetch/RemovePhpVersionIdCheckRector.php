<?php

declare(strict_types=1);

namespace Rector\DeadCode\Rector\ConstFetch;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\GreaterOrEqual;
use PhpParser\Node\Expr\BinaryOp\Smaller;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Stmt\If_;
use Rector\Core\Contract\Rector\ConfigurableRectorInterface;
use Rector\Core\Rector\AbstractRector;
use Rector\Core\Util\PhpVersionFactory;
use Rector\Core\ValueObject\PhpVersion;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Rector\Tests\DeadCode\Rector\ConstFetch\RemovePhpVersionIdCheckRector\RemovePhpVersionIdCheckRectorTest
 */
final class RemovePhpVersionIdCheckRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * @var string
     */
    public const PHP_VERSION_CONSTRAINT = 'phpVersionConstraint';

    private string | int | null $phpVersionConstraint;

    public function __construct(private PhpVersionFactory $phpVersionFactory)
    {
    }

    /**
     * @param array<string, int|string> $configuration
     */
    public function configure(array $configuration): void
    {
        $this->phpVersionConstraint = $configuration[self::PHP_VERSION_CONSTRAINT] ?? null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        $exampleConfiguration = [
            self::PHP_VERSION_CONSTRAINT => PhpVersion::PHP_80,
        ];
        return new RuleDefinition(
            'Remove unneded PHP_VERSION_ID check',
            [
                new ConfiguredCodeSample(
                    <<<'CODE_SAMPLE'
class SomeClass
{
    public function run()
    {
        if (PHP_VERSION_ID < 80000) {
            return;
        }
        echo 'do something';
    }
}
CODE_SAMPLE
,
                    <<<'CODE_SAMPLE'
class SomeClass
{
    public function run()
    {
        echo 'do something';
    }
}
CODE_SAMPLE
,
$exampleConfiguration
                ),
            ],
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [ConstFetch::class];
    }

    /**
     * @param ConstFetch $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $this->isName($node, 'PHP_VERSION_ID')) {
            return null;
        }

        /**
         * $this->phpVersionProvider->provide() fallback is here as $currentFileProvider must be accessed after initialization
         */
        $phpVersionConstraint = $this->phpVersionConstraint ?? $this->phpVersionProvider->provide();

        // ensure cast to (string) first to allow string like "8.0" value to be converted to the int value
        $this->phpVersionConstraint = $this->phpVersionFactory->createIntVersion((string) $phpVersionConstraint);

        $parent = $node->getAttribute(AttributeKey::PARENT_NODE);
        if ($parent instanceof Smaller) {
            return $this->processSmaller($node, $parent);
        }

        if ($parent instanceof GreaterOrEqual) {
            return $this->processGreaterOrEqual($node, $parent);
        }

        return null;
    }

    private function processSmaller(ConstFetch $constFetch, Smaller $smaller): ?ConstFetch
    {
        $parent = $smaller->getAttribute(AttributeKey::PARENT_NODE);
        if (! $parent instanceof If_ || $parent->cond !== $smaller) {
            return null;
        }

        $value  = $smaller->left === $constFetch
            ? $smaller->right
            : $smaller->left;

        if (! $value instanceof LNumber) {
            return null;
        }

        if ($this->phpVersionConstraint <= $value->value) {
            $this->removeNode($parent);
        }

        return $constFetch;
    }

    private function processGreaterOrEqual(ConstFetch $constFetch, GreaterOrEqual $greaterOrEqual): ConstFetch
    {
        $parent = $greaterOrEqual->getAttribute(AttributeKey::PARENT_NODE);
        if (! $parent instanceof If_ || $parent->cond !== $greaterOrEqual) {
            return null;
        }

        $value  = $greaterOrEqual->left === $constFetch
            ? $greaterOrEqual->right
            : $greaterOrEqual->left;

        if (! $value instanceof LNumber) {
            return null;
        }

        if ($this->phpVersionConstraint <= $value->value) {
            $this->addNodesBeforeNode($parent->stmts, $parent);
            $this->removeNode($parent);
        }

        return $constFetch;
    }
}
