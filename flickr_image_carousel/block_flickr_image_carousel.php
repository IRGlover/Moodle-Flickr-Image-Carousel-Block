<?php 

/**
 * BLOCK: flickr_image_carousel
 * AUTHOR: Ian Glover (City University London, UK)
 * DESCRIPTION:
 * 
 * This block is used to display images from Flickr within Moodle. It requires a username to be entered, 
 * and can further filter the users photos by a set id or by tag. There is currently no authentication 
 * so only public photos can be retrieved and an API key needs to be added to the global config in order for
 * it to work. The block uses phpFlickr v3 (http://phpflickr.com/) which is included with the block code and 
 * utiises YUI for displaying the carousel - though it will show a single image if JS is unavailable.
 * 
 * Responses from the Flickr API are cached in a directory within the phpFlickr directory (though this could be 
 * changed to use a MySQL database if desired). The responses are cached for 7 days but this can be changed by 
 * editing the relevant method in the phpFlickr.php file.
 */
 
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


require_js(array('yui_yahoo', 'yui_dom', 'yui_event', 'yui_treeview', 'yui_element', 'yui_animation', 'yui_dom-event'));

//Maximum number of thumbnails to display - setting this higher means downloading more data (sets are not limited to this number)
define('MAX_THUMBNAILS', 50); 

//Number of items per request from Flickr - 500 is the maximum and so reduces the number of API requests sent.
define('BATCH_SIZE', 500); 

class block_flickr_image_carousel extends block_base {

    function init() {
		$this->title = "Flickr Image Carousel";
        global $CFG;
        require_once($CFG->dirroot.'/blocks/flickr_image_carousel/phpflickr-3.0/phpFlickr.php');
        $this->f = new phpFlickr("003209ed3354d7e5a0065c849984d86d");
    }

    function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        // if there is no user specified to get photos from  - show error
        if (empty($this->config->userid)) {
            $tagdata = 'No user specified, please configure block';
        // set id contains data, but it is not solely numbers - show error
        } else if (!empty($this->config->setid) && !(is_numeric($this->config->setid))) {
            $tagdata = 'Set ID should be a number';
        // all settings valid - produce html for carousel
        } else { 
            $tagdata = $this->generate_carousel_tag();
        }

        $this->content         =  new stdClass;
        $this->content->text   = $tagdata;
        $this->content->footer = null;
        return $this->content;
    }

    function specialization() {
      global $CFG;
        if (!empty($this->config->userid)) {
            $this->userid = $this->config->userid;
        } else {
            $this->config->userid = '';
        }
        if (!empty($this->config->title)) {
            $this->title = $this->config->title;
        } else {
            $this->config->title = '';
        }
        if (empty($this->config->setid)) {
            $this->config->setid = '';
        }
        if (empty($this->config->tag)) {
            $this->config->tag = '';
        }
      
      
      // Handle the 'Clear Cache' option by setting the cache time to -1 so that it will clear the file then
      // switch off the checkbox so that the next display creates the 7 day cache.
      if (isset($this->config->clearcache)) {
         $this->f->enableCache("fs", $CFG->dataroot.'/block_flickr_image_carousel/img-cache/', -1);
         unset($this->config->clearcache);
         $this->instance_config_save($this->config);
      } else {
           $this->f->enableCache("fs", $CFG->dataroot.'/block_flickr_image_carousel/img-cache/', 604800); // cache for 7 days
      }

    }

    function has_config() {
        return true;
    }

    function preferred_width() {
        return 230;
    }

    function instance_allow_config() {
        return true;
    }

    function hide_header()
    {
        if (isset($this->config->showtitle)) {
            return false;
        } else {
            return true;
        }
    }

   function instance_allow_multiple() {
      return true;
   }

    /**
     * This function requests the details of the photos within the set, based on the 
     * number of pages sent as a parameter, and merges them into a single array . 
     * It returns an array containing all of the details.
     *
     * @param integer $pages - number of pages to retrieve (total photos / BATCH_SIZE)
     * @return array         - merged array of all photo details for set
     */
    private function get_photos_from_set($pages) { 
        $photos = array();

        for ($i = 0; $i < $pages; $i++) {
            $photopage = $this->f->photosets_getPhotos($this->config->setid, null, null, BATCH_SIZE, $i);
            $photos = $this->concat($photos, (array)$photopage['photoset']['photo']);
        }
        return $photos;
    }
    

    /**
     * This function requests the details of the photos within a specified user's 
     * photostream and merges them into a single array. If the total number of photos is 
     * larger than MAX_THUMBNAILS, a random start point is created and <MAX_THUMBNAILS> 
     * photos after that are returned. It returns an array containing all of the details.
     *
     * @param string $userno - Flickr user id of the person
     * @param integer $pages - number of pages to retrieve (total photos / BATCH_SIZE)
     * @return array         - merged array of all photo details for set
     */
    private function get_photos_from_person($userno, $pages) {

        $photos = array();

        for ($i = 0; $i < $pages; $i++) {
            $photopage = $this->f->people_getPublicPhotos($userno, null, null, BATCH_SIZE, $i);
            $photos = $this->concat($photos, (array)$photopage['photos']['photo']);
        }

        $photoslength = sizeof($photos);

        if ($photoslength > MAX_THUMBNAILS) {
            $randomstartpoint = rand(0, ($photoslength - MAX_THUMBNAILS));
            $photos = array_slice($photos, $randomstartpoint, MAX_THUMBNAILS);
        }

        return $photos;
    }
    
    /**
     * This function retrieves the details of the photos within a specified user's 
     * photostream with a specified tag. If the total number of photos is larger than MAX_THUMBNAILS, a 
     * random start point is created and <MAX_THUMBNAILS> photos after that are returned
     * It returns an array containing all of the details.
     *
     * @param string $userno - Flickr user id of the person
     * @param integer $pages - number of pages to retrieve (total photos / BATCH_SIZE)
     * @return array         - merged array of all photo details for set
     */
    private function get_photos_from_person_with_tag($userno, $pages) {

        $photos = array();

        $tagArray = array(
            'user_id'=>$userno,
            'tags'=>$this->config->tag,
            'tag_mode'=>'any',
            'per_page'=> BATCH_SIZE
        );

        for ($i = 0; $i < $pages; $i++) {
            $photopage = $this->f->photos_search($tagArray);
            $photos = $this->concat($photos, (array)$photopage['photo']);
        }

        $photoslength = sizeof($photos);

        if ($photoslength > MAX_THUMBNAILS) {
            $randomstartpoint = rand(0, ($photoslength - MAX_THUMBNAILS));
            $photos = array_slice($photos, $randomstartpoint, MAX_THUMBNAILS);
        }

        return $photos;
    }


    /**
     * This function manages the retrieval of details for the photos within a specified set. 
     * It works out the number of requests required to Flickr and calls get_photos_from_set() 
     * to do the actual retrieval. It returns an array containing all of the details.
     *
     * @return array - array of photo details for batch
     */
    private function get_set_contents() {

        $setinfo = $this->f->photosets_getInfo($this->config->setid);
        $totalpics = $setinfo['photos'];

        if ($totalpics == 0) {
            return null;
        }

        $userno = $setinfo['owner'];
        $userbaseurl = $this->f->urls_getUserPhotos($userno);
        $pages = floor(($totalpics/BATCH_SIZE)+1);

        $photos = $this->get_photos_from_set($pages);

        return $photos;
    }
    
    
    /**
     * This function manages the retrieval of details for the photos with a specified tag in a 
     * user's photostream. It works out the number of requests required to Flickr and calls 
     * get_photos_from_person_with_tag() to do the actual retrieval. It returns an array containing 
     * all of the details.
     *
     * @param string $userno - Flickr user id of the person
     * @return array         - array of photo details for batch
     */
    private function get_tagged_images($userno) {

        $tagArray = array(
            'user_id'=>$userno,
            'tags'=>$this->config->tag,
            'tag_mode'=>'any',
            'per_page'=>'1'
        );
        $photos_info = $this->f->photos_search($tagArray);
        $totalpics = $photos_info['total'];

        if ($totalpics == 0) {
            return null;
        }

        $userbaseurl = $this->f->urls_getUserPhotos($userno);

        $pages = floor(($totalpics/BATCH_SIZE)+1);

        $photos = $this->get_photos_from_person_with_tag($userno, $pages);

        return $photos;
    }

    /**
     * This function manages the retrieval of details for the photos in a user's photostream. It 
     * works out the number of requests required to Flickr and calls get_photos_from_person() to 
     * do the actual retrieval. It returns an array containing all of the details.
     *
     * @param string $userno - Flickr user id of the person
     * @return array         - array of photo details for batch
     */
    private function get_users_photos($userno) {

        $userbaseurl = $this->f->urls_getUserPhotos($userno);

        $personinfo = $this->f->people_getInfo($userno);
        $totalpics = $personinfo['photos']['count'];

        if ($totalpics == 0) {
            return null;
        }

        $pages = floor(($totalpics/BATCH_SIZE)+1);
        $photos = $this->get_photos_from_person($userno, $pages);
        return $photos;

    }

    /**
     * This function manages creation of the html used to display the images. It selects the 
     * which retrieval function to call based upon what is set in the instance config, and passes
     * the resultant array to a function to produce the markup.
     *
     * @return string $tagdata - the data to be displayed in the block (either the image data, or 
     *                           error message
     */
    private function generate_carousel_tag() {
        
        if (!empty($this->config->userid) || empty($this->config->useridtype)) {
         if ( $this->config->useridtype == "1") {
               $userno = $this->get_userid_from_name();
         } else if ( $this->config->useridtype == "2") {
            $userno = $this->get_userid_from_email();
         } else if ( $this->config->useridtype == "3") {
            $userno = $this->config->userid;
         }
        } else {
            return 'Invalid user';
        }
        
        if (!isset($userno)) {
            return 'Invalid user';
        }

        // get photos from a set
        if (!empty($this->config->setid)) {
            $photos = $this->get_set_contents();
        // get photos with tag
        } else if (!empty($this->config->tag)) { 
            $photos = $this->get_tagged_images($userno);
        // get photostream items
        } else {
            $photos = $this->get_users_photos($userno);
        }

        // check whether an error was returned
        if (isset($photos)) {
            return $this->build_film_strip($photos, $userno);
        } else {
            return 'No images available with the current settings. Please reconfigure';
        }
    }


    /**
     * This function converts the specified username into the equivalent Flickr user id.
     *
     * @return string $userno - the Flickr user id
     */
    private function get_userid_from_name() {
        
        $userdata = $this->f->people_findByUsername($this->config->userid);
        if ($userdata == null) {
            return null;
        }
        $userno = $userdata['nsid'];

        return $userno;
    }
   
   /**
     * This function converts the specified email address into the equivalent Flickr user id.
     *
     * @return string $userno - the Flickr user id
     */
    private function get_userid_from_email() {
        
        $userdata = $this->f->people_findByEmail($this->config->userid);
        if ($userdata == null) {
            return null;
        }
        $userno = $userdata['nsid'];

        return $userno;
    }

    /**
     * This function generates the markup used to display the carousel. It uses the YUI carousel, but also 
     * displays a static image from the batch of photos if JavaScript is unavailable. the thumbnails are laid 
     * out as ordered list items and these are then processed by the javascript into the carousel. Each image 
     * has a link back to it's source on Flickr based on the title of the image (where there is one). The array
     * is imploded into a string and this is the return value.
     *
     * @param array $photos    - Flickr user id of the person
     * @param string $userno   - Flickr user id of the person
     * @return string $tagdata - the data to be displayed in the block (either the image data, or 
     *                           error message)
     */
    private function build_film_strip($photos, $userno) {
        global $CFG;
        $randsuffix = rand(0, 32000);

        $photoslist = array(				
         '<script type="text/javascript" src="'.$CFG->wwwroot.'/blocks/flickr_image_carousel/carousel/carousel-min.js"></script>  '.
            '<link rel="stylesheet" type="text/css" href="'.$CFG->wwwroot.'/blocks/flickr_image_carousel/carousel/carousel.css">'.
            '<script src="'.$CFG->wwwroot.'/blocks/flickr_image_carousel/flickr_image_carousel.js"></script>'.
            '<div id="flickr_image_carousel_container-'.$randsuffix.'" class="carousel_container yui-skin-sam js-disabled"><ol class="flickr_image_carousel">'
        );

        foreach ($photos as $photo) {
            array_push($photoslist, '<li><img src="'.$this->f->buildPhotoURL($photo, "Square").'" alt="<a href=\'http://www.flickr.com/photos/'.
                $userno.'/'.$photo['id'].'\' target=\'_blank\'>'.htmlentities($photo['title'], ENT_QUOTES).'</a>"/></li>');
        }
        unset ($photo);

        array_push($photoslist, '</ol></div><br /><div id="flickr_image_carousel_spotlight-'.$randsuffix.'" class="carousel_spotlight yui-skin-sam js-disabled "></div>');

        $randomindex = rand(0, sizeof($photos)-1);
        $photo = $photos[$randomindex];
        $phototitle = htmlentities($photo['title'], ENT_QUOTES);
        $photosurl = $this->f->urls_getUserPhotos($userno);

        array_push($photoslist, "<noscript><div id='carousel-noscript'><img border='0' alt='$phototitle' src='".
                                   $this->f->buildPhotoURL($photo, "Small")."'><br/><a href='$photosurl$photo[id]'><div ".
                                "class='title'>$phototitle</div></a></div></noscript>");

        $tagdata = implode(' ', $photoslist);
        unset($photoslist);

        return $tagdata;
    }

    /**
     * This function concatenates arrays and returns the resulting merged array.
     *
     * @return array - the merged array
     */
    private function concat() {
        $vars=func_get_args();
        $array=array();
        foreach ($vars as $var) {
            if (is_array($var)) {
                foreach ($var as $val) {
                    $array[]=$val;
                }
            } else {
                $array[]=$var;
            }
        }
        return $array;
    }
}