<?php

/*
    Copyright 2010, 2011 Ian Glover
    

    This file is part of the Flickr Image Carousel (FIC) block for Moodle 2.x

    FIC is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    FIC is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with FIC.  If not, see <http://www.gnu.org/licenses/>.


*/


class block_flickr_image_carousel_edit_form extends block_edit_form {
	protected function specific_definition($mform) {
		// Section header title according to language file.
		$mform->addElement('header', 'configheader', "Flickr Image Carousel");
		
		// Title.
		$mform->addElement('html', 'Block Title:<br/>');
		$mform->addElement('text', 'config_title', "Title");
		$mform->setType('config_title', PARAM_MULTILANG);
		
		$mform->addElement('checkbox', 'config_showtitle', "Display Title?");
		
		$mform->addElement('html', '<br/>User Details:<br/>');
		
		// userid.
		$mform->addElement('text', 'config_userid', "Flickr User");
		$mform->setType('config_user', PARAM_MULTILANG);
		
		
		
		$mform->addElement('select', 'config_useridtype', "Type", array(1=>'Username',2=>'Email Address',3=>'Flickr User ID'));

		
$mform->addElement('html', '<br/>Image Selection (Leave blank to select from user\'s ENTIRE photostream):<br/>');		
		// setID.
		$mform->addElement('text', 'config_setid', "Set ID");
		$mform->setType('config_setid', PARAM_MULTILANG);
		
		// setID.
		$mform->addElement('text', 'config_tag', "Tag");
		$mform->setType('config_tag', PARAM_MULTILANG);
		
		$mform->addElement('html', '<br/>Check for New Images (Usually checks for images every 7 days):');
		$mform->addElement('checkbox', 'config_clearcache', "Check Now?");
		
		
	}
}



?>