<?php
include_once('ImageExplorer.php');

    $imageExplorer = new ImageExplorer();

    // convert $_POST to array in form array(key) { value, value }
    $query = $_POST['query'];
    $start = preg_replace('/[^0-9]/','',$_POST['start']);
    if (strlen($start)==0) { $start = 0; }

    $queryArray = Array();
 
    $query = str_replace('\\','',$query);

    $query = preg_replace('/: "/',':',$query);
    $query = preg_replace('/" /','|',"$query ");
    $pairs = explode('|',$query);
    $querytext = "";  // human readable description of query
    foreach ($pairs as $pairstr) { 
       if (strpos($pairstr,':')===FALSE) { 
          $pairstr = "text:$pairstr";
       }
       $pair = explode(':',$pairstr);
       if ($pair[0]!="" && $pair[1]!='') { 
          $key = $pair[0];
          $value = $pair[1];
          if (array_key_exists($key,$queryArray)) { 
            $valueArray = $queryArray[$key];
            $valueArray[] = $value;
            $queryArray[$key] = $valueArray;
            $querytext .= ",$value";
          } else { 
            $querytext .= " $key=";
            $valueArray = Array();
            $valueArray[] = $value;
            $queryArray[$key] = $valueArray;
            $querytext .= "$value";
          }
       }
    }
    // set start point for page
    $queryArray['start'] = $start;  

    $imgArr = $imageExplorer->getImages($queryArray);

    echo '<div style="clear:both;">';
    echo "<strong>Query for: $querytext</strong><br/>";
    $resultCount = $imgArr['cnt'];
    if (strlen($resultCount)==0) { $resultCount = "0"; }
    echo "<br/><strong>Found $resultCount Sheets.</strong><br/>" ;
    echo '<input type="hidden" id="imgCnt" value="'.$resultCount.'" />';

    unset($imgArr['cnt']);
    foreach($imgArr as $imgArr){
                    $imgId = $imgArr['imagesetid'];
                    $imgUrl = $imgArr['uri'];
                    $imgUrl = $imageDomain.$imgUrl;
?>

                    <div class="tndiv">
                        <div class="tnimg">
                            <a href="image_search.php?mode=details&imagesetid=<?php echo $imgId; ?>">
                                <img src="<?php echo $imgUrl; ?>" />
                            </a>
                        </div>
                        <div>
                            <a href="specimen_search.php?mode=details&id=<?php echo $imgArr['collectionobjectid']; ?>"><i><?php echo $imgArr['scientificname']."<br>" . $imgArr['country'] . ":" . $imgArr['state'] ;  ?></i></a> 
                        </div>
                    </div>
<?php
    }
    echo "</div>";
?>
