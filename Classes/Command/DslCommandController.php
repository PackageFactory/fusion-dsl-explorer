<?php
namespace PackageFactory\FusionDslExplorer\Command;

/*                                                                             *
 * This script belongs to the Neos package "Packagefactory.FusionDslExplorer". *
 *                                                                             *
 *                                                                             */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Package\PackageManagerInterface;
use Neos\Utility\Files;
use SebastianBergmann\Diff\Differ;


use Neos\Fusion\Core\DslFactory;

/**
 * @Flow\Scope("singleton")
 */
class DslCommandController extends CommandController
{

    /**
     * @Flow\InjectConfiguration(package="Neos.Fusion", path="dsl")
     * @var
     */
    protected $dslSettings;

    /**
     * @var PackageManagerInterface
     * @Flow\Inject
     */
    protected $packageManager;

    /**
     * @var DslFactory
     * @Flow\Inject
     */
    protected $DslFactory;

    /**
     * Expression to detect dsl segments in fusion code
     */
    const PATTERN_DSL_EXPRESSION = '/(?P<identifier>[a-zA-Z0-9\.]+)`(?P<code>[^`]*)`/';

    /**
     * Expand dsl-code to pure fusion, this can be usefull before
     * removing a dsl package.
     *
     * @param string $dsl the dsl identifier that shall be ejected
     * @param string $packageKey the key of the fusion file package
     * @param string $fusionFile the fusion file to simulate
     * @param boolean $noDiff do not show the diff result
     * @return void
     */
    public function ejectCommand($dsl, $packageKey = NULL, $fusionFile = NULL, $noDiff = false)
    {
        $this->ejectOrSimulate($dsl, $packageKey, $fusionFile, FALSE, $noDiff);
    }

    /**
     * Simulate the dsl expansion to pure fusion for a given fusion file.
     *
     * @param string $dsl the dsl identifier that shall be simulated
     * @param string $packageKey the key of the fusion file package
     * @param string $fusionFile the fusion file to simulate
     * @param boolean $noDiff do not show the diff result but the whole file
     * @return void
     */
    public function simulateCommand($dsl, $packageKey = NULL, $fusionFile = NULL, $noDiff = false)
    {
        $this->ejectOrSimulate($dsl, $packageKey, $fusionFile, TRUE, $noDiff);
    }

    /**
     * Execute the command and decide wether to show or to store the result via simulate flag
     *
     * @param string $dsl the dsl identifier that shall be ejected
     * @param string $packageKey the key of the fusion file package
     * @param string $fusionFile the fusion file to simulate
     * @param boolean $simulate only show the transpilation without storing
     * @param boolean $noDiff do not show the diff result
     * @return void
     */
    protected function ejectOrSimulate($dsl, $packageKey = NULL, $fusionFile = NULL, $simulate = TRUE, $noDiff = FALSE)
    {
        $fusionFilesToProcesss = [];

        if (!array_key_exists($dsl, $this->dslSettings)){
            $this->outputLine('No Fusion-DSL with identifier "%s" was found', [$dsl]);
            $this->quit(1);
        }

        if ((!$packageKey && !$fusionFile) || ($packageKey && $fusionFile)) {
            $this->outputLine('You have to specify either a packageKey or a fusionFile');
            $this->quit(1);
        }

        if ($packageKey) {
            if ($this->packageManager->isPackageAvailable($packageKey)) {
                $fusionFilesToProcesss = $this->getFusionFilesForPackage($packageKey);
            } else {
                $this->outputLine('Package %s is not available', [$packageKey]);
                $this->quit(1);
            }
        }

        if ($fusionFile) {
            if (file_exists($fusionFile) && is_file($fusionFile)) {
                $fusionFilesToProcesss = [$fusionFile];
            } else {
                $this->outputLine('File %s is not available', [$fusionFile]);
                $this->quit(1);
            }
        }

        if ($simulate == FALSE) {
            $this->outputLine('This command will expand all %s`...` expressions', [$dsl]);
            $this->outputLine("Are you sure you want to do this?  Type 'yes' to continue: ");
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            if (trim($line) != 'yes') {
                $this->outputLine('exit');
                $this->quit(1);
            }
        }

        foreach ($fusionFilesToProcesss as $fusionFile) {
            $fusionCode = file_get_contents($fusionFile);
            $fusionCodeProcessed = preg_replace_callback(
                self::PATTERN_DSL_EXPRESSION,
                function($matches) use ($dsl) {
                    if ($matches['identifier'] !== $dsl) {
                        return $matches[0];
                    }
                    $dslImplementation = $this->DslFactory->create($matches['identifier']);
                    return $dslImplementation->transpile($matches['code']);
                },
                $fusionCode
            );

            if ($fusionCode !== $fusionCodeProcessed) {
                if ($simulate) {
                    $this->outputLine();
                    $this->outputLine('Simulate Fusion-DSL "%s" for file %s', [$dsl, $fusionFile]);
                    $this->outputLine();
                    if ($noDiff) {
                        $this->output($fusionCodeProcessed);
                    } else {
                        $differ = new Differ($header = "--- Original\n+++ Transpiled\n", $showNonDiffLines = false);
                        $this->output($differ->diff($fusionCode, $fusionCodeProcessed));
                    }
                } else {
                    file_put_contents($fusionFile, $fusionCodeProcessed);
                    $this->outputLine();
                    $this->outputLine('Ejected Fusion-DSL "%s" from file %s', [$dsl, $fusionFile]);
                    if ($noDiff == FALSE) {
                        $this->outputLine();
                        $differ = new Differ($header = "--- Original\n+++ Transpiled\n", $showNonDiffLines = false);
                        $this->output($differ->diff($fusionCode, $fusionCodeProcessed));
                    }
                }
            }
        }
    }


    /**
     * @param $packageKey
     * @return array
     */
    protected function getFusionFilesForPackage($packageKey)
    {
        $package = $this->packageManager->getPackage($packageKey);
        $packageFusionPath = $package->getResourcesPath() . 'Private/Fusion';
        if (!file_exists($packageFusionPath)) {
            $this->outputLine('Fusion path %s is not found', [$packageFusionPath]);
            $this->quit(1);
        }
        $fusionFilesToProcesss = Files::readDirectoryRecursively($packageFusionPath, 'fusion');
        return $fusionFilesToProcesss;
    }
}
