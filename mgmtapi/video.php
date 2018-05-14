<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of video
 *
 * @author vernick
 */
class Video {

    // Stores the key and an array of information about the video
    //
    public $key;
    public $metadata;
    public $stats;

    /*
     * Constructor
     */
    function __construct($id, $info) {
        $this->key = $id;
        $this->metadata = $info;
        $this->stats = [];
    }

}

/* A list of Videos
 */

class VideoList {

    private $list;

    /* Constructor.  Create the list as an array
    */
    function __construct() {
        $this->list = array();
    }

    public function Create($id, $info) {
        $asset = $this->Get($id);
        if ($asset != null) {
            return $asset;
        }
        $asset = new Video($id, $info);
        $this->list[$id] = $asset;  // Add video to the list
        return $asset;
    }

    public function AddName($id, $name) {
        if ($this->Get($id) == null) {
            return null;
        }
        $this->list[$id]->name = $name;
    }

    public function AddLabel($id, $label) {
        if ($this->Get($id) == null) {
            return null;
        }
        $this->list[$id]->label = $label;
    }

    public function Get($id) {
        if (!isset($this->list[$id])) {
            return null;
        }

        return $this->list[$id];
    }

    public function Count() {
        return count($this->list);
    }

    public function Reset() {
        reset($this->list);
    }

    public function Next() {
        $asset = current($this->list);
        next($this->list);
        return $asset;
    }

}
