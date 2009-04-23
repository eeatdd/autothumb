<?php

$plugin_info = array(
						'pi_name'			=> 'Dd autothumb',
						'pi_version'		=> '0.1',
						'pi_author'			=> 'Steven Milne',
						'pi_author_url'		=> 'http://digitaldelivery.co.uk/',
						'pi_description'	=> 'Pulls an image from within existing html, saves thumbnail, returns inline or css placement code. Creates post thumbnails in a single step with no new custom fields',
						'pi_usage'			=> Dd_autothumb::usage()
					);
					
/**
 * Dd_autothumb Class
 *
 * @package		ExpressionEngine
 * @category		Plugin
 * @author			Steven Milne
 * @link			http://www.digitaldelivery.co.uk/downloads/dd_autothumb/
 */

class Dd_autothumb {

    var $return_data;

    
 /** ----------------------------------------
    /**  Pic Finder
    /** ----------------------------------------*/

    function Dd_autothumb($str = '' )
    {
        global $TMPL ;
		
		$picindex = 	( ! $TMPL->fetch_param('pic')) ? '0' :  $TMPL->fetch_param('pic');
		$thumbtype =   	$TMPL->fetch_param('thumbtype');
		$width =   		$TMPL->fetch_param('width');
		$height =   	$TMPL->fetch_param('height');
		
		// reset the width / height if nothing or garbage passed in
		if ( ! is_numeric($width))
			$width = 64;
		if ( ! is_numeric($height))
			$height = 64;
		
		// are we fetching the tag or just the url
		$fetchthis=1;
		if($fetch=="tag")
			$fetchthis=0;
		
		// grab the content to be processed and preg_match_all for <img tags
		// do this early so we know how many images we have
		// todo - remove dupes incase of bad use of bullets / gravatars etc...
        $str = $TMPL->tagdata;
		$pattern = "/<img[^']*?src=\"([^']*?)\"[^']*?>/"; 
		$match_count = preg_match_all($pattern, $str, $match_array);	    
		
		// first deal with the non numeric pic possibilities like first and last TODO add random
		if ( $picindex=="first")
			$picindex = 0;
		if ( $picindex=="last")
			$picindex = $match_count-1;
		// todo - add an 'all' option which returns all images thumbed	
		// any remaining non numerics are illegal, so switch to 0
		if ( ! is_numeric($picindex))
			$picindex = 0;
			
		// just in case we took a numeric larger than available we set to max
		if ( $picindex>($match_count-1))
			$picindex = $match_count-1;
		
		// now ready to try the actual thumbing
		if( ( $thm = $this->_resize($match_array[$fetchthis][$picindex], $width, $height, 90) ) !== FALSE)
		{
			if($thumbtype=="css_background") {
				 $this->return_data =  "style=\"background:url(".$thm['url'].") top left no-repeat\"" ;
		 	
			} else
			{
				$this->return_data =   "<img src=\"{$thm['url']}\" width=\"{$thm['width']}\" height=\"{$thm['height']}\" border=\"0\" />";
			}
		}
		else
		{
		// We have no thumbnail to share, so we return nothing
		$this->return_data ="";
		
		}		
}
	
	
	
	
/**
	* Resizes an image
	* 
	* Thumbnail code from David Renchers Image Sizer  -  http://www.lumis.com/site/page/imgsizer/
	* Loads more goodies in the original like remote (off domain) image processing
	* 
	* @param	$src		string		The image url
	* @param	$max_width	integer		The image max width
	* @param	$max_height	integer		The image max height
	* @param	$quality	integer		An interger between 1-100. Affects teh resized jpegs quality. 
	**/
	function _resize($src, $max_width = 9000, $max_height = 9000, $quality = 90)
	{
		global $FNS;

		$cachPath = "cache/";
		$debug = FALSE;
		$okmime = array("image/png","image/gif","image/jpeg");
		$cached = "";
		$crop = "";
		$proportional = FALSE;
		$cache = FALSE;

		if(empty($max_width) === TRUE)
		{
			$max_width = 9000;
		}

		if(empty($max_height) === TRUE)
		{
			$max_height = 9000;
		}

		/** -------------------------------------
		/* check if the src provided is a full URL
		/* and reset the src to the base path for the given URL
		/* some additions Thanks to Gonzalingui
		/** -------------------------------------*/	
		if (stristr($src, 'http'))
		{
			$url_src=$src; // save the URL src for remote?
			$urlarray = parse_url($src);
			$src = $urlarray['path'];
		}

		/** -------------------------------------
		/* get the images real path on the server 
		/* some additions Thanks to Gonzalingui
		/** -------------------------------------*/
		$img = pathinfo($src);
					
		$img_base = ( ! isset($img['dirname'])) ? '' : $img['dirname'];
		$img_basename = ( ! isset($img['basename'])) ? '' : $img['basename'];
		$img_extension = ( ! isset($img['extension'])) ? '' : $img['extension'];
		$img_filename = str_replace('.'.$img_extension, '', $img_basename);
		$img_filename = $FNS->filename_security($img_filename);
		
		$img_rootstep = (isset($_SERVER['DOCUMENT_ROOT'])) ? $_SERVER['DOCUMENT_ROOT'] : @getenv('DOCUMENT_ROOT');
		$img_rootstep = $img_rootstep."/".$img_base;

		// checks the path and removes // if the src path was entered as relitive 
		$img_rootpath = realpath($img_rootstep);
		$img_rootpath = $FNS->remove_double_slashes($img_rootpath);
			
		$img_fromroot = $img_rootpath."/".$img_filename.".".$img_extension;
		$img_fromroot = $FNS->remove_double_slashes($img_fromroot);

		$ourbug = "<pre>src = {$src}
img_base = {$img_base}
img_basename = {$img_basename}
img_extension = {$img_extension}
img_filename = {$img_filename}
img_rootstep = {$img_rootstep}
img_rootpath = {$img_rootpath}
img_fromroot = {$img_fromroot}</pre>";

		// can we actually read this file?
		if(!is_readable($img_fromroot))
		{		
			if($debug === TRUE)
			{
				return $ourbug.$img_fromroot." is not readable";
			}else{
				return FALSE;
			}
		}


		// end everything if the image is not there
		if (!is_file($img_fromroot))
		{
			if($debug === TRUE)
			{
				return $ourbug.$img_fromroot." is not a file";
			}else{
				return FALSE;
			}
		}

		if(!is_dir($img_rootpath."/".$cachPath.""))
		{
			// make the directory if we can 
			if (!mkdir($img_rootpath."/".$cachPath.""))
			{
				if($debug === TRUE)
				{
					return $ourbug.$img_rootpath."/".$cachPath." could not be created";
				}else{
					return FALSE;
				}
			}
			else
			{
				chmod($img_rootpath."/".$cachPath."", 0777);
			}

			// check if we can put files in the new directory 
			if (!is_writable($img_rootpath."/".$cachPath.""))
			{
				if($debug === TRUE)
				{
					return $ourbug.$img_rootpath."/".$cachPath." is not writable please chmod 777";
				}else{
					return FALSE;
				}
			}
		}

		/** -------------------------------------
		/* get src img sizes and mime type
		/** -------------------------------------*/
		$size = getimagesize($img_fromroot);
		$width = $size[0];
		$height = $size[1];
		$mime = $size['mime'];

		/** -------------------------------------
		/* lets stop this if the image is not in the ok mime types 
		/* evun if the file extension says it is the correct format
		/* prevents proessing if people try to rename a .bmp to .jpg etc.
		/* dont laugh it happens at my work all the time
		/** -------------------------------------*/
		if (!in_array($mime, $okmime))
		{
			if($debug)
			{
				return $ourbug."your image is not the correct type must be jpg,png or gif your image is ".$mime;
			}else{
				return FALSE;
			}
		}

		// get the ratio needed
		$x_ratio = $max_width / $width;
		$y_ratio = $max_height / $height;

		// if image already meets criteria, load current values in
		// if not, use ratios to load new size info
		if (($width <= $max_width) && ($height <= $max_height) ) {
			$out_width = $width;
			$out_height = $height;
		} else if (($x_ratio * $height) < $max_height) {
			$out_height = ceil($x_ratio * $height);
			$out_width = $max_width;
		} else {
			$out_width = ceil($y_ratio * $width);
			$out_height = $max_height;
		}

		// Do we want to Crop the image?
		if($max_height != '9000' && $max_width != '9000' && $max_width != $max_height)
		{
			$crop = "yes";
			$proportional = false;
			$out_width = $max_width;
			$out_height = $max_height;
		}

		// put all the output image and path togeather
		$resized = $img_rootpath.'/'.$cachPath.''.$img_filename.'-'.$out_width.'x'.$out_height.'.'.$img_extension;

		// check the sorce and the cache file 
		$imageModified = @filemtime($img_fromroot);
		$thumbModified = @filemtime($resized);

		// set a update flag for doing the work
		if ($imageModified > $thumbModified || $cache == "no")
		{
			$cached = "update";
		}

		$do_the_work = $this->do_some_image($img_fromroot, $out_width, $out_height, $proportional, $crop, $resized, $cached, $quality);

		if(!$do_the_work)
		{
			if($debug)
			{
				return $ourbug."Your Install of GD Experienced a failure";
			}else{
				return FALSE;
			}
		}

		$out['url'] = $FNS->remove_double_slashes("/".$img_base."/".$cachPath."".$img_filename.'-'.$out_width.'x'.$out_height.'.'.$img_extension);
		$out['width'] = $do_the_work['img_width'];
		$out['height'] = $do_the_work['img_height'];
		$out['original_size'] = $size;

		return $out;
	}
	
	
	

	// Resizing and Croping Function 
	// Some code courtesy Maxim Chernyak  http://mediumexposure.com/
	function do_some_image($file, $width = 0, $height = 0, $proportional = false, $crop = false, $output, $cached, $quality)
	{

		if ( $height <= 0 && $width <= 0 ) {
            return false;
        }


		$info = getimagesize($file);
				$image = '';


		$final_width = 0;
        $final_height = 0;
        list($width_old, $height_old) = $info;

        if ($proportional) 
		{
            if ($width == 0) $factor = $height/$height_old;
            elseif ($height == 0) $factor = $width/$width_old;
            else $factor = min ( $width / $width_old, $height / $height_old);
			$final_width = round ($width_old * $factor);
			$final_height = round ($height_old * $factor);
		}
		else 
		{
			$final_width = ( $width <= 0 ) ? $width_old : $width;
			$final_height = ( $height <= 0 ) ? $height_old : $height;
        }

		if ($crop)
		{

			$int_width = 0;
			$int_height = 0;

			$adjusted_height = $final_height;
			$adjusted_width = $final_width;

				$wm = $width_old/$width;
				$hm = $height_old/$height;
				$h_height = $height/2;
				$w_height = $width/2;

				$ratio = $width/$height;
				$old_img_ratio = $width_old/$height_old;

					if ($old_img_ratio > $ratio) 
					{
						$adjusted_width = $width_old / $hm;
						$half_width = $adjusted_width / 2;
						$int_width = $half_width - $w_height;
					} 
					else if($old_img_ratio <= $ratio) 
					{
						$adjusted_height = $height_old / $wm;
						$half_height = $adjusted_height / 2;
						$int_height = $half_height - $h_height;
					}
		}

		if($cached)
		{

			@ini_set("memory_limit","12M");
			@ini_set("memory_limit","16M");
			@ini_set("memory_limit","32M");
			@ini_set("memory_limit","64M");			

			switch ($info[2])
			{
				case IMAGETYPE_GIF:
					$image = imagecreatefromgif($file);
				break;
				case IMAGETYPE_JPEG:
					$image = imagecreatefromjpeg($file);
				break;
				case IMAGETYPE_PNG:
					$image = imagecreatefrompng($file);
				break;
				default:
					return false;
			}

			$image_resized = imagecreatetruecolor( $final_width, $final_height );

			if ( ($info[2] == IMAGETYPE_GIF) || ($info[2] == IMAGETYPE_PNG) )
			{
				$trnprt_indx = imagecolortransparent($image);

				// If we have a specific transparent color
				if ($trnprt_indx >= 0)
				{

					// Get the original image's transparent color's RGB values
					$trnprt_color    = imagecolorsforindex($image, $trnprt_indx);

					// Allocate the same color in the new image resource
					$trnprt_indx    = imagecolorallocate($image_resized, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);

					// Completely fill the background of the new image with allocated color.
					imagefill($image_resized, 0, 0, $trnprt_indx);

					// Set the background color for new image to transparent
					imagecolortransparent($image_resized, $trnprt_indx);


				}
				// Always make a transparent background color for PNGs that don't have one allocated already
				elseif ($info[2] == IMAGETYPE_PNG)
				{

					// Turn off transparency blending (temporarily)
					imagealphablending($image_resized, false);

					// Create a new transparent color for image
					$color = imagecolorallocatealpha($image_resized, 0, 0, 0, 127);

					// Completely fill the background of the new image with allocated color.
					imagefill($image_resized, 0, 0, $color);

					// Restore transparency blending
					imagesavealpha($image_resized, true);
				}
			}

			if ($crop) 
			{   
				imagecopyresampled($image_resized, $image, -$int_width, -$int_height, 0, 0, $adjusted_width, $adjusted_height, $width_old, $height_old);    
			}
			else
			{
				imagecopyresampled($image_resized, $image, 0, 0, 0, 0, $final_width, $final_height, $width_old, $height_old);
			}  

			switch ($info[2] ) {
				case IMAGETYPE_GIF:
					imagegif($image_resized, $output);
				break;
				case IMAGETYPE_JPEG:
					imagejpeg($image_resized, $output, $quality);

				break;
				case IMAGETYPE_PNG:
					imagepng($image_resized, $output);
				break;
				default:
					return false;
			}
		}		

		$out_sized = array (
			'img_width'	    =>	$final_width,
			'img_height'	=>  $final_height
		);

		return $out_sized;

	}


	
	
    /* END */
    
// ----------------------------------------
//  Plugin Usage
// ----------------------------------------

// This function describes how the plugin is used.
//  Make sure and use output buffering

function usage()
{
ob_start(); 
?>
Wrap the html containing your image(s) you want to thumbnail between the tag pairs. Dd_autothumb finds them, creates the thumbnails, saves them, and eturns the html or css to place them in a single step.

{exp:dd_autothumb pic="first" fetch="tag" thumbtype=="css_background" width="100" height="100"}
	text you want processed
{/exp:dd_autothumb}

Note:  
The "pic" parameter lets you specify which image to select. 'first', 'last' or 0,1,2,3...
The "tag" parameter lets you pick between a path, and the full <img.../> tag by using - path or tag
The "thumbtype" parameter lets you pick between an inline or a css background presentation simply - inline, cs_background
The "width" and height" parameters take an integer - in px
Thumbnailing won't create a bigger image than you start with even if you ask nicely - it'll just return the original image
Currently only works on images on your domain

Next version:
Add a pic="all" option to return all images from the post in thumbnail form to drive slideshows / lightboxes 

Note 2:
Early version, please email any bugs / issues to ee@digitaldelivery.co.uk

<?php
$buffer = ob_get_contents();
	
ob_end_clean(); 

return $buffer;
}
/* END */


}
// END CLASS
?>