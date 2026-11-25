<?php

namespace Castor\Import;

use Castor\Import\Exception\ImportError;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Adds "register_plugin('vendor/package');" to a castor.php file, after a
 * package was required. The file is parsed only to know where to insert the
 * code: the rest of it is left untouched, formatting included.
 *
 * @internal
 */
final class PluginRegistrationWriter
{
    private const FUNCTION = 'Castor\register_plugin';

    /**
     * Whether the code already calls register_plugin() for the package.
     */
    public function isRegistered(string $code, string $package): bool
    {
        try {
            $stmts = $this->parse($code);
        } catch (ImportError) {
            return false;
        }

        foreach (new NodeFinder()->findInstanceOf($stmts, FuncCall::class) as $call) {
            if (!$call->name instanceof Name || 'register_plugin' !== strtolower($call->name->getLast())) {
                continue;
            }

            $argument = $call->getArgs()[0] ?? null;

            if ($argument?->value instanceof String_ && strtolower($argument->value->value) === strtolower($package)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the code with the call to register_plugin() added at its end,
     * and the function imported.
     *
     * @throws ImportError When the code cannot be changed safely, with the reason
     */
    public function add(string $code, string $package): string
    {
        $stmts = $this->parse($code);

        if (str_ends_with(rtrim($code), '?>')) {
            throw new ImportError('it ends with a closing "?>" tag');
        }

        $namespaces = array_values(array_filter($stmts, static fn (Stmt $stmt): bool => $stmt instanceof Stmt\Namespace_));

        if (\count($namespaces) > 1) {
            throw new ImportError('it declares several namespaces');
        }

        $namespace = $namespaces[0] ?? null;

        if ($namespace && Stmt\Namespace_::KIND_BRACED === $namespace->getAttribute('kind')) {
            throw new ImportError('its namespace uses braces');
        }

        // The statements of a namespace without braces are the ones after it
        $scope = $namespace ? $namespace->stmts : $stmts;
        $call = \sprintf("register_plugin('%s');\n", addcslashes($package, "'\\"));

        if (!$this->isImported($scope)) {
            $use = 'use function ' . self::FUNCTION . ';';
            [$position, $before, $after] = $this->findUsePosition($code, $scope, $namespace);
            $code = substr($code, 0, $position) . $before . $use . $after . substr($code, $position);
        }

        return rtrim($code) . "\n\n" . $call;
    }

    /**
     * @return array<Stmt>
     */
    private function parse(string $code): array
    {
        try {
            $stmts = new ParserFactory()->createForHostVersion()->parse($code);
        } catch (Error $e) {
            throw new ImportError('it cannot be parsed: ' . $e->getMessage(), previous: $e);
        }

        return $stmts ?? [];
    }

    /**
     * @param array<Stmt> $scope
     */
    private function isImported(array $scope): bool
    {
        foreach ($scope as $stmt) {
            if ($stmt instanceof Stmt\Use_ && Stmt\Use_::TYPE_FUNCTION === $stmt->type) {
                foreach ($stmt->uses as $use) {
                    if (self::FUNCTION === $use->name->toString() && 'register_plugin' === $use->getAlias()->toString()) {
                        return true;
                    }
                }
            }

            if ($stmt instanceof Stmt\GroupUse) {
                foreach ($stmt->uses as $use) {
                    $type = Stmt\Use_::TYPE_UNKNOWN === $use->type ? $stmt->type : $use->type;
                    $name = Name::concat($stmt->prefix, $use->name)?->toString();

                    if (Stmt\Use_::TYPE_FUNCTION === $type && self::FUNCTION === $name && 'register_plugin' === $use->getAlias()->toString()) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Where to insert the import of the function: after the last import of a
     * Castor function, of a function, or of anything, else after the
     * namespace, else before the first statement.
     *
     * @param array<Stmt> $scope
     *
     * @return array{int, string, string} The position, and what to write before and after the import
     */
    private function findUsePosition(string $code, array $scope, ?Stmt\Namespace_ $namespace): array
    {
        $lastUse = $lastFunctionUse = $lastCastorFunctionUse = null;

        foreach ($scope as $stmt) {
            if (!$stmt instanceof Stmt\Use_ && !$stmt instanceof Stmt\GroupUse) {
                continue;
            }

            $lastUse = $stmt;

            if (Stmt\Use_::TYPE_FUNCTION !== $stmt->type) {
                continue;
            }

            $lastFunctionUse = $stmt;
            $prefix = $stmt instanceof Stmt\GroupUse ? $stmt->prefix->toString() : $stmt->uses[0]->name->toString();

            if (str_starts_with($prefix, 'Castor\\') || 'Castor' === $prefix) {
                $lastCastorFunctionUse = $stmt;
            }
        }

        // In alphabetical order among the imports of a single Castor function
        foreach ($scope as $stmt) {
            if ($stmt instanceof Stmt\Use_ && Stmt\Use_::TYPE_FUNCTION === $stmt->type && 1 === \count($stmt->uses)) {
                $name = $stmt->uses[0]->name->toString();

                if (str_starts_with($name, 'Castor\\') && strcasecmp($name, self::FUNCTION) > 0) {
                    return [$this->getStart($stmt), '', "\n"];
                }
            }
        }

        if ($stmt = $lastCastorFunctionUse ?? $lastFunctionUse) {
            return [$this->getEnd($stmt), "\n", ''];
        }

        if ($lastUse) {
            return [$this->getEnd($lastUse), "\n\n", ''];
        }

        if ($namespace) {
            // A namespace without braces ends with a semicolon after its name
            $name = $namespace->name;
            $position = null === $name ? false : strpos($code, ';', $this->getEnd($name));

            if (false !== $position) {
                return [$position + 1, "\n\n", ''];
            }
        }

        $previous = null;

        foreach ($scope as $stmt) {
            // Like "\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);", which stays at the top
            if ($stmt instanceof Stmt\Declare_ || $stmt instanceof Stmt\InlineHTML || $this->isDefinition($stmt)) {
                $previous = $stmt;

                continue;
            }

            return [$this->getStart($stmt), '', "\n\n"];
        }

        if ($previous) {
            return [$this->getEnd($previous), "\n\n", ''];
        }

        // Nothing but the opening tag
        return [\strlen(rtrim($code)), "\n\n", ''];
    }

    /**
     * Whether the statement only calls define() or defined().
     */
    private function isDefinition(Stmt $stmt): bool
    {
        if (!$stmt instanceof Stmt\Expression) {
            return false;
        }

        $calls = new NodeFinder()->findInstanceOf($stmt, FuncCall::class);

        foreach ($calls as $call) {
            if (!$call->name instanceof Name || !\in_array(strtolower($call->name->getLast()), ['define', 'defined'], true)) {
                return false;
            }
        }

        return [] !== $calls;
    }

    /**
     * Where the node starts, its comments included.
     */
    private function getStart(Node $node): int
    {
        $comments = $node->getComments();

        return $comments ? $comments[0]->getStartFilePos() : $node->getStartFilePos();
    }

    private function getEnd(Node $node): int
    {
        return $node->getEndFilePos() + 1;
    }
}
