<?php


namespace Learnosity\Tests\Integration\Mappers\QtiV2\Interactions;

use Learnosity\AppContainer;
use Learnosity\Entities\Item\item;
use Learnosity\Entities\QuestionTypes\imageclozeassociation;
use Learnosity\Entities\QuestionTypes\imageclozeassociation_image;
use Learnosity\Utils\FileSystemUtil;

class GraphicGapMatchInteractionTest extends \PHPUnit_Framework_TestCase
{

    private function getFixtureFile($filepath)
    {
        return FileSystemUtil::readFile(FileSystemUtil::getRootPath() . '/src/Tests/Fixtures/' . $filepath)->getContents();
    }

    public function testMapResponseBasic()
    {
        $mapper = AppContainer::getApplicationContainer()->get('qtiv2_item_mapper');
        list($item, $questions, $exceptions) = $mapper->parse($this->getFixtureFile('interactions/graphic_gap_match.xml'));

        /** @var item $item */
        $this->assertNotNull($item);
        $this->assertInstanceOf('Learnosity\Entities\Item\item', $item);
        $this->assertEquals('graphicGapfill', $item->get_reference());
        $this->assertEquals('<p>Test intro</p><span class="learnosity-response question-graphicGapfill_RESPONSE"></span>', $item->get_content());
        $this->assertEquals('published', $item->get_status());
        $this->assertEquals('Airport Tags', $item->get_description());
        $this->assertEquals(['graphicGapfill_RESPONSE'], $item->get_questionReferences());

        $this->assertCount(1, $questions);
        $this->assertEquals('graphicGapfill_RESPONSE', $questions[0]->get_reference());
        $this->assertEquals('imageclozeassociation', $questions[0]->get_type());
        /** @var imageclozeassociation $question */
        $question = $questions[0]->get_data();
        $this->assertEquals([
            '<img src="images/CBG.png"/>',
            '<img src="images/EBG.png"/>',
            '<img src="images/EDI.png"/>',
            '<img src="images/GLA.png"/>',
            '<img src="images/MAN.png"/>',
            '<img src="images/MCH.png"/>'
        ], $question->get_possible_responses());
        $this->assertFalse($question->get_duplicate_responses());
        /** @var imageclozeassociation_image $img */
        $img = $question->get_image();
        $this->assertInstanceOf('Learnosity\Entities\QuestionTypes\imageclozeassociation_image', $img);
        $this->assertEquals('images/ukairtags.png', $img->get_src());
        $this->assertEquals(
            [
                ['x' => 5.8252, 'y' => 38.5714],
                ['x' => 62.1359, 'y' => 36.7857],
                ['x' => 32.0388, 'y' => 58.9286],
            ],
            $question->get_response_positions()
        );

        $validation = $question->get_validation();
        $this->assertNotNull($validation);
        $this->assertEquals('exactMatch', $validation->get_scoring_type());
        $this->assertEquals(3, $validation->get_valid_response()->get_score());
        $this->assertEquals(['<img src="images/GLA.png"/>', '<img src="images/EDI.png"/>', '<img src="images/MAN.png"/>'], $validation->get_valid_response()->get_value());

        $this->assertNull($validation->get_alt_responses());

        $this->assertCount(1, $exceptions);
    }
}
