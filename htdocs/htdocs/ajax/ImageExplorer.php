<?php
include_once('connection_library.php');

if ($debug) {
    mysqli_report(MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_STRICT);
} else {
    mysqli_report(MYSQLI_REPORT_OFF);
}

$connection = specify_connect();

class PreparedSQL { 
   public $sql; 
   public $types;
   public $params = Array();
}

class ImageExplorer{

	private $imgCnt = 0;

    /** To enable mysqli_stmt->bind_param using call_user_func_array($array) 
     * allow $array to be converted to array of by references 
     * if php version requires it. 
     */
    public static function correctReferences($params) { 
       if (strnatcmp(phpversion(),'5.3') >= 0) {
          $byrefs = array();
          foreach($params as $key => $value)
             $byrefs[$key] = &$array[$key];
          return $byrefs;
       }
       return $arr;
    } 

	public function getImages($searchCriteria){
        global $connection;
		$retArr = array();
		$sql = $this->getSql($searchCriteria);
        $stmt = $connection->stmt_init();
        if ($stmt->prepare($sql->sql)) { 
            if (strlen($sql->types)==1) {
               $stmt->bind_param($sql->types, $sql->params[0]);
            }
            if (strlen($sql->types)==2) {
               $stmt->bind_param($sql->types, $sql->params[0], $sql->params[1]);
            }
            if (strlen($sql->types)==3) {
               $stmt->bind_param($sql->types, $sql->params[0], $sql->params[1], $sql->prams[2]);
            }
            if (strlen($sql->types)==4) {
               $stmt->bind_param($sql->types, $sql->params[0], $sql->params[1], $sql->prams[2], $sql->params[3]);
            }
            if (strlen($sql->types)==5) {
               $stmt->bind_param($sql->types, $sql->params[0], $sql->params[1], $sql->prams[2], $sql->params[3], $sql->params[4]);
            }
            //call_user_func_array('mysqli_stmt_bind_param', 
            //     array_merge (array($stmt, $sql->types), $this->correctReferences($sql->params))
            //); 
            $stmt->execute();
            echo $stmt->error;
            $stmt->bind_result($uri,$scientificname,$country,$state,$collectionobjectid,$imagesetid);
			while($stmt->fetch()){
                // convert into an associative array
                // todo: fetch query metadata instead of hardcoding.
                $r = Array();
		        $r['uri'] = $uri;
                $r['scientificname'] = $scientificname;
                $r['country'] = $country; 
                $r['state'] = $state; 
                $r['collectionobjectid'] = $collectionobjectid;
                $r['imagesetid'] = $imagesetid;
				$retArr[$r['collectionobjectid']] = $r;
			}
            $stmt->close();			

			if($retArr){
			
				//Set image count
				$cntSql = 'SELECT count(distinct dwc.collectionobjectid) AS cnt '.substr($sql->sql,strpos($sql->sql,' FROM '));
				$cntSql = substr($cntSql,0,strpos($cntSql,' LIMIT '));
                $stmt = $connection->stmt_init();
                if ($stmt->prepare($cntSql)) {
                   if (strlen($sql->types)==1) {
                       $stmt->bind_param($sql->types, $sql->params[0]);
                   }
                   if (strlen($sql->types)==2) {
                      $stmt->bind_param($sql->types, $sql->params[0], $sql->params[1]);
                   }
                   if (strlen($sql->types)==3) {
                      $stmt->bind_param($sql->types, $sql->params[0], $sql->params[1], $sql->prams[2]);
                   }
                   if (strlen($sql->types)==4) {
                      $stmt->bind_param($sql->types, $sql->params[0], $sql->params[1], $sql->prams[2], $sql->params[3]);
                   }
                   if (strlen($sql->types)==5) {
                      $stmt->bind_param($sql->types, $sql->params[0], $sql->params[1], $sql->prams[2], $sql->params[3], $sql->params[4]);
                   }
                   //call_user_func_array('mysqli_stmt_bind_param', 
                   //     array_merge (array($stmt, $sql->types), $this->correctReferences($sql->params))
                   //); 
                   $stmt->execute();
                   $stmt->bind_result($cntR);
   				   if($stmt->fetch()){
   				  	   $this->imgCnt = $cntR;
   					   $retArr['cnt'] = $cntR;
   				   }
   				$stmt->close();
                }
			}
            else{
                $retArr['cnt'] = 0;
            }
		}
		else{
			echo 'ERROR returning image recordset: '.$connection->error.'<br/>';
			echo 'SQL: '.$sql->sql;
		}
		return $retArr;
	}
	
	/* 
	 * Input: array of criteria (e.g. array("state" => array("Arizona", "New Mexico"))
	 * Input criteria: taxa (INT: tid), country (string), state (string), tag (string), 
	 *     idNeeded (INT: 0,1), collid (INT), photographer (INT: photographerUid), 
	 *     cntPerCatagory (INT: 0-2), start (INT), limit (INT) 
	 *     e.g. {"state": ["Arizona", "New Mexico"],"taxa":["Pinus"}}
	 * Output: String, SQL to be used to query database  
	 */
	private function getSql($searchCriteria){
		$sqlWhere = '';
        $and = "";
        $types = "";
        $params = Array();
        $result = new PreparedSQL();
		// build clause for type status
		if(isset($searchCriteria['typestatus']) && $searchCriteria['typestatus']){
			$sqlWhere .= $and.' dwc.typestatus IN( ';
            $comma = "";
            foreach ($searchCriteria['typestatus'] as $term) {  
               $types .= 's';
               $params[] = $term;
               $sqlWhere .= "$comma ?";            
               $comma = ",";
            }
			$sqlWhere .= ' ) ';
            $and = " AND ";
		}
		// build clause for genus 
		if(isset($searchCriteria['genus']) && $searchCriteria['genus']){
			$sqlWhere .= $and.' dwc.genus IN( ';
            $comma = "";
            foreach ($searchCriteria['genus'] as $term) {  
               $types .= 's';
               $params[] = $term;
               $sqlWhere .= "$comma ?";            
               $comma = ",";
            }
			$sqlWhere .= ' ) ';
            $and = " AND ";
		}
		// build clause for country
		if(isset($searchCriteria['country']) && $searchCriteria['country']){
			$sqlWhere .= $and.' dwc.country IN( ';
            $comma = "";
            foreach ($searchCriteria['country'] as $term) {  
               $types .= 's';
               $params[] = $term;
               $sqlWhere .= "$comma ?";            
               $comma = ",";
            }
			$sqlWhere .= ' ) ';
            $and = " AND ";
		}
		// build clause for state/province
		if(isset($searchCriteria['state']) && $searchCriteria['state']){
			$sqlWhere .= $and.' dwc.stateprovince IN( ';
            $comma = "";
            foreach ($searchCriteria['state'] as $term) {  
               $types .= 's';
               $params[] = $term;
               $sqlWhere .= "$comma ?";            
               $comma = ",";
            }
			$sqlWhere .= ' ) ';
            $and = " AND ";
		}
		// build clause for family
		if(isset($searchCriteria['family']) && $searchCriteria['family']){
			$sqlWhere .= $and.' dwc.family IN( ';
            $comma = "";
            foreach ($searchCriteria['family'] as $term) {  
               $types .= 's';
               $params[] = $term;
               $sqlWhere .= "$comma ?";            
               $comma = ",";
            }
			$sqlWhere .= ' ) ';
            $and = " AND ";
		}

        if ($sqlWhere != '') { 
          $and = " AND ";
        }
        $sqlWhere = " WHERE io.object_type_id = 2 $and $sqlWhere ";
		
		$sqlStr = 'SELECT DISTINCT concat(ifnull(r.url_prefix,\'\'),io.uri), concat(dwc.genus,\' \',dwc.species), dwc.country, dwc.state, '.
        'dwc.collectionobjectid, io.image_set_id ' .
		'FROM web_search dwc LEFT JOIN IMAGE_SET_collectionobject ic ON dwc.collectionobjectid = ic.collectionobjectid '.
		'LEFT JOIN IMAGE_OBJECT io ON ic.imagesetid = io.image_set_id  ' .
        'LEFT JOIN REPOSITORY r on io.repository_id = r.id' .
        ' '. $sqlWhere ;

		//Set start and limit
		$start = (isset($searchCriteria['start'])?$searchCriteria['start']:0);
		$limit = (isset($searchCriteria['limit'])?$searchCriteria['limit']:50);
		$sqlStr .= 'LIMIT '.$start.','.$limit;
        $result->sql = $sqlStr;
        $result->types = $types;
        $result->params = $params;
        if ($debug) { print_r($result); }
		return $result;
	}

	public function testSql($searchCriteria){
		echo json_encode($searchCriteria).'<br/>';
		echo $this->getSql($searchCriteria).'<br/>';
		//$imgArr = $this->getImages($searchCriteria);
		//print_r($imgArr);
	}
	
	//variable setters and getters
	public function getImgCnt(){
		return $this->imgCnt;
	}

	//Misc functions
 	private function cleanInArray($arr){
 		$newArray = Array();
 		foreach($arr as $key => $value){
 			$newArray[$this->cleanInStr($key)] = $this->cleanInStr($value);
 		}
 		return $newArray;
 	}

	private function cleanInStr($str){
		$newStr = trim($str);
		$newStr = preg_replace('/\s\s+/', ' ',$newStr);
		$newStr = $this->conn->real_escape_string($newStr);
		return $newStr;
	}
}

mysqli_report(MYSQLI_REPORT_OFF);

?>
