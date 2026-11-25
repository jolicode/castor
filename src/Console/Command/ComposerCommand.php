<?php

namespace Castor\Console\Command;

use Castor\Console\Input\GetRawTokenTrait;
use Castor\Import\Exception\ImportError;
use Castor\Import\PluginRegistrar;
use Castor\Import\PluginRegistrationWriter;
use Castor\Import\Remote\Composer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;

/** @internal */
#[AsCommand(
    name: 'castor:composer',
    description: 'Interact with built-in Composer for castor',
    aliases: ['composer'],
)]
final class ComposerCommand extends Command
{
    use GetRawTokenTrait;

    public function __construct(
        private readonly string $rootDir,
        #[Autowire(lazy: true)]
        private readonly Composer $composer,
        private readonly PluginRegistrationWriter $pluginRegistrationWriter,
        #[Autowire('%castor_file_path%')]
        private readonly ?string $castorFilePath = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->ignoreValidationErrors()
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Default to the root directory (so someone can do a composer init by example)
        $file = $this->composer->findComposerJsonFile($this->rootDir) ?? $this->rootDir . '/castor.composer.json';

        $vendorDirectory = $this->rootDir . '/' . Composer::VENDOR_DIR;
        $tokens = $this->getRawTokens($input, false);

        if (\in_array('init', $tokens)) {
            $lastDirectory = basename($this->rootDir);

            if (!\in_array('--name', $tokens)) {
                $tokens[] = '--name';
                $tokens[] = $lastDirectory . '/castor';
            }

            if (!\in_array('--description', $tokens)) {
                $tokens[] = '--description';
                $tokens[] = "Castor commands for project {$lastDirectory}";
            }

            if (!\in_array('--type', $tokens)) {
                $tokens[] = '--type';
                $tokens[] = 'castor-project';
            }
        }

        $requiredPackages = $this->getRequiredPackages($file);

        $this->composer->run($file, $vendorDirectory, $tokens, $output, $input->isInteractive());

        $newRequiredPackages = $this->getRequiredPackages($file);

        foreach (array_diff($newRequiredPackages, $requiredPackages) as $package) {
            $this->suggestRegistration($input, $output, $vendorDirectory, $package);
        }

        foreach (array_diff($requiredPackages, $newRequiredPackages) as $package) {
            $this->remindUnregistration($input, $output, $package);
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function getRequiredPackages(string $composerJsonFile): array
    {
        if (!file_exists($composerJsonFile)) {
            return [];
        }

        $json = json_decode((string) file_get_contents($composerJsonFile), true);
        $require = \is_array($json) ? ($json['require'] ?? []) : [];

        return \is_array($require) ? array_map(strtolower(...), array_map(strval(...), array_keys($require))) : [];
    }

    /**
     * Proposes to register a plugin just required in castor.php, or tells how
     * to do it.
     */
    private function suggestRegistration(InputInterface $input, OutputInterface $output, string $vendorDirectory, string $package): void
    {
        if (null === $features = $this->getPluginFeatures($vendorDirectory, $package)) {
            return;
        }

        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);
        $castorFile = $this->findCastorFile();
        $call = \sprintf("register_plugin('%s');", $package);
        $featuresHint = $features ? \sprintf(' It also declares the features "%s", to load instead with "register_plugin(\'%s\', \'<feature>\')".', implode('", "', $features), $package) : '';

        $newCode = null;

        if (null === $castorFile) {
            $reason = 'there is no castor.php file';
        } else {
            $code = (string) file_get_contents($castorFile);

            if ($this->pluginRegistrationWriter->isRegistered($code, $package)) {
                return;
            }

            try {
                $newCode = $this->pluginRegistrationWriter->add($code, $package);
                $reason = $input->isInteractive() ? null : 'the command is not interactive';
            } catch (ImportError $e) {
                $reason = $e->getMessage();
            }
        }

        if (null === $reason && null !== $newCode && null !== $castorFile) {
            $relativeFile = Path::makeRelative($castorFile, $this->rootDir);

            if (!$io->confirm(\sprintf('The package "%s" is a Castor plugin, register it in %s to expose its tasks, contexts and listeners?', $package, $relativeFile), true)) {
                $io->note(\sprintf('Add "%s" to %s to expose them later.', $call, $relativeFile) . $featuresHint);

                return;
            }

            file_put_contents($castorFile, $newCode);
            $io->success(\sprintf('Added "%s" to %s.', $call, $relativeFile) . $featuresHint);

            return;
        }

        $io->note(\sprintf('The package "%s" is a Castor plugin: add "use function Castor\register_plugin;" and "%s" to your castor.php file to expose its tasks, contexts and listeners (Castor could not do it, as %s).', $package, $call, $reason) . $featuresHint);
    }

    /**
     * Reminds to remove the registration of a plugin just removed: the code
     * of castor.php is left as it is.
     */
    private function remindUnregistration(InputInterface $input, OutputInterface $output, string $package): void
    {
        if (null === $castorFile = $this->findCastorFile()) {
            return;
        }

        if (!$this->pluginRegistrationWriter->isRegistered((string) file_get_contents($castorFile), $package)) {
            return;
        }

        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);
        $io->note(\sprintf('The package "%s" is no longer required: remove its "register_plugin()" call from %s, or Castor will stop with an error.', $package, Path::makeRelative($castorFile, $this->rootDir)));
    }

    /**
     * The features of the package when it is a Castor plugin, or null.
     *
     * @return list<string>|null
     */
    private function getPluginFeatures(string $vendorDirectory, string $package): ?array
    {
        if (!file_exists($file = $vendorDirectory . '/composer/installed.json')) {
            return null;
        }

        $installed = json_decode((string) file_get_contents($file), true);

        foreach (\is_array($installed) ? ($installed['packages'] ?? []) : [] as $definition) {
            if (!\is_array($definition) || $package !== strtolower((string) ($definition['name'] ?? ''))) {
                continue;
            }

            $features = $definition['extra']['castor'][PluginRegistrar::FEATURES_EXTRA_KEY] ?? null;

            if (Composer::CASTOR_PLUGIN_TYPE !== ($definition['type'] ?? null) && !\is_array($features)) {
                return null;
            }

            return \is_array($features) ? array_values(array_filter(array_map(strval(...), array_keys($features)), static fn (string $feature): bool => PluginRegistrar::DEFAULT_FEATURE !== $feature)) : [];
        }

        return null;
    }

    private function findCastorFile(): ?string
    {
        $candidates = null !== $this->castorFilePath ? [$this->rootDir . '/' . $this->castorFilePath] : [$this->rootDir . '/castor.php', $this->rootDir . '/.castor/castor.php'];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
