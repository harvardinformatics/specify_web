<?php
/*
 * Created on 2010 May 13
 *
 * Copyright © 2010 President and Fellows of Harvard College
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * 
 * @Author: Paul J. Morris  bdim@oeb.harvard.edu
 */

// Uncomment to turn on debugging 
//$debug = true;

// set to "hwpi" to turn on new page header/footer 
// $useheader = 'old';
$useheader = 'hwpi';

// ******* this file contains only supporting functions. *****


// Workaround from http://stackoverflow.com/questions/2045875/pass-by-reference-problem-with-php-5-3-1
function make_values_referenced($arr){
    $refs = array();
    foreach($arr as $key => $value) { 
        $refs[$key] = &$arr[$key];
    }
    return $refs;
}

function barcode_to_catalog_number($aBarcode) {
  $LOCALLENGTH = 9;    // HUH Barcode is a zero padded string of length 9 
  $returnvalue = $aBarcode;
  if (strlen($returnvalue) < $LOCALLENGTH) { 
     $returnvalue = str_pad($returnvalue, $LOCALLENGTH, "0", STR_PAD_LEFT);
  }
  return $returnvalue;
}

/** given some date and some precision where precision is 0,1,2,or 3, 
**  this will return the date as a string truncated to the appropriate precision
**  @date = dash separated date year - month - day
**  @precision = 0,1,2,3 where 0 = none, 1 = full, 2 = month, 3 = year
**  @return = date as a truncated text string
**/
function transformDate($date, $precision) { 
   if ($precision <= 0 || $precision > 3)
      return "";

   if (!preg_match("/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/", $date))
     return "";

  if ($precision == 1)
     return $date;
  
  $parts = explode("-",$date);
  
  if ($precision == 2)
    return $parts[0]."-".$parts[1];

  if ($precision == 3)
    return $parts[0];
  
  return "";
}


/** given some date and some precision where precision is 0,1,2,or 3, 
**  this will return the date as a string truncated to the appropriate precision
**  in the text form  99, Month 9999  day, month year
**  @date = dash separated date year - month - day
**  @precision = 0,1,2,3 where 0 = none, 1 = full, 2 = month, 3 = year
**  @return = date as a truncated text string 
**/
function transformDateText($date, $precision) { 
   $returnvalue = "";
   if ($precision <= 0 || $precision > 3)
      return "";

   if (!preg_match("/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/", $date))
     return "";

  $matches = array();
  $match = preg_match("/^([0-9][0-9][0-9][0-9])-([0-9][0-9])-([0-9][0-9])$/", $date, $matches);
  if ($precision == 1) { 
     $returnvalue =  $matches[1]."-".$matches[2]."-".$matches[3];
  }
  if ($precision == 2) { 
     $returnvalue =  $matches[1]."-".$matches[2]."-**";
  }
  if ($precision == 3) { 
     $returnvalue =  $matches[1];
  }   
  
  return $returnvalue;
}
 
 
function pageheader($mode = "specimen",$topbar = "on") { 
  global $useheader;
  if ($mode=="off") { $topbar = "off"; } 
  if  ($useheader=='hwpi') { 
    return pageheader_new($mode,$topbar);
  } else { 
    return pageheader_old($mode,$topbar);
  }
}
function pagefooter() { 
  global $useheader;
  if  ($useheader=='hwpi') { 
    return pagefooter_new($mode);
  } else { 
    return pagefooter_old($mode);
  }
}
 
function pageheader_old($mode = "specimen",$topbar = "on") {
	$title = "Specimen Search"; 
	$heading = "Specimens"; 
        $link = "specimen_index.html";
	$active['s'] = " class='active' ";
	$active['p'] = "";
	$active['b'] = "";
	$active['i'] = " ";
	switch ($mode) {
		case "off":
			$title = "Databases"; 
	        $heading = "Botanical Databases"; 
            $link = "specimen_index.html";
            break;
		case "specimen":
			$title = "Specimen Search"; 
	        $heading = "Specimens"; 
            $link = "specimen_index.html";
			$active['s'] = " class='active' ";
			$active['p'] = "";
			$active['b'] = "";
			$active['i'] = " ";
			break;
		case "agent":
			$title = "Botanist Search"; 
	        $heading = "Botanists"; 
            $link = "botanist_index.html";
			$active['s'] = " ";
			$active['p'] = " ";
			$active['b'] = " class='active' ";
			$active['i'] = " ";
			break;
		case "publication":
			$title = "Publication Search"; 
	        $heading = "Publications"; 
            $link = "publication_index.html";
			$active['s'] = " ";
			$active['p'] = " class='active' ";
			$active['b'] = " ";
			$active['i'] = " ";
			break;
		case "image":
			$title = "Specimen Image Search"; 
	        $heading = "Specimens"; 
            $link = "image_search.php";
			$active['s'] = "";
			$active['p'] = "";
			$active['b'] = "";
			$active['i'] = " class='active' ";
			break;
		default;
		
	}
	$result="<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>
<head>
	<meta http-equiv='content-type' content='text/html; charset=utf-8' />
	<title>HUH - Databases - $title</title>
	<link rel='stylesheet' type='text/css' media='print' href='print.css'></link>
	<link rel='stylesheet' type='text/css' href='dbstyles.css'></link>	
";
if ($mode=='image' || $mode=='imagedetails') { 
    // include jquery libraries
    $result.='
        <link type="text/css" href="css/jquery-ui.css" rel="Stylesheet" />   
        <script type="text/javascript" src="js/jquery.js"></script>
        <script type="text/javascript" src="js/jquery-ui.js"></script>
    ';
}
if ($mode=='image') { 
    // include libraries for visualsearch search bar
    $result.='
        <script type="text/javascript" src="js/underscore-1.4.3.js"></script>
        <script type="text/javascript" src="js/backbone-0.9.10.js"></script>

        <script src="js/visualsearch.js" type="text/javascript"></script>
        <!--[if (!IE)|(gte IE 8)]><!-->
           <link href="css/visualsearch-datauri.css" media="screen" rel="stylesheet" type="text/css"/>
        <!--<![endif]-->
        <!--[if lte IE 7]><!-->
           <link href="css/visualsearch.css" media="screen" rel="stylesheet" type="text/css"/>
        <!--<![endif]-->';
}
if ($mode=='imagedetails') { 
     // include libraries for featured image zoom widget
     $result.= "
          <link rel=\"stylesheet\" href=\"css/multizoom.css\" type=\"text/css\" />
          <script type=\"text/javascript\" src=\"js/multizoom.js\" ></script>
          ";
} // end mode==image
$result .= "
</head>
<body>
<div id='allcontent'>
	
		<!-- header code begins -->
		<div id='old_header'>
			<div id='top_menu'>
			
		        <!-- SiteSearch Google HERE -->
		         
				<div id='embed_nav'>
		  			<ul>
						<li><a href='http://www.huh.harvard.edu/'>Home</a></li>
						<li>&#124;</li>
						<li><a href='http://www.huh.harvard.edu/people/index.php'>Contact</a></li>
						<li>&#124;</li>
						<li><a href='http://www.huh.harvard.edu/news_events/news_events.html'>News &#38; Events</a></li>

						<li>&#124;</li>
						<li><a href='http://www.huh.harvard.edu/news_events/calendar.html'>Calendar</a></li>
						<li>&#124;</li>
						<li><a href='http://www.huh.harvard.edu/sitemap.html'>Sitemap</a></li>
						<li>&#124;</li>
						<li><a href='http://www.huh.harvard.edu/links.html'>Links</a></li>
		  			</ul>

				</div>
			
			</div>
			<!-- top menu ends -->
		
				<div id='mid_sect'>
					<a href='http://www.huh.harvard.edu'><img width='100' src='http://www.huh.harvard.edu/images/huh_logo_bw_100.png' alt='HUH logo' title='Home page' /></a>
				</div>
	

				<div id='topnav'>
		 			<ul>
			 			<li ><a href='http://www.huh.harvard.edu/collections/'>Collections</a></li>

			 			<li class=active><a href='http://www.huh.harvard.edu/databases/'>Databases</a></li>
			 			<li ><a href='http://www.huh.harvard.edu/research/'>Research</a></li>
			 			<li ><a href='http://www.huh.harvard.edu/seminar_series/'>Seminar Series</a></li>
			 			<li ><a href='http://www.huh.harvard.edu/libraries/'>Libraries</a></li>
			 			<li  ><a href='http://zatoichi.huh.harvard.edu/people/'>People</a></li>
			 			<li ><a href='http://www.huh.harvard.edu/publications/'>HPB Journal</a></li>

			 			<li ><a href='http://www.huh.harvard.edu/visiting/'>Visiting</a></li>
		   			</ul>
		 		</div>
		
		</div>

		<!-- header code ends -->
";
if ($topbar!="off") { 		
$result .= "
<div id='nav2'>
  <ul>
    <li><a href='addenda.html'>Search Hints</a></li>
    <li><a href='addenda.html#policy'>Distribution and Use Policy</a></li>
    <li><a href='botanist_index.html' ". $active['b'].">BOTANISTS</a></li>
    <li><a href='publication_index.html' ". $active['p'] .">PUBLICATIONS</a></li>
    <li><a href='specimen_index.html' ". $active['s'] .">SPECIMENS</a></li>
    <li><a href='image_search.php' ". $active['i'] .">IMAGES</a></li>
    <li><a href='taxon_search.php' ". $active['t'] .">TAXA</a></li>
    <li><a href='http://flora.huh.harvard.edu/HuCards/'>Hu Card Index</a></li>
    <li><a href='http://econ.huh.harvard.edu/'>ECON Artifacts</a></li>
    <li><a href='add_correct.html'>Contribute</a></li>
    <li><a href='comment.html'>Comments/questions</a></li>
    
  </ul>
</div>  <!-- nav2 ends -->		
";
}
$result .= "		
<div id='main'>
   <!-- main content begins -->
   <div id='main_text_wide'>
   <div id='title'>
      <h3><a href='$link'>Index of $heading</a></h3>
   </div>
"; 
   return $result;
}

function pagefooter_old() { 
   $result = "
   </div>
</div>
	<!-- main content ends -->

<p>Please feel free to report any issues that you observe with the data through our <a href='add_correct.html'>comments and corrections form</a>.
</p>

<!-- footer include begins -->		
	<div id='footer'>

			<div id='embed_nav2'>
		  		<ul>
					<li><a href='http://www.arboretum.harvard.edu/' target='_blank'>Arnold Arboretum</a></li>
					<li>&#124;</li>
					<li><a href='http://www.oeb.harvard.edu/' target='_blank'>OEB</a></li>

					<li>&#124;</li>
					<li><a href='http://www.pbi.fas.harvard.edu/' target='_blank'>PBI</a></li>
					<li>&#124;</li>
					<li><a href='http://www.hmnh.harvard.edu/' target='_blank'>HMNH</a></li> 
		  		</ul>
			</div>
			<h5>&copy; 2001 - <span id='cdate'></span> by the President and Fellows of <a href='http://www.harvard.edu/' target='_blank'>Harvard</a> College
		 	<br /><a href='http://www.huh.harvard.edu/priv_statement.html'>Privacy Statement</a> <span class='footer_indent'>Updated: 
		 		
		 		2009 May 21		 		
				</span></h5>

		 		
		 		<!-- gets current year for copyright date -->
		 		<script type='text/javascript'>
					// <![CDATA[
						var now = new Date()
						document.getElementById('cdate').innerHTML = now.getFullYear();
					// ]]>
				</script>

	</div>

</div> <!-- all content div tag ends -->
</body>
</html>";
   return $result;
} 


function pageheader_new($mode = "specimen",$topbar = "on") {
	$title = "Specimen Search"; 
	$heading = "Botanical Specimens"; 
        $link = "specimen_index.html";
	$active['s'] = " class='active' ";
	$active['p'] = "";
	$active['b'] = "";
	$active['i'] = " ";
	switch ($mode) {
		case "off":
			$title = "Databases"; 
	        $heading = "Botanical Databases"; 
            $link = "specimen_index.html";
            break;
		case "specimen":
			$title = "Specimen Search"; 
	        $heading = "Botanical Specimens"; 
            $link = "specimen_index.html";
			$active['s'] = " class='active' ";
			$active['p'] = "";
			$active['b'] = "";
			$active['i'] = " ";
			$active['t'] = "";
			break;
		case "agent":
			$title = "Botanist Search"; 
	        $heading = "Botanists"; 
            $link = "botanist_index.html";
			$active['s'] = " ";
			$active['p'] = " ";
			$active['b'] = " class='active' ";
			$active['i'] = " ";
			$active['t'] = "";
			break;
		case "publication":
			$title = "Publication Search"; 
	        $heading = "Botanical Publications"; 
            $link = "publication_index.html";
			$active['s'] = " ";
			$active['p'] = " class='active' ";
			$active['b'] = " ";
			$active['i'] = " ";
			$active['t'] = "";
			break;
		case "image":
			$title = "Specimen Image Search"; 
	        $heading = "Botanical Specimens"; 
            $link = "image_search.php";
			$active['s'] = "";
			$active['p'] = "";
			$active['b'] = "";
			$active['i'] = " class='active' ";
			$active['t'] = "";
			break;
		case "taxa":
			$title = "Taxon Search"; 
	        $heading = "Botanical Taxa"; 
            $link = "taxon_search.php";
			$active['s'] = "";
			$active['p'] = "";
			$active['b'] = "";
			$active['i'] = "";
			$active['t'] = " class='active' ";
			break;
		default;
		
	}
	$result="<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>
<head>
	<meta http-equiv='content-type' content='text/html; charset=utf-8' />
    <meta property='og:type' content='university' />
    <meta property='og:title' content='Harvard University Herbaria &amp; Libraries' />
<link rel='shortcut icon' href='http://hwpi.harvard.edu/profiles/openscholar/themes/hwpi_classic/favicon.ico' type='image/vnd.microsoft.icon' />
	<title>HUH - Databases - $title</title>
    <meta name='viewport' content='width=device-width, initial-scale=1.0' />
";
    // unstable style sheets coming from hwpi 
    // moved to local copies.
    $result .= "
<link type='text/css' rel='stylesheet' href='/css/hwpi/css_autocomplete_pbm0lsQQJ7A7WCCIMgxLho6mI_kBNgznNUWmTWcnfoE.css' media='all' />
<link type='text/css' rel='stylesheet' href='/css/hwpi/css_booknavigation_ueTLzD5nG-cUWCNxgvxnrujU5lN0jOXNNOXjbwGLMT0.css' media='all' />
<link type='text/css' rel='stylesheet' href='/css/hwpi/css__colorbox_4Cnbcv58osyNmwlNq65lb2j10SUGgMy5GBI44Cs5cko.css' media='all' />
";
if ($mode=='imagedetails') { 
  // line in one css file breaks the image details browser.
  $result .= "<link type='text/css' rel='stylesheet' href='css_OE.css' media='screen' />";
} else { 
  $result .= "<link type='text/css' rel='stylesheet' href='/css/hwpi/css__screen_ZA-CzvgM_hYQAxV3p2e2blh0OdJfEF_EIJ2yEh_Z9dU.css' media='screen' />";
}
$result .="
<link type='text/css' rel='stylesheet' href='/css/hwpi/css__print_qTBhov6j81VXwPEf5guTmDNsXK37qC0IaPAFtyW71lk.css' media='print' />
<link type='text/css' rel='stylesheet' href='/css/hwpi/css_messages_En_US41hhaF-_qfgf3V91TZA7_HTPvL-FMSrDwH_Tt0.css' media='all' />
    ";
// Original hwpi test website styles
/*
    <style>
      @import url('http://hwpi.harvard.edu/modules/system/system.base.css');
      @import url('http://hwpi.harvard.edu/modules/system/system.menus.css');
      @import url('http://hwpi.harvard.edu/modules/system/system.messages.css');
      @import url('http://hwpi.harvard.edu/modules/system/system.theme.css');
      
      @import url('http://hwpi.harvard.edu/modules/book/book.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/modules/contrib/calendar/css/calendar_multiday.css');
      @import url('http://hwpi.harvard.edu/modules/comment/comment.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/modules/contrib/date/date_api/date.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/modules/contrib/date/date_popup/themes/datepicker.1.7.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/modules/contrib/date/date_repeat_field/date_repeat_field.css');
      @import url('http://hwpi.harvard.edu/modules/field/theme/field.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/modules/contrib/mollom/mollom.css');
      @import url('http://hwpi.harvard.edu/modules/node/node.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/modules/os/modules/os_help/os_help.css');
      @import url('http://hwpi.harvard.edu/modules/search/search.css');
      @import url('http://hwpi.harvard.edu/modules/user/user.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/modules/contrib/views/css/views.css');
      
      @import url('http://hwpi.harvard.edu/profiles/openscholar/modules/contrib/colorbox/styles/default/colorbox_style.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/modules/contrib/ctools/css/ctools.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/modules/contrib/nice_menus/nice_menus.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/modules/contrib/nice_menus/nice_menus_default.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/modules/contrib/views_slideshow/views_slideshow.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/modules/contrib/biblio/biblio.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/modules/os/modules/os_slideshow/os_slideshow.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/themes/hwpi_basetheme/css/responsive.base.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/themes/hwpi_basetheme/css/responsive.layout.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/themes/hwpi_basetheme/css/responsive.nav.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/themes/hwpi_basetheme/css/responsive.slideshow.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/themes/hwpi_basetheme/css/responsive.widgets.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/themes/hwpi_classic/css/responsive.classic.css');
      
      @import url('http://hwpi.harvard.edu/profiles/openscholar/themes/adaptivetheme/at_core/css/at.layout.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/themes/os_basetheme/css/globals.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/themes/hwpi_basetheme/css/hwpi.globals.css');
      @import url('http://hwpi.harvard.edu/profiles/openscholar/themes/hwpi_classic/css/hwpi_classic.css');
      
      @import url('http://hwpi.harvard.edu/profiles/openscholar/modules/os/theme/os_dismiss.css');
    </style>
*/

// ivy style sheets from hwpi
$result .= "
<style media='print'>@import url('http://hwpi.harvard.edu/profiles/openscholar/themes/os_basetheme/css/print.css');</style>
    <link type='text/css' rel='stylesheet' href='http://hwpi.harvard.edu/profiles/openscholar/themes/hwpi_classic/flavors/ivy_accents/ivy_accents.css' media='all' />
    <link type='text/css' rel='stylesheet' href='http://hwpi.harvard.edu/profiles/openscholar/themes/hwpi_classic/flavors/ivy_accents/responsive.ivy.css' media='all' />
        <script type='text/javascript' src='/js/hwpi/jquery_from_hwpi.js'></script>
        <script type='text/javascript' src='/js/hwpi/js__rhiSuayLbtRqMHYTNEz5cOkIfup7XMCy0XrxzyE6zOI.js'></script>
        <script type='text/javascript'>
        </script>
";
$result .= '
        <script type="text/javascript">
jQuery.extend(Drupal.settings, {"basePath":"\/","pathPrefix":"herbaria\/","ajaxPageState":{"theme":"hwpi_classic","theme_token":"B2peLlHWVgl3MujkxO_-L1AVgD_yW5qJJftnZtO1lk8","js":{"profiles\/openscholar\/libraries\/respondjs\/respond.min.js":1,"profiles\/openscholar\/modules\/contrib\/jquery_update\/replace\/jquery\/1.8\/jquery.min.js":1,"misc\/jquery.once.js":1,"misc\/drupal.js":1,"profiles\/openscholar\/modules\/os\/theme\/os_colorbox.js":1,"profiles\/openscholar\/libraries\/colorbox\/jquery.colorbox-min.js":1,"profiles\/openscholar\/modules\/contrib\/colorbox\/js\/colorbox.js":1,"profiles\/openscholar\/modules\/contrib\/colorbox\/styles\/default\/colorbox_style.js":1,"profiles\/openscholar\/modules\/contrib\/colorbox\/js\/colorbox_inline.js":1,"profiles\/openscholar\/modules\/contrib\/nice_menus\/superfish\/js\/superfish.js":1,"profiles\/openscholar\/modules\/contrib\/nice_menus\/superfish\/js\/jquery.bgiframe.min.js":1,"profiles\/openscholar\/modules\/contrib\/nice_menus\/superfish\/js\/jquery.hoverIntent.minified.js":1,"profiles\/openscholar\/modules\/contrib\/nice_menus\/nice_menus.js":1,"profiles\/openscholar\/modules\/contrib\/views_slideshow\/js\/views_slideshow.js":1,"0":1,"profiles\/openscholar\/modules\/os\/modules\/os_ga\/os_ga.js":1,"profiles\/openscholar\/modules\/os\/theme\/os_dismiss.js":1,"profiles\/openscholar\/themes\/os_basetheme\/js\/os_base.js":1,"profiles\/openscholar\/themes\/hwpi_basetheme\/js\/css_browser_selector.js":1,"profiles\/openscholar\/themes\/hwpi_basetheme\/js\/matchMedia.js":1,"profiles\/openscholar\/themes\/hwpi_basetheme\/js\/eq.js":1,"profiles\/openscholar\/themes\/hwpi_basetheme\/js\/eq-os.js":1,"profiles\/openscholar\/themes\/hwpi_basetheme\/js\/scripts.js":1},"css":{"modules\/system\/system.base.css":1,"modules\/system\/system.menus.css":1,"modules\/system\/system.messages.css":1,"modules\/system\/system.theme.css":1,"modules\/book\/book.css":1,"profiles\/openscholar\/modules\/contrib\/calendar\/css\/calendar_multiday.css":1,"modules\/comment\/comment.css":1,"profiles\/openscholar\/modules\/contrib\/date\/date_api\/date.css":1,"profiles\/openscholar\/modules\/contrib\/date\/date_popup\/themes\/datepicker.1.7.css":1,"profiles\/openscholar\/modules\/contrib\/date\/date_repeat_field\/date_repeat_field.css":1,"modules\/field\/theme\/field.css":1,"profiles\/openscholar\/modules\/contrib\/mollom\/mollom.css":1,"modules\/node\/node.css":1,"profiles\/openscholar\/modules\/os\/modules\/os_slideshow\/os_slideshow.css":1,"profiles\/openscholar\/modules\/os\/modules\/os_slideshow\/os_slideshow_aspect_ratio_form.css":1,"modules\/search\/search.css":1,"modules\/user\/user.css":1,"profiles\/openscholar\/modules\/contrib\/views\/css\/views.css":1,"profiles\/openscholar\/modules\/contrib\/colorbox\/styles\/default\/colorbox_style.css":1,"profiles\/openscholar\/modules\/contrib\/ctools\/css\/ctools.css":1,"profiles\/openscholar\/modules\/contrib\/nice_menus\/nice_menus.css":1,"profiles\/openscholar\/modules\/contrib\/nice_menus\/nice_menus_default.css":1,"profiles\/openscholar\/modules\/contrib\/views_slideshow\/views_slideshow.css":1,"profiles\/openscholar\/modules\/contrib\/biblio\/biblio.css":1,"profiles\/openscholar\/themes\/hwpi_basetheme\/css\/responsive.base.css":1,"profiles\/openscholar\/themes\/hwpi_basetheme\/css\/responsive.layout.css":1,"profiles\/openscholar\/themes\/hwpi_basetheme\/css\/responsive.nav.css":1,"profiles\/openscholar\/themes\/hwpi_basetheme\/css\/responsive.slideshow.css":1,"profiles\/openscholar\/themes\/hwpi_basetheme\/css\/responsive.widgets.css":1,"profiles\/openscholar\/themes\/hwpi_classic\/css\/responsive.classic.css":1,"profiles\/openscholar\/themes\/adaptivetheme\/at_core\/css\/at.layout.css":1,"profiles\/openscholar\/themes\/os_basetheme\/css\/globals.css":1,"profiles\/openscholar\/themes\/hwpi_basetheme\/css\/hwpi.globals.css":1,"profiles\/openscholar\/themes\/hwpi_classic\/css\/hwpi_classic.css":1,"profiles\/openscholar\/themes\/os_basetheme\/css\/print.css":1,"profiles\/openscholar\/modules\/os\/theme\/os_dismiss.css":1,"profiles\/openscholar\/themes\/hwpi_classic\/flavors\/ivy_accents\/ivy_accents.css":1,"profiles\/openscholar\/themes\/hwpi_classic\/flavors\/ivy_accents\/responsive.ivy.css":1}},"colorbox":{"opacity":"0.85","current":"{current} of {total}","previous":"\u00ab Prev","next":"Next \u00bb","close":"Close","maxWidth":"98%","maxHeight":"98%","fixed":true,"mobiledetect":true,"mobiledevicewidth":"480px"},"jcarousel":{"ajaxPath":"\/herbaria\/jcarousel\/ajax\/views"},"nice_menus_options":{"delay":800,"speed":1},"os_ga":{"trackOutbound":1,"trackMailto":1,"trackDownload":1,"trackDownloadExtensions":"7z|aac|arc|arj|asf|asx|avi|bin|csv|docx?|exe|flv|gif|gz|gzip|hqx|jar|jpe?g|js|mp(2|3|4|e?g)|mov(ie)?|msi|msp|pdf|phps|png|ppt|qtm?|ra(m|r)?|sea|sit|tar|tgz|torrent|txt|wav|wma|wmv|wpd|xlsx?|xml|z|zip","trackNavigation":1},"ogContext":{"groupType":"node","gid":"92531"},"password":{"strengthTitle":"Password compliance:"},"type":"setting"});

        </script>
        ';
// Local HUH stylesheet
$result .= "
	<link rel='stylesheet' type='text/css' href='dbstyles.css'></link>	
";
if (1==1 || $mode=='image' || $mode=='imagedetails') { 
    // include jquery libraries
    $result.='
        <link type="text/css" href="css/jquery-ui.css" rel="Stylesheet" />   
        <script type="text/javascript" src="js/jquery.js"></script>
        <script type="text/javascript" src="js/jquery-ui.js"></script>
    ';
}
if ($mode=='image') { 
    // include libraries for visualsearch search bar
    $result.='
        <script type="text/javascript" src="js/underscore-1.4.3.js"></script>
        <script type="text/javascript" src="js/backbone-0.9.10.js"></script>

        <script src="js/visualsearch.js" type="text/javascript"></script>
        <!--[if (!IE)|(gte IE 8)]><!-->
           <link href="css/visualsearch-datauri.css" media="screen" rel="stylesheet" type="text/css"/>
        <!--<![endif]-->
        <!--[if lte IE 7]><!-->
           <link href="css/visualsearch.css" media="screen" rel="stylesheet" type="text/css"/>
        <!--<![endif]-->';
}
if ($mode=='imagedetails') { 
     // include libraries for featured image zoom widget
     $result.= "
          <link rel=\"stylesheet\" href=\"css/multizoom.css\" type=\"text/css\" />
          <script type=\"text/javascript\" src=\"js/multizoom.js\" ></script>
          ";
} // end mode==image
$result .= "
</head>
<body class='html not-front not-logged-in one-sidebar sidebar-second page-node page-node- page-node-99711 node-type-page og-context og-context-node og-context-node-92531 navbar-on'>
  <div id='skip-link'>
    <a href='#main-content' class='element-invisible element-focusable' tabindex='1'>Skip to main content</a>
  </div>
<div id='allcontent'>
    
<!--FLEXIBLE ADMIN HEADER FOR USE BY SELECT GROUPS USING OS-->
    <div id='branding_header'>
        <div  class='branding-container clearfix'>
          <div class='branding-left'><a href='http://www.harvard.edu' ><img typeof='foaf:Image' src='http://hwpi.harvard.edu/profiles/openscholar/themes/hwpi_basetheme/images/harvard-logo.png' width='259' height='35' alt='Harvard Logo' /></a></div><div class='branding-right'><a href='http://www.fas.harvard.edu/' >FACULTY OF ARTS AND SCIENCES</a> | <a href='http://www.harvard.edu' >HARVARD.EDU</a></div>     </div>
    </div>

<div id='page' class='container page header-main header-right content-top content-right footer footer-right'>
    <div id='page-wrapper'>

					<!--header regions beg-->
			<header id='header' class='clearfix' role='banner'>
			 <div id='header-container'>
				 <div id='header-panels' class='at-panel gpanel panel-display three-col clearfix'>
					 <div class='region region-header-second'><div class='region-inner clearfix'><div id='block-boxes-site-info' class='block block-boxes block-boxes-os_boxes_site_info no-title' ><div class='block-inner clearfix'>  
                     <div class='block-content content'><div id='boxes-box-site_info' class='boxes-box'><div class='boxes-box-content'><h1><a href='http://www.huh.harvard.edu/'  class='active'>Harvard University Herbaria &amp; Libraries</a></h1>
    <p>
    </p></div></div></div>
  </div></div></div></div>					  					  <div class='region region-header-third'><div class='region-inner clearfix'><div id='block-os-secondary-menu' class='block block-os no-title' ><div class='block-inner clearfix'>  
  
  <div class='block-content content'><ul class='nice-menu nice-menu-down' id='nice-menu-secondary-menu'><li class='menu-3619 menu-path-node-99471  first   odd  '><a href='http://huh.harvard.edu/pages/contact' >Contact</a></li><li class='menu-3620 menu-path-node-99461   even  '><a href='http://huh.harvard.edu/pages/visit' >Visit</a></li><li class='menu-3604 menu-path-kikihuhharvardedu-databases-   odd   last '><a href='http://kiki.huh.harvard.edu/databases/' >Databases</a></li></ul>
</div>
  </div></div><div id='block-os-search-solr-site-search' class='block block-os-search-solr no-title' ><div class='block-inner clearfix'>  
  
  <div class='block-content content'><form action='http://huh.harvard.edu/search/site' method='post' id='search-block-form' accept-charset='UTF-8'><div><div class='container-inline'>
  <div class='form-item form-type-textfield form-item-search-block-form'>
  <label for='edit-search-block-form--2'>Search </label>
 <input title='Enter the terms you wish to search for.' type='search' id='edit-search-block-form--2' name='search_block_form' value='' size='15' maxlength='128' class='form-text' />
</div>
<div class='form-actions form-wrapper' id='edit-actions'><input type='submit' id='edit-submit' name='op' value='Search' class='form-submit' /></div><input type='hidden' name='form_build_id' value='form-2-0EE9t7nDtl9hRVx2rTRedX-IkvEpOTna-UAoieeUc' />
<input type='hidden' name='form_id' value='search_block_form' />
</div></div></form></div>
  </div></div></div></div>					  				 </div>
			  </div>
		  </header>
      <!--header regions end-->        

				  <!--main menu region beg-->
<div id='menu-bar' class='nav clearfix'>
<nav id='block-os-primary-menu' class='block block-os no-title menu-wrapper menu-bar-wrapper clearfix' >  
 
<ul class='nice-menu nice-menu-down' id='nice-menu-primary-menu'>
<li class='menu-3564 menuparent  menu-path-node-98801  first   odd  '><a href='http://huh.harvard.edu/pages/collections'  title='' class='active active'>Collections</a>
<ul>
  <li class='menu-3600 menu-path-node-98996  first   odd  '><a href='http://huh.harvard.edu/pages/herbaria'  title=''>Herbaria</a></li>
  <li class='menu-3601 menu-path-node-99001   even  '><a href='http://huh.harvard.edu/pages/digital-resources'  title=''>Digital Resources</a></li>
  <li class='menu-3602 menu-path-node-99006   odd   last '><a href='http://huh.harvard.edu/pages/use'  title=''>Use Policies</a></li>
</ul>
</li>
<li class='menu-3565 menuparent  menu-path-node-98811   even   active-trail'><a href='http://huh.harvard.edu/pages/research'  title='' >Research</a>
<ul>
  <li class='menu-3630 menu-path-node-99711  first   odd  '><a href='http://huh.harvard.edu/pages/taxonomy'  title=''>Taxonomy</a></li>
  <li class='menu-6062 menu-path-node-205581   even  '><a href='http://huh.harvard.edu/floristics-and-monography'  title=''>Floristics &amp; Monography</a></li>
  <li class='menu-3631 menu-path-node-99726   odd  '><a href='http://huh.harvard.edu/pages/plant-fungal-phylogenetics'  title=''>Plant &amp; Fungal Phylogenetics</a></li>
  <li class='menu-6061 menu-path-node-205661   even  '><a href='http://huh.harvard.edu/paleobotany'  title=''>Paleobotany</a></li>
  <li class='menu-6353 menu-path-node-229251   odd  '><a href='http://huh.harvard.edu/plant-speciation-and-local-adaptation'  title=''>Plant Speciation and Local Adaptation</a></li>
  <li class='menu-6354 menu-path-node-232531   even   last '><a href='http://huh.harvard.edu/forest-ecosystem-carbon-dynamics'  title=''>Forest Ecosystem Carbon Dynamics</a></li>
</ul>
</li>
<li class='menu-5387 menuparent  menu-path-node-141961   odd  '><a href='http://huh.harvard.edu/pages/publications'  title=''>Publications</a>
<ul>
  <li class='menu-5804 menu-path-node-141986  first   odd  '><a href='http://huh.harvard.edu/pages/publications-about'  title=''>About HPB</a></li>
  <li class='menu-5805 menu-path-node-141976   even  '><a href='http://huh.harvard.edu/pages/orders-access'  title=''>Orders &amp; Access</a></li>
  <li class='menu-5806 menu-path-node-141971   odd   last '><a href='http://huh.harvard.edu/pages/manuscript-preparation'  title=''>For Authors</a></li>
</ul>
</li>
<li class='menu-4113 menuparent  menu-path-node-110296   even  '><a href='http://huh.harvard.edu/libraries'  title=''>Libraries</a>
<ul>
  <li class='menu-4657 menuparent  menu-path-node-134106  first   odd  '><a href='http://huh.harvard.edu/pages/libraries-collections'  title=''>Libraries&#039; Collections</a>
 <ul>
  <li class='menu-4654 menu-path-libharvardedu-  first   odd  '><a href='http://lib.harvard.edu/' >Harvard&#039;s Online Library Catalog (HOLLIS)</a></li>
  <li class='menu-4662 menu-path-node-134096   even  '><a href='http://huh.harvard.edu/pages/archives' >Archives Collections</a></li>
  <li class='menu-4671 menu-path-node-138056   odd   last '><a href='http://huh.harvard.edu/pages/digital-collections-0' >Digital Collections</a></li>
  </ul>
  </li>
<li class='menu-4660 menuparent  menu-path-node-134126   even  '><a href='http://huh.harvard.edu/pages/use-libraries'  title=''>Use of the Libraries</a>
  <ul>
     <li class='menu-4666 menu-path-node-137936  first   odd  '><a href='http://huh.harvard.edu/pages/hours-directions'  title=''>Hours</a></li>
     <li class='menu-4658 menu-path-node-134111   even  '><a href='http://huh.harvard.edu/pages/resources' >Resources</a></li>
     <li class='menu-4659 menu-path-node-134116   odd  '><a href='http://huh.harvard.edu/pages/services' >Services</a></li>
     <li class='menu-4668 menu-path-node-137946   even   last '><a href='http://huh.harvard.edu/pages/permission-publish'  title=''>Permissions</a></li>
 </ul>
 </li>
 <li class='menu-4673 menu-path-people-taxonomy-term-18916   odd  '><a href='http://huh.harvard.edu/association/libraries'  title=''>Libraries Staff</a></li>
 <li class='menu-4661 menu-path-node-134136   even   last '><a href='http://huh.harvard.edu/pages/online-exhibits'  title=''>Online Exhibits</a></li>
</ul>
</li>
<li class='menu-4141 menuparent  menu-path-node-113866   odd  '><a href='http://huh.harvard.edu/pages/news-events'  title=''>News &amp; Events</a>
<ul>
  <li class='menu-4142 menu-path-news  first   odd  '><a href='http://huh.harvard.edu/news' >News</a></li>
  <li class='menu-5072 menu-path-node-146976   even   last '><a href='http://huh.harvard.edu/events'  title=''>Events</a></li>
</ul></li>
<li class='menu-22536 menu-path-people   even  '><a href='http://huh.harvard.edu/people'  title='List of people'>People</a></li>
<li class='menu-3610 menuparent  menu-path-node-99451   odd   last '><a href='http://huh.harvard.edu/pages/about'  title=''>About</a>
<ul>
  <li class='menu-3621 menu-path-node-99461  first   odd  '><a href='http://huh.harvard.edu/pages/visit' >Visit</a></li>
  <li class='menu-3622 menu-path-node-99471   even  '><a href='http://huh.harvard.edu/pages/contact' >Contact</a></li>
  <li class='menu-3623 menu-path-node-99476   odd   last '><a href='http://huh.harvard.edu/pages/history' >History</a></li>
</ul></li>
</ul>
 

  </nav></div>		  <!--main menu region end-->
        

		<!-- header code ends -->

";
if ($topbar!="off") { 
$result .= "	
<div id='nav2'>
  <ul>
    <li><a href='addenda.html'>Search Hints</a></li>
    <li><a href='addenda.html#policy'>Use Policy</a></li>
    <li><a href='botanist_index.html' ". $active['b'].">Botanists</a></li>
    <li><a href='publication_index.html' ". $active['p'] .">Publications</a></li>
    <li><a href='specimen_index.html' ". $active['s'] .">Specimens</a></li>
    <li><a href='image_search.php' ". $active['i'] .">Images</a></li>
    <li><a href='taxon_search.php' ". $active['t'] .">Taxa</a></li>
    <li><a href='http://flora.huh.harvard.edu/HuCards/'>Hu Cards</a></li>
    <li><a href='http://econ.huh.harvard.edu/'>ECON Artifacts</a></li>
    <li><a href='add_correct.html'>Contribute</a></li>
    <li><a href='comment.html'>Comments</a></li>
    
  </ul>
</div>  <!-- nav2 ends -->		
";
} 
$result .= "
		
<div id='main'>
   <!-- main content begins -->
   <a name='main-content'></a>
   <div id='main_text_wide'>
   <div id='title'>
      <h3><a href='$link'>Index of $heading</a></h3>
   </div>
"; 
   return $result;
}

function pagefooter_new() { 
   $result = '
   </div>
</div>
	<!-- main content ends -->

<div id="extradiv"></div>

  <!--FLEXIBLE ADMIN FOOTER FOR USE BY SELECT GROUPS USING OS-->
  <div id="branding_footer">
        <div class="branding-container">
        <div class="copyright"><span class="harvard-copyright">Copyright &copy; 2013 The President and Fellows of Harvard College</span> | <a href="http://accessibility.harvard.edu/" >Accessibility</a></div>       </div>
  </div>

</div> <!-- all content div tag ends -->
  </body>
</html>';
   return $result;
} 

/** 
 * This function will execute the sql statement for your major category counts Given the query that select count, field name from group by field name, this will return a series of anchor statements that run a search on the field provided in the field parameter displaying the names and counts for each row.
 * @param = $query a string containing a valid sql statement in the form 
                'select count(*), fieldname from ... group by fieldname'
 * @param = $field  a string containing the name of a field that can be searched through the search 
            mode of this application (mode=search&$field=valueOfFieldName) 
 * @returns a list of <a href=  href='specimen_search.php?mode=search&$field={value}'>{value}</a> ({count}) <BR>
**/
function nameCountSearch($query, $field, $cachequery) {
    global $connection, $errormessage;
	
       $result = "";
       if (strlen($cachequery)>0) { 
           $statement = $connection->prepare($cachequery);
           if (!$statement) { 
               $statement = $connection->prepare($query);
           }
       } else { 
            $statement = $connection->prepare($query);
       }
       if ($statement) { 
		$statement->execute();
		$statement->bind_result($ct, $name, $imct);
		$statement->store_result();
		if ($statement->num_rows > 0 ) {
			while ($statement->fetch()) { 
			    if ($name!="") { 
                                    $im = "";
                                    if ($imct>0) { 
                                       $im = " <a href='image_search.php?$field=$name'>($imct Images)</a>";
                                    }
				    $result .= "<a href='specimen_search.php?mode=search&$field=$name'>$name</a> ($ct) $im<BR>";
				}
			}
			
		} else {
			$errormessage .= "No matching results. ";
		}
  	    $statement->close();
	} else { 
	    $errormessage .= $connection->error; 
    } 
    
    return $result;
}


// TODO: Replace this with an include of the generated file.
// This function gives html that provides counts for major categories in the database. 
// There are elements to this function that are HUH specific.
function stats() {  
   $returnvalue = "";
   $returnvalue .= "<h1>Statistics</h1>";
   
   $returnvalue .= "<h2>Numbers of specimens in major groups.</h2>";
   $picklistName = 'HUH Taxon Group'; //customize for your own instance of Specify
   $query = "select count(*), Title from taxon left join picklistitem on groupnumber = value where PickListID=(select PickListID from  picklist where name='$picklistName') group by title";
   $returnvalue .= nameCountSearch($query, 'taxonGroup');
   
   $returnvalue .= "<h2>Numbers of specimens by Herbarium.</h2>";
   //you need to customize this for the way you have collections, collection codes and acronyms managed in Specify for your institution
   $query = "select count(PreparationId), preparationattribute.text3 from preparation left join preparationattribute on preparation.preparationattributeid = preparationattribute.preparationattributeid group by preparationattribute.text3";
   $query = "select count(collectionobjectid), text1 from fragment group by text1";
   $returnvalue .= nameCountSearch($query, 'herbarium');
   
   $returnvalue .= "<h2>Numbers of specimens by Type Status.</h2>";
   $query = "select count(collectionobjectid), typestatusname from determination d left join fragment f  on d.fragmentid = f.fragmentid where typestatusname is not null group by typestatusname";
   $returnvalue .= nameCountSearch($query, 'typestatus');
   
   return $returnvalue;
}

function browse($target = 'families') { 
	$result = ""; 
	$field = "";
	switch ($target) { 
		case 'types':
                        $cachequery = "";
			$sql = 'select count(collectionobjectid), typestatus, 0 from web_search group by typestatus ';
			$field = 'typestatus';
			break;	
		case 'countries':
                        $cachequery = "select cocount, country, imcount from cache_country ";
			$sql = 'select count(distinct w.collectionobjectid), country, count(distinct i.imagesetid) from web_search w left join IMAGE_SET_collectionobject i on w.collectionobjectid = i.collectionobjectid group by country ';
			$field = 'country';
			break;	
		case 'families':
		default: 
                        $cachequery = "select cocount, family, imcount from cache_family ";
			$sql = 'select count(distinct w.collectionobjectid), family, count(distinct i.imagesetid) from web_search w left join IMAGE_SET_collectionobject i on w.collectionobjectid = i.collectionobjectid  group by family ';
			$field = 'family';
	}
	if ($sql!="") { 
		$result = nameCountSearch($sql, $field, $cachequery);
	} 
	return $result; 
}

/** 
 * Workaround for missing json_encode in old php
 */
function json_encode( $array ){

    if( !is_array( $array ) ){
        return false;
    }

    $associative = count( array_diff( array_keys($array), array_keys( array_keys( $array )) ));
    if( $associative ){

        $construct = array();
        foreach( $array as $key => $value ){

            // We first copy each key/value pair into a staging array,
            // formatting each key and value properly as we go.

            // Format the key:
            if( is_numeric($key) ){
                $key = "key_$key";
            }
            $key = '"'.addslashes($key).'"';

            // Format the value:
            if( is_array( $value )){
                $value = json_encode( $value );
            } else if( !is_numeric( $value ) || is_string( $value ) ){
                $value = '"'.addslashes($value).'"';
            }

            // Add to staging array:
            $construct[] = "$key: $value";
        }

        // Then we collapse the staging array into the JSON form:
        $result = "{ " . implode( ", ", $construct ) . " }";

    } else { // If the array is a vector (not associative):

        $construct = array();
        foreach( $array as $value ){

            // Format the value:
            if( is_array( $value )){
                $value = json_encode( $value );
            } else if( !is_numeric( $value ) || is_string( $value ) ){
                $value = '"'.addslashes($value).'"';
            }

            // Add to staging array:
            $construct[] = $value;
        }

        // Then we collapse the staging array into the JSON form:
        $result = "[ " . implode( ", ", $construct ) . " ]";
    }

    return $result;
}

?>
