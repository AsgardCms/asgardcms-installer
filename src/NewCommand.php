<?php namespace AsgardCms\Installer\Console;

use GuzzleHttp\Client;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZipArchive;

class NewCommand extends Command
{
    /**
     * Current version of AsgardCms
     * @var string
     */
    private $version = '1.0.2';

    /**
     * Configure the command options.
     */
    protected function configure()
    {
        $this->setName('new')
            ->setDescription('Create a new AsgardCms application.')
            ->addArgument('name', InputArgument::REQUIRED);
    }

    /**
     * Execute the command.
     * @param  InputInterface $input
     * @param  OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->verifyApplicationDoesntExist(
            $directory = getcwd() . '/' . $input->getArgument('name'),
            $output
        );
        $output->writeln('<info>Crafting application...</info>');
        $this->download($zipFile = $this->makeFilename())
            ->extract($zipFile, $directory)
            ->cleanUp($zipFile);
        $output->writeln('<comment>Application ready! Build something amazing.</comment>');
    }

    /**
     * Verify that the application does not already exist.
     * @param  string $directory
     * @return void
     */
    protected function verifyApplicationDoesntExist($directory, OutputInterface $output)
    {
        if (is_dir($directory)) {
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
    protected function download($zipFile)
    {
        $response = (new Client)->get("https://github.com/AsgardCms/Platform/archive/{$this->version}.zip");
        file_put_contents($zipFile, $response->getBody());

        return $this;
    }

    /**
     * Extract the zip file into the given directory.
     * @param  string $zipFile
     * @param  string $directory
     * @return $this
     */
    protected function extract($zipFile, $directory)
    {
        $archive = new ZipArchive;
        $archive->open($zipFile);
        $archive->extractTo($directory);
        $this->recursiveCopy("$directory/Platform-{$this->version}", $directory);
        $archive->close();

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
     * Recursivaly move the source to destination
     * @param string $source
     * @param string $destination
     */
    public function recursiveCopy($source, $destination)
    {
        $dir = opendir($source);
        @mkdir($destination);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($source . '/' . $file)) {
                    $this->recursiveCopy($source . '/' . $file, $destination . '/' . $file);
                } else {
                    copy($source . '/' . $file, $destination . '/' . $file);
                }
            }
        }
        closedir($dir);
    }
}
