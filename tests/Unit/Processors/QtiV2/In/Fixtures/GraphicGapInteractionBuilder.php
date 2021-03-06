<?php

namespace LearnosityQti\Tests\Unit\Processors\QtiV2\In\Fixtures;

use qtism\common\datatypes\QtiCoords;
use qtism\common\datatypes\QtiShape;
use qtism\data\content\interactions\AssociableHotspot;
use qtism\data\content\interactions\AssociableHotspotCollection;
use qtism\data\content\interactions\GapImg;
use qtism\data\content\interactions\GapImgCollection;
use qtism\data\content\interactions\GraphicGapMatchInteraction;
use qtism\data\content\xhtml\ObjectElement;

class GraphicGapInteractionBuilder
{
    public static function build($identifier, $bgObject, $gapImgs, $hotspots)
    {
        $gapImgCollection = new GapImgCollection();

        foreach ($gapImgs as $id => $data) {
            $obj = new ObjectElement($data, 'image/png');
            $gapImg = new GapImg($id, 1, $obj);
            $gapImgCollection->attach($gapImg);
        }

        $associableHotspotCollection = new AssociableHotspotCollection();
        foreach ($hotspots as $id => $data) {
            $coords = new QtiCoords(QtiShape::RECT, $data);
            $associableHotspot = new AssociableHotspot($id, 1, QtiShape::RECT, $coords);
            $associableHotspotCollection->attach($associableHotspot);
        }

        return new GraphicGapMatchInteraction(
            $identifier,
            $bgObject,
            $gapImgCollection,
            $associableHotspotCollection
        );
    }
}
