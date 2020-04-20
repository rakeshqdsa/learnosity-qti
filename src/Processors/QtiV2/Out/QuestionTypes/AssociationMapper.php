<?php

namespace LearnosityQti\Processors\QtiV2\Out\QuestionTypes;

use LearnosityQti\Entities\BaseQuestionType;
use LearnosityQti\Entities\QuestionTypes\association;
use LearnosityQti\Processors\QtiV2\Out\ContentCollectionBuilder;
use LearnosityQti\Processors\QtiV2\Out\Validation\AssociationValidationBuilder;
use LearnosityQti\Utils\QtiMarshallerUtil;
use qtism\data\content\FlowCollection;
use qtism\data\content\FeedbackInline;
use qtism\data\content\InlineCollection;
use qtism\data\content\BlockStaticCollection;
use qtism\data\content\interactions\AssociateInteraction;
use qtism\data\content\interactions\SimpleAssociableChoice;
use qtism\data\content\interactions\SimpleAssociableChoiceCollection;
use qtism\data\content\TextRun;
use qtism\data\content\xhtml\text\Div;

class AssociationMapper extends AbstractQuestionTypeMapper
{
    public function convert(BaseQuestionType $question, $interactionIdentifier, $interactionLabel)
    {
        /** @var association $question */

        $feedbackOptions = [];
        $metadata = $question->get_metadata();
        if (isset($metadata)) {
            if (!empty($metadata->get_distractor_rationale())) {             
                $feedbackOptions['general_feedback'] = $metadata->get_distractor_rationale();
            }
        }
        $possibleResponses = $question->get_group_possible_responses();
             //   var_dump($possibleResponses);die;
        foreach ($possibleResponses as $index => $possibleResponse){
            $gapIdentifier = 'POSSIBLE_RESPONSE_' . $index;
            $content = ContentCollectionBuilder::buildBlockStaticCollectionContent(QtiMarshallerUtil::unmarshallElement($possibleResponses));
            return $gapIdentifier;
        }
        $content = ContentCollectionBuilder::buildBlockStaticCollectionContent(QtiMarshallerUtil::unmarshallElement($possibleResponses));


        $associableChoices = new SimpleAssociableChoiceCollection();
        $stimulusLists = $question->get_stimulus_list();
        //var_dump($stimulusLists);die;
        $matchMax = $question->get_duplicate_responses() ? count($stimulusLists) : 1;
        foreach ($stimulusLists as $index => $stimulusList) {
            $associableChoice = new SimpleAssociableChoice('STIMULUS_LIST_' . $index, $matchMax);
            $associableChoice->setContent(ContentCollectionBuilder::buildFlowStaticCollectionContent(QtiMarshallerUtil::unmarshallElement($stimulusList)));
          //  $associableChoiceContent = new TextOrVariableCollection();
            //$associableChoiceContent->attach($associableChoice);
          //  $associableChoice->setContent($associableChoiceContent);
            $associableChoices->attach($associableChoice);
        }
        
        $interaction = new AssociateInteraction($interactionIdentifier, $associableChoices, $content);
     //var_dump($interaction);die;
        $interaction->setLabel($interactionLabel);
        $interaction->setPrompt($this->convertStimulusForPrompt($question->get_stimulus()));
        $interaction->setShuffle($question->get_shuffle_options() ? true : false);

        $builder = new AssociationValidationBuilder($possibleResponses);
        list($responseDeclaration, $responseProcessing) = $builder->buildValidation($interactionIdentifier, $question->get_validation(), 1, $feedbackOptions);

        return [$interaction, $responseDeclaration, $responseProcessing];
    }

}
