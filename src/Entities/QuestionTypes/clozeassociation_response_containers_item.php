<?php

namespace LearnosityQti\Entities\QuestionTypes;

use LearnosityQti\Entities\BaseQuestionTypeAttribute;

/**
* This class is auto-generated based on Schemas API and you should not modify its content
* Metadata: {"responses":"v2.72.0","feedback":"v2.71.0","features":"v2.72.0"}
*/
class clozeassociation_response_containers_item extends BaseQuestionTypeAttribute {
    protected $height;
    protected $width;
    protected $wordwrap;
    
    public function __construct(
            )
    {
            }

    /**
    * Get Height \
    * The height of the cloze response containers including units. Example: "100px" \
    * @return string $height \
    */
    public function get_height() {
        return $this->height;
    }

    /**
    * Set Height \
    * The height of the cloze response containers including units. Example: "100px" \
    * @param string $height \
    */
    public function set_height ($height) {
        $this->height = $height;
    }

    /**
    * Get Width \
    * The width of the cloze response containers including units. Example: "100px" \
    * @return string $width \
    */
    public function get_width() {
        return $this->width;
    }

    /**
    * Set Width \
    * The width of the cloze response containers including units. Example: "100px" \
    * @param string $width \
    */
    public function set_width ($width) {
        $this->width = $width;
    }

    /**
    * Get Wordwrap \
    * Determines if the possible response text should wrap or show an ellipsis when placed in a response container. \
    * @return boolean $wordwrap \
    */
    public function get_wordwrap() {
        return $this->wordwrap;
    }

    /**
    * Set Wordwrap \
    * Determines if the possible response text should wrap or show an ellipsis when placed in a response container. \
    * @param boolean $wordwrap \
    */
    public function set_wordwrap ($wordwrap) {
        $this->wordwrap = $wordwrap;
    }

    
}

