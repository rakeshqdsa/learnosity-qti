<?php

namespace LearnosityQti\Services;

use LearnosityQti\Converter;
use LearnosityQti\Domain\JobDataTrait;
use LearnosityQti\Processors\QtiV2\Out\Constants as LearnosityExportConstant;
use LearnosityQti\Utils\General\FileSystemHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class ConvertToQtiService
{
    use JobDataTrait;

    const RESOURCE_TYPE_ITEM = 'imsqti_item_xmlv2p1';
    const INFO_OUTPUT_PREFIX = '';
    const CONVERT_LOG_FILENAME = 'convert:to:qti.log';

    protected $inputPath;
    protected $outputPath;
    protected $output;
    protected $organisationId;

    /* Runtime options */
    protected $dryRun                     = false;
    protected $shouldAppendLogs           = false;
    protected $shouldGuessItemScoringType = true;
    protected $shouldUseManifest          = true;
    /* Job-specific configurations */
    // Overrides identifiers to be the same as the filename
    protected $useFileNameAsIdentifier    = false;
    // Uses the identifier found in learning object metadata if available
    protected $useMetadataIdentifier      = true;
    // Resource identifiers sometimes (but not always) match the assessmentItem identifier, so this can be useful
    protected $useResourceIdentifier      = false;
    private $assetsFixer;

    public function __construct($inputPath, $outputPath, OutputInterface $output, $organisationId = null)
    {
        $this->inputPath      = $inputPath;
        $this->outputPath     = $outputPath;
        $this->output         = $output;
        $this->organisationId = $organisationId;
        $this->finalPath      = 'final';
        $this->logPath        = 'log';
        $this->rawPath        = 'raw';
    }

    public function process()
    {
        $errors = $this->validate();
        $result = [
            'status' => null,
            'message' => []
        ];

        if (!empty($errors)) {
            $result['status'] = false;
            $result['message'] = $errors;
            return $result;
        }

        // Setup output (or -o) subdirectories
        FileSystemHelper::createDirIfNotExists($this->outputPath . '/' . $this->finalPath);
        FileSystemHelper::createDirIfNotExists($this->outputPath . '/' . $this->logPath);
        FileSystemHelper::createDirIfNotExists($this->outputPath . '/' . $this->rawPath);

        $result = $this->parseContent();

        $this->tearDown();

        return $result;
    }

    /**
     * Performs a conversion on each directory (one level deep)
     * inside the given source directory.
     */
    private function parseContent()
    {
        $results = [];
        $jsonFiles = $this->parseInputFolders();
        $finalManifest = $this->getJobManifestTemplate();
        $this->output->writeln("<info>" . static::INFO_OUTPUT_PREFIX . "Processing JSON directory: {$this->inputPath} </info>");

        foreach ($jsonFiles as $file) {
            $tempDirectoryParts = explode('/', dirname($file));
            $fileName = $tempDirectoryParts[count($tempDirectoryParts)-1];
            $results[] = $this->convertLearnosityInDirectory($file);
        }

        $this->updateJobManifest($finalManifest, $results);
        $this->persistResultsFile($results, realpath($this->outputPath) . '/' . $this->rawPath . '/');
        $this->flushJobManifest($finalManifest);
    }

    /**
     * Performs a conversion on QTI content packages found in the given root source directory.
     *
     * @param  string $sourceDirectory
     * @param  string $relativeSourceDirectoryPath
     *
     * @return array - the results of the conversion
     */
    private function convertLearnosityInDirectory($file)
    {
        $this->output->writeln("<comment>Converting Learnosity JSON {$file}</comment>");
        return $this->convertAssessmentItem(json_decode(file_get_contents($file), true));
    }

    // Traverse the -i option and find all paths with files
    private function parseInputFolders()
    {
        $folders = [];

        // Look for json files in the current path
        $finder = new Finder();
        $finder->files()->in($this->inputPath . '/activities');
        foreach ($finder as $json) {
            $activityJson = json_decode(file_get_contents($json));
            $itemReferences = $activityJson->data->items;

            if (!empty($itemReferences)) {
                foreach ($itemReferences as $itemref) {
                    $itemFile = $this->inputPath . '/items/' . md5($itemref) . '.json';
                    if (file_exists($itemFile)) {
                        $folders[] = $itemFile;
                    }
                }
            } else {
                $this->output->writeln("<error>Error converting : No item refrences found in the activity json</error>");
            }
        }
        return $folders;
    }

    /**
     * Converts Learnosity JSON to QTI
     *
     * @param  string $jsonString
     *
     * @return array - the results of the conversion
     *
     * @throws \Exception - if the conversion fails
     */
    private function convertAssessmentItem($json)
    {
        $result = [];

        foreach ($json['questions'] as $question) {

            if (in_array($question['data']['type'], LearnosityExportConstant::$supportedQuestionTypes)) {
                $result = Converter::convertLearnosityToQtiItem($json);
            } else {
                $result = [
                    '',
                    [
                        'Ignoring' . $question['data']['type'] . ' , currently unsupported'
                    ]
                ];
                $this->output->writeln("<error>Question type `{$question['data']['type']}` not yet supported, ignoring</error>");
            }
        }
        return [
            'qti' => $result,
            'json' => $json
        ];
    }

    /**
     * Flush and write the given job manifest.
     *
     * @param array $manifest
     */
    private function flushJobManifest(array $manifest)
    {
        if ($this->dryRun) {
            return;
        }

        if ($this->shouldAppendLogs) {
            $manifestFileBasename = static::CONVERT_LOG_FILENAME . '_' . date('m-d-y-His');
        } else {
            $manifestFileBasename = static::CONVERT_LOG_FILENAME;
        }

        $this->output->writeln('<info>' . static::INFO_OUTPUT_PREFIX . 'Writing manifest: ' . $this->outputPath . '/' . $manifestFileBasename . '.json</info>');
        $this->writeJsonToFile($manifest, $this->outputPath . '/' . $this->logPath . '/' . $manifestFileBasename . '.json');
    }

    /**
     * Returns the base template for job manifests consumed by this job.
     *
     * @return array
     */
    private function getJobManifestTemplate()
    {
        return [];
    }

    /**
     * Writes a given results file to the specified output path.
     *
     * @param array  $results
     * @param string $outputFilePath
     */
    private function persistResultsFile(array $results, $outputFilePath)
    {
        if ($this->dryRun) {
            return;
        }
        $this->output->writeln("\n<info>" . static::INFO_OUTPUT_PREFIX . "Writing conversion results: " . $outputFilePath . '.json' . "</info>\n");

        foreach ($results as $result) {

            foreach ($result['json']['questions'] as $question) {
                if (!empty($result['qti'])) {
                    file_put_contents($outputFilePath . '/' . $question['reference'] . '.xml', $result['qti'][0]);
                }
            }
        }
    }

    /**
     * Updates a given job manifest in place with the contents of a specified
     * job partial result object.
     *
     * @param array $manifest - the job manifest to update
     * @param array $results  - the partial job result object to read
     */
    private function updateJobManifest(array &$manifest, array $results)
    {
        foreach ($results as $result) {
            if (!empty($result['qti'][1])) {
                $manifest[] = [
                    $result['json']['reference'] => $result['qti'][1]
                ];
            }
        }
    }

    private function tearDown()
    {
    }

    private function validate()
    {
        $errors = [];
        $jsonFolders = $this->parseInputFolders();

        if (empty($jsonFolders)) {
            array_push($errors, 'No files found in ' . $this->inputPath);
        }

        return $errors;
    }
}
