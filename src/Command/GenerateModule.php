<?php

namespace AllekslarModuleGenerator\Command;

use function Symfony\Component\String\u;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\String\UnicodeString;
use Symfony\Component\Finder\SplFileInfo;

class GenerateModule extends Command
{
    public static $defaultName = 'allekslar:generate:module';
    protected static $defaultDescription = 'Generates Administration Module Structure';

    private array $plugins;
    private string $moduleName;
    private string $pluginName;
    private array $moduleFolderName;
    private $io;
    private $fileSystem;

    public function __construct(Filesystem $fileSystem, array $plugins)
    {
        $this->plugins = $plugins;
        $this->fileSystem = $fileSystem;
        parent::__construct();
    }

    /**
     * Configures the current command.
     */
    protected function configure(): void
    {

        $this
            ->addArgument('pluginName', InputArgument::OPTIONAL, 'Plugin Name')
            ->addArgument('moduleName', InputArgument::OPTIONAL, 'The name of the module.');
    }


    /**
     * Initializes the command after the input has been bound and before the input
     * is validated.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * Interacts with the user.
     *
     * interactively ask for values of missing required arguments.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $output->writeln([
            '',
            '=========================================',
            '<comment>Generates Administration Module Structure</comment>',
            '=========================================',
            '',
        ]);

        $pluginName = $input->getArgument('pluginName');

        if (!$pluginName) {
            $pluginName = $this->io->ask('Please enter a plugin name: ');
        }
        $this->pluginName = ucfirst($pluginName);

        $pluginPath = $this->getPluginPath($this->pluginName);

        $moduleName = $input->getArgument('moduleName');

        if (!$moduleName) {
            $moduleName = $this->io->ask('Please enter a module name: ');
        }
        $this->moduleName = strtolower($moduleName);

        $this->moduleFolderPath = "{$pluginPath}/Resources/app/administration/src/module/{$this->moduleName}/";

        $question = new ChoiceQuestion(
            'Please select module subfolder structure <fg=yellow;>defaults:</>',
            ['page', 'snippet', 'component', 'acl', 'view', 'service', 'mixin'],
            '0,1'
        );
        $question->setMultiselect(true);
        $this->moduleFolderName = $this->io->askQuestion($question);

        $output->writeln([
            '<fg=green;>You have just selected: </><fg=blue;>' . implode(', ', $this->moduleFolderName) . '</>',
            '=========================================',
            '',
        ]);
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int 0 if everything went fine, or an exit code
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->buildModule();

        return Command::SUCCESS;
    }

    /**
     * Build the Module.
     *
     * @return void
     * @throws \ReflectionException
     */
    private function buildModule(): void
    {
        $pluginPath = $this->getPluginPath($this->pluginName);

        $this->createMainJs($pluginPath);

        $this->setModuleFolderStructure($pluginPath);

        $this->buildModuleFiles();
    }

    /**
     * @param string $name
     * @return string
     * @throws \ReflectionException
     */
    private function getPluginPath(string $name): string
    {
        foreach ($this->plugins as $plugin) {
            if ($plugin['name'] !== $name) {
                continue;
            }

            $reflection = new \ReflectionClass($plugin['baseClass']);

            return dirname($reflection->getFileName());
        }

        throw new \RuntimeException(sprintf('Cannot find plugin by name "%s"', $name));
    }

    /**
     *
     * @param string $pluginPath
     * @return void
     */
    private function createMainJs(string $pluginPath): void
    {
        $moduleParentFolderPath = "{$pluginPath}/Resources/app/administration/src/";

        if (!$this->fileSystem->exists($moduleParentFolderPath . 'main.js')) {
            $this->fileSystem->dumpFile($moduleParentFolderPath . 'main.js', null);
        }
    }

    /**
     *
     * @param string $pluginPath
     * @return void
     */
    private function setModuleFolderStructure(string $pluginPath): void
    {

        $moduleFolderPath = "{$pluginPath}/Resources/app/administration/src/module/{$this->moduleName}/";

        $this->createDefaultSubfolderStructure($moduleFolderPath);
        $this->createComponentSubfolderStructure($moduleFolderPath);
        $this->createSubfolderStructure($moduleFolderPath, 'acl');
        $this->createSubfolderStructure($moduleFolderPath, 'view');
        $this->createSubfolderStructure($moduleFolderPath, 'service');
        $this->createSubfolderStructure($moduleFolderPath, 'mixin');
    }

    /**
     * Build the Module files.
     *
     * @return void
     */
    private function buildModuleFiles(): void
    {
        $finder = new Finder();

        $finder->files()->in(__DIR__ . '/../Resources/templates/module/');

        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $fileContent = file_get_contents($file->getPathname());
                $fileContent = $this->setTemplateVariables($fileContent);
                $this->createModuleFiles($file, $fileContent);
            }
        }
    }

    /**
     *
     * @param string $moduleFolderPath
     * @return void
     */
    private function createDefaultSubfolderStructure(string $moduleFolderPath): void
    {
        if (!$this->fileSystem->exists($moduleFolderPath)) {
            $this->fileSystem->mkdir([
                $moduleFolderPath . 'page/' . $this->moduleName . '-list/',
                $moduleFolderPath . 'page/' . $this->moduleName . '-detail/',
                $moduleFolderPath . 'page/' . $this->moduleName . '-create/',
                $moduleFolderPath . 'snippet/',
            ]);
        }
    }

    /**
     *
     * @param string $moduleFolderPath
     * @return void
     */
    private function createComponentSubfolderStructure(string $moduleFolderPath): void
    {
        if (
            !$this->fileSystem->exists("{$moduleFolderPath}component/{$this->moduleName}-action/")
            && in_array("component", $this->moduleFolderName)
        ) {
            $this->fileSystem->mkdir([
                "{$moduleFolderPath}component/{$this->moduleName}-action/",
            ]);
        }
    }

    /**
     *
     * @param string $moduleFolderPath
     * @param string $subfolder
     * @return void
     */
    private function createSubfolderStructure(string $moduleFolderPath, string $subfolder): void
    {
        if (
            !$this->fileSystem->exists("{$moduleFolderPath}{$subfolder}/")
            && in_array($subfolder, $this->moduleFolderName)
        ) {
            $this->fileSystem->mkdir([
                "{$moduleFolderPath}{$subfolder}/",
            ]);
        }
    }

    /**
     *
     * @param string $fileContent
     * @return string
     */
    private function setTemplateVariables(string $fileContent): string
    {

        $unicodeString = new UnicodeString($this->moduleName);
        // Convert element-name to element.name
        $routeName = u($this->moduleName)->replace('-', '.');

        $fileContent = str_replace('{{ name }}', $this->moduleName, $fileContent);
        $fileContent = str_replace('{{ routeName }}', $routeName, $fileContent);
        $fileContent = str_replace('{{ labelName }}', $unicodeString->camel(), $fileContent);
        $fileContent = str_replace('{{ twigModule }}', $unicodeString->snake(), $fileContent);
        return $fileContent;
    }

    /**
     *
     * @param SplFileInfo $file
     * @param string $fileContent
     * @return void
     */
    private function createModuleFiles(SplFileInfo $file, string $fileContent): void
    {
        if (!$this->fileSystem->exists("{$this->moduleFolderPath}index.js")) {
            if (strpos($file->getFilename(), 'base')) {
                file_put_contents("{$this->moduleFolderPath}index.js", $fileContent);
            }
        }
        $this->createSnippetFiles($file, $fileContent, 'en-GB');

        $this->createModulePageFiles($file, $fileContent, 'list');
        $this->createModulePageFiles($file, $fileContent, 'detail');
        $this->createModulePageFiles($file, $fileContent, 'create');

        $this->createComponentFiles($file, $fileContent);
        $this->createFiles($file, $fileContent, 'acl');
        $this->createFiles($file, $fileContent, 'mixin');
        $this->createFiles($file, $fileContent, 'service');
        $this->createFiles($file, $fileContent, 'view');
    }

    /**
     *
     * @param SplFileInfo $file
     * @param string $fileContent
     * @param string $language
     * @return void
     */
    private function createSnippetFiles(SplFileInfo $file, string $fileContent, string $language): void
    {
        if (!$this->fileSystem->exists("{$this->moduleFolderPath}snippet/{$language}.json")) {
            if (strpos($file->getFilename(), 'snippet')) {
                file_put_contents("{$this->moduleFolderPath}snippet/{$language}.json", $fileContent);
            }
        }
    }
    
    /**
     *
     * @param SplFileInfo $file
     * @param string $fileContent
     * @param string $type
     * @return void
     */
    private function createModulePageFiles(SplFileInfo $file, string $fileContent, string $type): void
    {
        if (!$this->fileSystem->exists("{$this->moduleFolderPath}page/{$this->moduleName}-{$type}/index.js")) {
            if (strpos($file->getFilename(), "page-{$type}")) {
                file_put_contents("{$this->moduleFolderPath}page/{$this->moduleName}-{$type}/index.js", $fileContent);
            }
        }
        if (!$this->fileSystem->exists("{$this->moduleFolderPath}page/{$this->moduleName}-${type}/{$this->moduleName}-{$type}.html.twig")) {
            if (strpos($file->getFilename(), "page_{$type}_twig")) {
                file_put_contents("{$this->moduleFolderPath}page/{$this->moduleName}-${type}/{$this->moduleName}-{$type}.html.twig", $fileContent);
            }
        }
        if (!$this->fileSystem->exists("{$this->moduleFolderPath}page/{$this->moduleName}-{$type}/{$this->moduleName}-{$type}.scss")) {
            if (strpos($file->getFilename(), "page_{$type}_scss")) {
                file_put_contents("{$this->moduleFolderPath}page/{$this->moduleName}-{$type}/{$this->moduleName}-{$type}.scss", $fileContent);
            }
        }
    }

    /**
     *
     * @param SplFileInfo $file
     * @param string $fileContent
     * @return void
     */
    private function createComponentFiles(SplFileInfo $file, string $fileContent): void
    {
        if ($this->fileSystem->exists("{$this->moduleFolderPath}component/{$this->moduleName}-action/")) {
            if (!$this->fileSystem->exists("{$this->moduleFolderPath}component/{$this->moduleName}-action/index.js")) {
                if (strpos($file->getFilename(), 'component_index')) {
                    file_put_contents("{$this->moduleFolderPath}component/{$this->moduleName}-action/index.js", $fileContent);
                }
            }
            if (!$this->fileSystem->exists("{$this->moduleFolderPath}component/{$this->moduleName}-action/{$this->moduleName}-action.html.twig")) {
                if (strpos($file->getFilename(), 'component_action_twig')) {
                    file_put_contents("{$this->moduleFolderPath}component/{$this->moduleName}-action/{$this->moduleName}-action.html.twig", $fileContent);
                }
            }
            if (!$this->fileSystem->exists("{$this->moduleFolderPath}component/{$this->moduleName}-action/{$this->moduleName}-action.scss")) {
                if (strpos($file->getFilename(), 'component_action_scss')) {
                    file_put_contents("{$this->moduleFolderPath}component/{$this->moduleName}-action/{$this->moduleName}-action.scss", $fileContent);
                }
            }
        }
    }

    /**
     *
     * @param SplFileInfo $file
     * @param string $fileContent
     * @param string $type
     * @return void
     */
    private function createFiles(SplFileInfo $file, string $fileContent, string $type): void
    {
        if ($this->fileSystem->exists("{$this->moduleFolderPath}{$type}/")) {
            if (!$this->fileSystem->exists("{$this->moduleFolderPath}{$type}/index.js")) {
                if (strpos($file->getFilename(), "{$type}_index")) {
                    file_put_contents("{$this->moduleFolderPath}{$type}/index.js", $fileContent);
                }
            }
        }
    }
}
