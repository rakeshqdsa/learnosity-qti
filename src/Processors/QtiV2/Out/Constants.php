<?php

namespace LearnosityQti\Processors\QtiV2\Out;

class Constants
{
    public static $supportedQuestionTypes = [
        'mcq',
        'shorttext',
        'orderlist',
        'longtext',
        'longtextV2',
        'plaintext',
        'choicematrix',
        'tokenhighlight',
        'clozeassociation',
        'clozetext',
        'clozedropdown',
        'imageclozeassociation',
        'hotspot'
    ];

    /**
     * Note that only question types that maps to response declaration with `single` baseType would be able to be mapped with `mapping`.
     * Ignored for now :
     *  - mcq (can be both `single` and `multiple`, can only support when `single`)
     *  - tokenhighlight (can be both `single` and `multiple`, can only support when `single`)
     *  - clozedropdown
     */
    public static $questionTypesWithMappingSupport = [
        'shorttext',
        'clozetext',
    ];

    const RESPONSE_PROCESSING_TEMPLATE_MATCH_CORRECT = 'http://www.imsglobal.org/question/qti_v2p1/rptemplates/match_correct';
    const RESPONSE_PROCESSING_TEMPLATE_MAP_RESPONSE = 'http://www.imsglobal.org/question/qti_v2p1/rptemplates/map_response';
    const IMSQTI_TOOLNAME = 'SCIP ItemExport';
    const IMSQTI_TOOL_VERSION = '1.0';
    const IMSQTI_TOOL_VENDOR = 'Learnosity';
    const IMSQTI_TITLE = 'SCIP Item Bank Package';
    const IMSQTI_LANG = 'en';
    const IMSQTI_METADATA_SCHEMA = array('LOMv1.0','QTIv2.1');
    const IMSQTI_RIGHTS = 'Copyright (c) 2016 by Certica Solutions, Inc. All rights reserved.';
    
}
