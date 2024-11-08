<?php

namespace Portrino\PxDbmigrator\Command;

use Portrino\PxDbmigrator\DirectoryIterator\SortableDirectoryIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MigrateCommand extends Command
{
    /**
     * @var array<string, mixed>
     */
    protected array $extConf = [];

    /**
     * @var string
     */
    protected string $sqlCommandTemplate = '%s --default-character-set=UTF8 -u"%s" -p"%s" -h "%s" -D "%s" -e "source %s" 2>&1';

    public function __construct(
        protected readonly ExtensionConfiguration $extensionConfiguration,
        protected readonly Registry $registry,
        ?string $name = null
    ) {
        parent::__construct($name);

        $this->extConf = $this->extensionConfiguration->get('px_dbmigrator');
    }

    protected function configure(): void
    {
        $this->setDescription(
            'Executes pending *.sh, *.sql and *.typo3cms migration files from the configured migrations directory.'
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->updateObsoleteRegistryNamespace();

        $pathFromConfig = Environment::getPublicPath() . DIRECTORY_SEPARATOR . $this->extConf['migrationFolderPath'];
        $migrationFolderPath = realpath($pathFromConfig);

        if ($migrationFolderPath === false) {
            GeneralUtility::mkdir_deep($pathFromConfig);
            $migrationFolderPath = realpath($pathFromConfig);
            if ($migrationFolderPath === false) {
                $io->writeln(
                    sprintf(
                        '<fg=red>Migration folder not found. Please make sure "%s" exists!</>',
                        htmlspecialchars($pathFromConfig, ENT_QUOTES)
                    )
                );
            }
            return 1;
        }

        $io->writeln(sprintf('Migration path: %s', $migrationFolderPath));
        $io->writeln('');

        $iterator = new SortableDirectoryIterator($migrationFolderPath);

        $highestExecutedVersion = 0;
        $errors = [];
        $executedFiles = 0;

        /** @var \SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            $fileVersion = (int)$fileInfo->getBasename('.' . $fileInfo->getExtension());

            if ($fileInfo->getType() !== 'file') {
                continue;
            }

            $migrationStatus = $this->registry->get(
                'PxDbmigrator',
                'migrationStatus:' . $fileInfo->getBasename(),
                ['tstamp' => null, 'success' => false]
            );

            if ($migrationStatus['success'] === true) {
                // already successfully executed
                continue;
            }

            $io->write(sprintf('Processing %s', $fileInfo->getBasename()));

            $migrationErrors = [];
            $migrationOutput = '';
            switch ($fileInfo->getExtension()) {
                case 'sql':
                    $success = $this->migrateSqlFile($fileInfo, $migrationErrors, $migrationOutput);
                    break;
                case 'typo3cms':
                    $success = $this->migrateTypo3CmsFile($fileInfo, $migrationErrors, $migrationOutput);
                    break;
                case 'sh':
                    $success = $this->migrateShellFile($fileInfo, $migrationErrors, $migrationOutput);
                    break;
                default:
                    // ignore other files
                    $success = true;
            }

            $io->writeln(' ' . ($success ? '<fg=green>OK</>' : '<fg=red>ERROR</>'));

            if ($migrationOutput) {
                $io->writeln(trim($migrationOutput));
            }

            // migration stops on the 1st erroneous file
            if (!$success || count($migrationErrors) > 0) {
                $errors[$fileInfo->getFilename()] = $migrationErrors;
                break;
            }

            $executedFiles++;
            $highestExecutedVersion = max($highestExecutedVersion, $fileVersion);

            $this->registry->set(
                'PxDbmigrator',
                'migrationStatus:' . $fileInfo->getBasename(),
                ['tstamp' => time(), 'success' => $success]
            );
        }

        $this->outputMessages($executedFiles, $errors, $io);

        return count($errors) === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @param \SplFileInfo $fileInfo
     * @param string[] $errors
     * @param string $output
     * @return bool
     */
    protected function migrateSqlFile(\SplFileInfo $fileInfo, array &$errors, string &$output): bool
    {
        $filePath = $fileInfo->getPathname();

        $shellCommand = sprintf(
            $this->sqlCommandTemplate,
            $this->extConf['mysqlBinaryPath'],
            $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user'],
            $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'],
            $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host'],
            $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname'],
            $filePath
        );

        if ($this->isExecutableWithinPath($this->extConf['mysqlBinaryPath']) === false) {
            $errors[] = 'MySQL binary not found or not executable. Please check your configuration.';
            return false;
        }

        $output .= shell_exec($shellCommand);

        $outputMessages = explode(PHP_EOL, $output);
        foreach ($outputMessages as $outputMessage) {
            if (trim($outputMessage) !== '' && str_contains($outputMessage, 'ERROR')) {
                $errors[] = $outputMessage;
            }
        }

        return count($errors) === 0;
    }

    /**
     * @param \SplFileInfo $fileInfo
     * @param string[] $errors
     * @param string $output
     * @return bool
     */
    protected function migrateTypo3CmsFile(\SplFileInfo $fileInfo, array &$errors, string &$output): bool
    {
        $migrationContent = file_get_contents($fileInfo->getPathname());
        if ($migrationContent === false) {
            $errors[] = 'Could not read file ' . $fileInfo->getFilename();
            return false;
        }
        foreach (explode(PHP_EOL, $migrationContent) as $line) {
            $line = trim($line);
            if ($line !== '' && !str_starts_with($line, '#')   && !str_starts_with($line, '//')) {
                $outputLines = [];
                $status = null;
                $shellCommand =
                    ($this->extConf['typo3cmsBinaryPath'] !== '' ? $this->extConf['typo3cmsBinaryPath'] : './vendor/bin/typo3cms')
                    . ' '
                    . $line
                    . ' 2>&1';

                chdir(Environment::getPublicPath());
                exec($shellCommand, $outputLines, $status);

                $output .= implode(PHP_EOL, $outputLines);
                if ($status !== 0) {
                    $errors[] = $output;
                    break;
                }
            }
        }
        return count($errors) === 0;
    }

    /**
     * @param \SplFileInfo $fileInfo
     * @param string[] $errors
     * @param string $output
     * @return bool
     */
    protected function migrateShellFile(\SplFileInfo $fileInfo, array &$errors, string &$output): bool
    {
        $command = '/usr/bin/env sh ' . $fileInfo->getPathname() . ' 2>&1';
        $outputLines = [];
        $status = null;

        chdir(Environment::getPublicPath());
        exec($command, $outputLines, $status);

        $output .= implode(PHP_EOL, $outputLines);
        if ($status !== 0) {
            $errors[] = $output;
        }
        return count($errors) === 0;
    }

    /**
     * @param int $executedFiles
     * @param array<string, string[]> $errors
     * @param SymfonyStyle $io
     */
    protected function outputMessages(int $executedFiles, array $errors, SymfonyStyle $io): void
    {
        if ($executedFiles === 0 && count($errors) === 0) {
            $io->writeln('Everything up to date. No migrations needed.');
        } else {
            if ($executedFiles > 0) {
                $io->writeln(
                    sprintf(
                        '<fg=green>Migration of %d file%s completed.</>',
                        $executedFiles,
                        ($executedFiles > 1 ? 's' : '')
                    )
                );
            } else {
                $io->writeln('<fg=red>Migration failed</>');
            }
            if (count($errors) > 0) {
                $io->writeln(sprintf('<fg=red>The following error%s occured:</>', (count($errors) > 1 ? 's' : '')));
                foreach ($errors as $filename => $error) {
                    $io->writeln(sprintf('File %s: ', $filename));
                    $io->writeln(sprintf('%s: ', implode(PHP_EOL, $error)));
                }
            }
        }
    }

    protected function updateObsoleteRegistryNamespace(): void
    {
        $result = (GeneralUtility::makeInstance(ConnectionPool::class))
                                ->getConnectionForTable('sys_registry')
                                ->count(
                                    'uid',
                                    'sys_registry',
                                    ['entry_namespace' => 'Appzap\\Migrator']
                                );
        if ($result > 0) {
            (GeneralUtility::makeInstance(ConnectionPool::class))
                          ->getConnectionForTable('sys_registry')
                          ->update(
                              'sys_registry',
                              ['entry_namespace' => 'PxDbmigrator'],
                              ['entry_namespace' => 'Appzap\\Migrator']
                          );
        }
    }

    protected function isExecutableWithinPath(string $filename): bool
    {
        if (is_executable($filename)) {
            return true;
        }

        if ($filename !== basename($filename)) {
            return false;
        }

        $env = getenv('PATH');
        if ($env !== false) {
            $paths = explode(PATH_SEPARATOR, $env);
            foreach ($paths as $path) {
                if (is_executable($path . DIRECTORY_SEPARATOR . $filename)) {
                    return true;
                }
            }
        }
        return false;
    }
}
