<?php
include_once('connection_library.php');

class DataExplorer { 

    public function getTypeStatusList(){
        global $connection;

        $retArr = array();
        $sql = "select count(*), typestatusname from determination d left join fragment f on d.fragmentid = f.fragmentid left join IMAGE_SET_collectionobject i on f.collectionobjectid = i.collectionobjectid where i.collectionobjectid is not null  group by typestatusname";
        $stmt = $connection->stmt_init();
        if (@$stmt->prepare($sql)) { 
           @$stmt->execute();
           $stmt->bind_result($count,$typestatus);
           $row = 0;
           while($stmt->fetch()){
              if ($typestatus==null) { $typestatus = "none"; }
              $retArr[$row]['value'] = $typestatus;
              $retArr[$row]['label'] = "$typestatus ($count)";
              $row++;
           }
        $stmt->close();
        }

        return $retArr;
    }

    public function getCountryList(){
        global $connection;

        $retArr = array();
        $sql = "select count(*), country from web_search w left join IMAGE_SET_collectionobject i on w.collectionobjectid = i.collectionobjectid where i.collectionobjectid is not null group by w.country";
        $stmt = $connection->stmt_init();
        if (@$stmt->prepare($sql)) { 
           @$stmt->execute();
           $stmt->bind_result($count,$typestatus);
           $row = 0;
           while($stmt->fetch()){
              if ($typestatus==null) { $typestatus = "none"; }
              $retArr[$row]['value'] = $typestatus;
              $retArr[$row]['label'] = "$typestatus ($count)";
              $row++;
           }
        $stmt->close();
        }

        return $retArr;
    }

    public function getGenusList() { 
        $sql = "select count(*), genus from web_search w left join IMAGE_SET_collectionobject i on w.collectionobjectid = i.collectionobjectid where i.collectionobjectid is not null group by w.genus";
        return $this->runQuery($sql);
    }

    public function getFamilyList() { 
        $sql = "select count(*), family from web_search w left join IMAGE_SET_collectionobject i on w.collectionobjectid = i.collectionobjectid where i.collectionobjectid is not null group by w.family";
        return $this->runQuery($sql);
    }

    protected function runQuery($sql) { 
        global $connection;

        $retArr = array();
        $stmt = $connection->stmt_init();
        if (@$stmt->prepare($sql)) {
           @$stmt->execute();
           $stmt->bind_result($count,$typestatus);
           $row = 0;
           while($stmt->fetch()){
              if ($typestatus==null) { $typestatus = "none"; }
              $retArr[$row]['value'] = $typestatus;
              $retArr[$row]['label'] = "$typestatus ($count)";
              $row++;
           }
        $stmt->close();
        }

        return $retArr;
    }

}

?>
