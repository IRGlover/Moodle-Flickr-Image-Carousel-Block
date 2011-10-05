<?php

function xmldb_block_flickr_image_carousel_install() {
	global $CFG;
		
	mkdir($CFG->dataroot.'/block_flickr_image_carousel/img-cache', 0777, true);
}

