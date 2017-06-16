<?php

declare(strict_types=1);

namespace Facile\CodingStandards\Installer;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\AliasPackage;
use Composer\Package\BasePackage;
use Composer\Script\Event;

class Configuration
{
    /**
     * @var IOInterface
     */
    private $io;
    /**
     * @var string
     */
    private $projectRoot;
    /**
     * @var array
     */
    private $composerDefinition;
    /**
     * @var JsonFile
     */
    private $composerJson;
    /**
     * @var BasePackage
     */
    private $rootPackage;

    public static function install(Event $event)
    {
        $installer = new self($event->getIO(), $event->getComposer());
        $installer->io->write('<info>Setting up Facile.it Coding Standards</info>');
        $installer->requestCreateCsConfig();
        $installer->requestAddComposerScripts();
    }

    /**
     * @param IOInterface $io
     * @param Composer $composer
     * @param null|string $projectRoot
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function __construct(IOInterface $io, Composer $composer, $projectRoot = null)
    {
        $this->io = $io;
        // Get composer.json location
        $composerFile = Factory::getComposerFile();
        // Calculate project root from composer.json, if necessary
        $this->projectRoot = $projectRoot ?: realpath(dirname($composerFile));
        $this->projectRoot = rtrim($this->projectRoot, '/\\').'/';
        // Parse the composer.json
        $this->parseComposerDefinition($composer, $composerFile);
    }

    /**
     * @param Composer $composer
     * @param string $composerFile
     * @return void
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    private function parseComposerDefinition(Composer $composer, $composerFile)
    {
        $this->composerJson = new JsonFile($composerFile);
        $this->composerDefinition = $this->composerJson->read();
        // Get root package
        $this->rootPackage = $composer->getPackage();
        while ($this->rootPackage instanceof AliasPackage) {
            $this->rootPackage = $this->rootPackage->getAliasOf();
        }
    }

    public function requestCreateCsConfig()
    {
        $destPath = $this->projectRoot.'/.php_cs.dist';

        if (file_exists($destPath)) {
            $this->io->write(sprintf("\n  <comment>Skipping... CS config file already exists</comment>"));
            $this->io->write(sprintf('  <info>Delete .php_cs.dist if you want to install it.</info>'));
            return;
        }

        $question = [
            sprintf(
                "\n  <question>%s</question>\n",
                'Do you want to create the CS configuration in your project root? (Y/n)'
            ),
            '  <info>It will create a .php_cs.dist file in your project root directory.</info> ',
        ];
        $answer = $this->io->askConfirmation($question, true);

        if (! $answer) {
            return;
        }

        $this->io->write(sprintf("\n  <info>Writing configuration in project root...</info>"));

        file_put_contents($this->projectRoot.'/.php_cs.dist', $this->createCSFile($this->getAutoloadPaths()));
    }

    protected function getAutoloadPaths()
    {
        if (! array_key_exists('autoload', $this->composerDefinition)) {
            return [];
        }

        $paths = [];
        $autoloads = ['psr-0', 'psr-4'];
        foreach ($autoloads as $autoload) {
            if (! array_key_exists($autoload, $this->composerDefinition['autoload'])) {
                continue;
            }

            foreach ($this->composerDefinition['autoload'][$autoload] as $autoloadPaths) {
                if (!is_array($autoloadPaths)) {
                    $autoloadPaths = [$autoloadPaths];
                }
                foreach ($autoloadPaths as $autoloadPath) {
                    if (in_array($autoload, $paths)) {
                        continue;
                    }

                    $paths[] = $autoloadPath;
                }
            }
        }

        return $paths;
    }

    public function requestAddComposerScripts()
    {
        $scripts = [
            'cs-check' => 'php-cs-fixer fix --dry-run --diff',
            'cs-fix' => 'php-cs-fixer fix --diff',
        ];

        if (! array_key_exists('scripts', $this->composerDefinition)) {
            $this->composerDefinition['scripts'] = [];
        }

        if (0 === count(array_diff_key($scripts, $this->composerDefinition['scripts']))) {
            $this->io->write(sprintf("\n  <comment>Skipping... Scripts already exist in composer.json.</comment>"));

            return;
        }

        $question = [
            sprintf(
                "\n  <question>%s</question>\n",
                'Do you want to add scripts to composer.json? (Y/n)'
            ),
            "  <info>It will add two scripts:</info>\n",
            "  - <info>cs-check</info>\n",
            "  - <info>cs-fix</info>\n",
            'Answer: ',
        ];

        $answer = $this->io->askConfirmation($question, true);

        if (! $answer) {
            return;
        }

        foreach ($scripts as $key => $command) {
            if (isset($this->composerDefinition['scripts'][$key]) && $this->composerDefinition['scripts'][$key] !== $command) {
                $this->io->write([
                    sprintf('  <error>Another script "%s" exists!</error>', $key),
                    "  If you want, you can replace it manually with:\n",
                    sprintf('  <comment>"%s": "%s"</comment>', $key, $command),
                ]);
                continue;
            }

            $this->composerDefinition['scripts'][$key] = $command;
        }

        $this->composerJson->write($this->composerDefinition);
    }

    public function createCSFile(array $finderPaths = [])
    {
        $finderPathsString = var_export($finderPaths, true);

        $contents = <<<FILE
<?php

/*
 * Additional rules or rules to override.
 * These rules will be added to default rules or will override them if the same key already exists.
 */
\$additionalRules = [];

\$config = PhpCsFixer\Config::create();
\$config->setRules(Facile\CodingStandards\Rules::getRules(\$additionalRules));

\$config->setUsingCache(false);
\$config->setRiskyAllowed(false);

\$finder = PhpCsFixer\Finder::create();
\$finder->in($finderPathsString);

\$config->setFinder(\$finder);

return \$config;

FILE;

        return $contents;
    }
}
