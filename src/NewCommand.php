<?php namespace AsgardCms\Installer\Console;

use GuzzleHttp\Client;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
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
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $directory = $input->getArgument('name') ? getcwd() . '/' . $input->getArgument('name') : getcwd();
        if (! $input->getOption('force')) {
            $this->verifyApplicationDoesntExist($directory);
        }

        $output->writeln('<info>Crafting application...</info>');

        $this->download($zipFile = $this->makeFilename(), $directory)
            ->extract($zipFile, $directory)
            ->cleanUp($zipFile);
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
     * @return $this
     */
    protected function download($zipFile, $dir)
    {
        $client = new Client([
            'base_uri' => 'https://api.github.com',
            'timeout'  => 2.0,
        ]);

        $latestVersionUrl = $this->getLatestVersionUrl($client);

        $response = (new Client)->get($latestVersionUrl);
        file_put_contents($zipFile, $response->getBody());

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
}
