<?php

namespace LearnosityQti;

use Exception;
use LearnosityQti\Entities\Item\item;
use LearnosityQti\Exceptions\InvalidQtiException;
use LearnosityQti\Exceptions\MappingException;
use LearnosityQti\Processors\Learnosity\In\ItemMapper;
use LearnosityQti\Processors\Learnosity\In\QuestionMapper;
use LearnosityQti\Processors\QtiV2\Out\ItemWriter;
use LearnosityQti\Processors\QtiV2\Out\QuestionWriter;
use LearnosityQti\Services\LearnosityToQtiPreProcessingService;
use LearnosityQti\Services\LogService;
use LearnosityQti\Utils\StringUtil;
use qtism\data\storage\xml\XmlDocument;
use qtism\data\storage\xml\XmlStorageException;

class Converter
{
    const INPUT_FORMAT_QTIV2P1 = 'qtiv2p1';
    const OUTPUT_FORMAT_LRN_JSON = 'json';

    const LEARNOSITY_DATA_ITEM = 'item';
    const LEARNOSITY_DATA_QUESTION = 'question';
    const LEARNOSITY_DATA_QUESTION_DATA = 'questiondata';

    public static function convertQtiItemToLearnosity($xmlString, $baseAssetsUrl = '', $validate = true)
    {
        $itemMapper = AppContainer::getApplicationContainer()->get('qtiv2_item_mapper');
        $itemWriter = AppContainer::getApplicationContainer()->get('learnosity_item_writer');
        $questionWriter = AppContainer::getApplicationContainer()->get('learnosity_question_writer');
        $assetsProcessing = AppContainer::getApplicationContainer()->get('assets_processing');
        $assetsProcessing->setBaseAssetUrl($baseAssetsUrl);

        // Parse `em
        try {
            list($item, $questions, $exceptions) = $itemMapper->parse($xmlString, $validate);
        } catch (XmlStorageException $e) {
            // Check invalid schema error message and intercept to rethrow as known `InvalidQtiException` exception
            $exceptionMessage = $e->getMessage();
            if (StringUtil::startsWith($exceptionMessage, 'The document could not be validated with schema')) {
                $exceptionMessage = preg_replace('/The document could not be validated with schema(.*)/', 'The document could not be validated with standard QTI schema: ', $exceptionMessage);
                throw new InvalidQtiException($exceptionMessage);
            } else {
                throw $e;
            }
        }

        // Conversion to JSON
        $itemData = [];
        if ($item instanceof item) {
            $itemData = $itemWriter->convert($item);
        }

        $questionsData = [];
        if (is_array($questions)) {
            foreach ($questions as $question) {
                $questionsData[] = $questionWriter->convert($question);
            }
        }

        return [$itemData, $questionsData, $exceptions];
    }

    public static function convertLearnosityToQtiItem(array $data)
    {
        $jsonType = self::guessLearnosityJsonDataType($data);

        // Handle `item` which contains both a single item and one or more questions/features
        if ($jsonType === self::LEARNOSITY_DATA_ITEM) {
            list($xmlString, $messages) = self::convertLearnosityItem($data);
        // Handle if just question
        } else if ($jsonType === self::LEARNOSITY_DATA_QUESTION) {
            list($xmlString, $messages) = self::convertLearnosityQuestion($data);
        // Handle if just question data
        } else if ($jsonType === self::LEARNOSITY_DATA_QUESTION_DATA) {
            list($xmlString, $messages) = self::convertLearnosityQuestionData($data);
        } else {
            throw new \Exception('Unknown JSON format');
        }

        // Validate them before proceeding by feeding it back
        try {
            $document = new XmlDocument();
            $document->loadFromString($xmlString);
        } catch (\Exception $e) {
            LogService::log('Unknown error occurred. The QTI XML produced may not be valid');
        }

        return [$xmlString, $messages];
    }

    private static function convertLearnosityQuestion(array $questionJson)
    {
        $preprocessingService = new LearnosityToQtiPreProcessingService();
        $questionMapper = new QuestionMapper();
        $questionWriter = new QuestionWriter();

        $question = $questionMapper->parse($preprocessingService->processJson($questionJson));
        return $questionWriter->convert($question);
    }

    private static function convertLearnosityQuestionData(array $questionDataJson)
    {
        $preprocessingService = new LearnosityToQtiPreProcessingService();
        $questionMapper = new QuestionMapper();
        $questionWriter = new QuestionWriter();

        $question = $questionMapper->parseDataOnly($preprocessingService->processJson($questionDataJson));
        return $questionWriter->convert($question);
    }

    private static function convertLearnosityItem(array $itemJson)
    {
        // Separate question(s) and item
        $itemJson['questionReferences'] = array_column($itemJson['questions'], 'reference');
        $questionsJson = $itemJson['questions'];
        unset($itemJson['questions']);

        // Pre-process these JSON
        $preprocessingService = new LearnosityToQtiPreProcessingService($questionsJson);
        $questionsJson = $preprocessingService->processJson($questionsJson);
        $itemJson = $preprocessingService->processJson($itemJson);

        // Map those bad boys to Learnosity entities
        $itemMapper = new ItemMapper();
        $questionMapper = new QuestionMapper();
        $item = $itemMapper->parse($itemJson);
        $questions = [];
        foreach ($questionsJson as $question) {
            if (!in_array($question['data']['type'], ['audioplayer', 'videoplayer', 'sharedpassage'])) {
                $questions[] = $questionMapper->parse($question);
            }
        }

        // Write em` to QTI
        $itemWriter = new ItemWriter();
        return $itemWriter->convert($item, $questions);
    }

    private static function guessLearnosityJsonDataType(array $data)
    {
        if ($data == null) {
            throw new MappingException('Invalid JSON');
        }

        // Guess this JSON is an `item`
        if (!isset($data['type'])) {
            if (!isset($data['reference']) && !isset($data['content'])) {
                throw new MappingException('Invalid `item` JSON. Neither `reference` nor `content` shall not be empty');
            }
            return self::LEARNOSITY_DATA_ITEM;
        }

        // Guess this JSON is a `question`
        if (isset($data['data'])) {
            if (!isset($data['reference'])) {
                throw new MappingException('Invalid `item` JSON. Key `reference` shall not be empty');
            }
            return self::LEARNOSITY_DATA_QUESTION;
        }

        // Guess this JSON is a `questiondata`
        return self::LEARNOSITY_DATA_QUESTION_DATA;
    }
}
