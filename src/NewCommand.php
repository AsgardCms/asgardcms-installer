<?php namespace AsgardCms\Installer\Console;

use GuzzleHttp\Client;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use ZipArchive;

class NewCommand extends Command
{
    /**
     * Configure the command options.
     */
    protected function configure()
    {
        $this->setName('new')
            ->setDescription('Create a new AsgardCMS application.')
            ->addArgument('name', InputArgument::REQUIRED)
            ->addOption('force', null, InputOption::VALUE_NONE, 'Forces install even if the directory already exists');
    }

    /**
     * Execute the command.
     * @param  InputInterface $input
     * @param  OutputInterface $output
     * @return void
     * @throws \Symfony\Component\Process\Exception\LogicException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $directory = $input->getArgument('name') ? getcwd() . '/' . $input->getArgument('name') : getcwd();
        if (! $input->getOption('force')) {
            $this->verifyApplicationDoesntExist($directory);
        }

        $output->writeln('<info>Crafting application...</info>');

        $this->download($zipFile = $this->makeFilename(), $output)
            ->extract($zipFile, $directory)
            ->cleanUp($zipFile);

        $output->writeln('<comment></comment>');
        $composer = $this->findComposer();

        $commands = [
            $composer.' install --no-scripts',
            $composer.' run-script post-install-cmd',
        ];

        $process = new Process(implode(' && ', $commands), $directory, null, null, null);
        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            $process->setTty(true);
        }
        $process->run(function ($type, $line) use ($output) {
            $output->write($line);
        });

        $output->writeln('<comment>Application ready! Build something amazing.</comment>');
    }

    /**
     * Verify that the application does not already exist.
     * @param  string $directory
     * @return void
     * @throws \RuntimeException
     */
    protected function verifyApplicationDoesntExist($directory)
    {
        if ((is_dir($directory) || is_file($directory)) && $directory != getcwd()) {
            throw new RuntimeException('Application already exists!');
        }
    }

    /**
     * Generate a random temporary filename.
     * @return string
     */
    protected function makeFilename()
    {
        return getcwd() . '/asgardcms_' . md5(time() . uniqid()) . '.zip';
    }

    /**
     * Download the temporary Zip to the given file.
     * @param  string $zipFile
     * @param OutputInterface $output
     * @return $this
     */
    protected function download($zipFile, OutputInterface $output)
    {
        $client = new Client([
            'base_uri' => 'https://api.github.com',
            'timeout'  => 2.0,
        ]);

        $latestVersionUrl = $this->getLatestVersionUrl($client);

        $progress = new ProgressBar($output);
        $progress->setFormat('[%bar%] %elapsed:6s%');

        $response = (new Client)->get($latestVersionUrl, [
            'progress' => function($downloadTotal, $downloadedBytes, $uploadTotal, $uploadedBytes) use ($progress) {
                $progress->advance();
            },
        ]);
        file_put_contents($zipFile, $response->getBody());

        $progress->finish();

        return $this;
    }

    private function getLatestVersionUrl(Client $client)
    {
        $githubReleases = $client->get('repos/asgardcms/platform/releases/latest');

        $response = \GuzzleHttp\json_decode($githubReleases->getBody()->getContents());

        return $response->zipball_url;
    }

    /**
     * Extract the zip file into the given directory.
     * @param  string $zipFile
     * @param  string $directory
     * @return $this
     */
    protected function extract($zipFile, $directory)
    {
        $fs = new Filesystem();

        $fs->mkdir($directory);

        $archive = new ZipArchive;
        $archive->open($zipFile);
        $archive->extractTo($directory);
        $original = $directory . '/' . $archive->getNameIndex(0);

        $fs->mirror($original, $directory);
        $archive->close();

        $fs->remove($original);

        return $this;
    }

    /**
     * Clean-up the Zip file.
     * @param  string $zipFile
     * @return $this
     */
    protected function cleanUp($zipFile)
    {
        @chmod($zipFile, 0777);
        @unlink($zipFile);

        return $this;
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        if (file_exists(getcwd().'/composer.phar')) {
            return '"'.PHP_BINARY.'" composer.phar';
        }
        return 'composer';
    }
}
