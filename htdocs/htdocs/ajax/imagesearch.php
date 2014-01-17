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
    $querytext = "";  // human readable description of query
    $semicolon = "";
    foreach ($queryArray as $key => $values) { 
        $querytext .= "$semicolon$key=";
        $comma = "";
        foreach ($values as $value) { 
            $querytext .= "$comma$value";
            $comma = ",";
        } 
        $semicolon = "; ";
    }
    // set start point for page
    $queryArray['start'] = $start;  

    $imgArr = $imageExplorer->getImages($queryArray);

    echo '<div style="clear:both;">';
    echo "<strong>Query for: $querytext</strong><br/>";
    $resultCount = $imgArr['cnt'];
    if (strlen($resultCount)==0) { $resultCount = "0"; }
    $st = $start + 1;
    $end = $start + 50;
    if ($end > $resultCount) { $end = $resultCount; } 
    $last = $resultCount - 50;
    $prev = $start - 50;
    if ($prev < 0 ) { $prev = 0; } 
    echo "<br/><strong>Found $resultCount Sheets.  Showing $st to $end.</strong>" ; 
    if ($resultCount>50) { 
    echo "<form >";
         $b1d=$b2d=$b3d=$b4d='';
         if ($start==0) { $b1d = "disabled='true'"; $b2d = "disabled='true'"; } 
         if ($end==$resultCount) { $b3d = "disabled='true'"; $b4d = "disabled='true'"; } 
         echo "<input type='button' $b1d value='First' onclick=\"
                              $.post('ajax/imagesearch.php', { 'query' : '$query' , 'start' : '0' }, function(result) {
                                   $('#results').html(result); 
                                   }); \"
                      class='styledButton' 
         />"; 
         echo "<input type='button' $b2d value='Previous 50' onclick=\"
                              $.post('ajax/imagesearch.php', { 'query' : '$query' , 'start' : '$prev' }, function(result) {
                                   $('#results').html(result); 
                                   }); \"
                      class='styledButton' 
         />"; 
         echo "<input type='button' $b3d value='Next 50' onclick=\"
                              $.post('ajax/imagesearch.php', { 'query' : '$query' , 'start' : '$end' }, function(result) {
                                   $('#results').html(result); 
                                   }); \"
                      class='styledButton' 
         />"; 
         echo "<input type='button' $b4d value='Last' onclick=\"
                              $.post('ajax/imagesearch.php', { 'query' : '$query' , 'start' : '$last' }, function(result) {
                                   $('#results').html(result); 
                                   }); \"
                      class='styledButton' 
         />"; 
    echo "</form>";
    }
    echo "<br/>" ;
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
